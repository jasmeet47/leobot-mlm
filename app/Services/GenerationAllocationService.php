<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 2: classify ONE already-funded global profit period by network business.
 * Only reclassifies the funded reserve; NEVER pays members or credits wallets.
 * Snapshot is immutable after a successful allocation, even if business changes.
 */
final class GenerationAllocationService
{
    private const SCALE = 8;
    private const MAX_LEVEL = 51;
    // Fail closed rather than silently generating a partial allocation.
    private const MAX_USERS = 10000;
    private const MAX_ACTIVE_PACKAGES = 20000;
    private const MAX_ALLOCATION_ROWS = 100000;
    private const MAX_MONEY = '999999999999.99999999';

    /**
     * For each level, all members with positive active downline business
     * contribute to the denominator. An unqualified candidate's weighted
     * share is HELD, not redistributed to the qualified candidates.
     * Zero candidates or fractional dust are UNALLOCATED.
     *
     * @return int The immutable allocation run ID (same on retry).
     */
    public static function allocate(User $admin, int $fundingId): int
    {
        if ($fundingId <= 0) {
            throw new InvalidArgumentException('Invalid funding ID.');
        }

        return DB::transaction(function () use ($admin, $fundingId): int {
            // PostgreSQL: keep member, package and configuration reads in one
            // transaction-level snapshot; serialisation failures roll back.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $actualAdmin = DB::table('users')->where('id', $admin->getKey())
                ->lockForUpdate()->first(['id', 'username', 'status']);
            if (!$actualAdmin || strtoupper((string) $actualAdmin->username) !== 'ADMIN'
                || (string) $actualAdmin->status !== 'active') {
                throw new AuthorizationException('Active ADMIN approval required.');
            }

            // Same shared lock as Phase 1 funding: prevents parallel changes
            // to the global reserve, including another allocation.
            $pool = DB::table('generation_pool_accounts')->where('id', 1)
                ->lockForUpdate()->first();
            if (!$pool) {
                throw new RuntimeException('Generation pool account missing.');
            }
            self::assertPoolReconciles($pool);

            $funding = DB::table('generation_profit_fundings')
                ->where('id', $fundingId)->lockForUpdate()->first();
            if (!$funding) {
                throw new RuntimeException('Funding record missing.');
            }

            $existing = DB::table('generation_allocation_runs')
                ->where('funding_id', $fundingId)->first();
            if ($existing) {
                // A retry must not re-evaluate current team/business data.
                return (int) $existing->id;
            }

            $settings = DB::table('generation_pool_settings')
                ->where('id', 1)->lockForUpdate()->first();
            if (!$settings || self::trueValue($settings->generation_enabled)) {
                throw new RuntimeException('Generation distribution must remain OFF.');
            }
            if ($funding->status !== 'funded_unallocated') {
                throw new RuntimeException('Funding is not available for first allocation.');
            }

            $budgets = DB::table('generation_level_budgets')
                ->where('funding_id', $fundingId)->orderBy('level_number')
                ->lockForUpdate()->get();
            if ($budgets->count() !== self::MAX_LEVEL) {
                throw new RuntimeException('Exactly 51 level budgets are required.');
            }

            $levelBudgetTotal = '0.00000000';
            foreach ($budgets as $index => $budget) {
                if ((int) $budget->level_number !== $index + 1
                    || bccomp((string) $budget->unallocated_amount, (string) $budget->budget_amount, 8) !== 0
                    || bccomp((string) $budget->held_amount, '0', 8) !== 0
                    || bccomp((string) $budget->payable_amount, '0', 8) !== 0
                    || bccomp((string) $budget->paid_amount, '0', 8) !== 0) {
                    throw new RuntimeException('Budget is corrupt or already classified.');
                }
                self::assertNonnegative((string) $budget->budget_amount);
                $levelBudgetTotal = bcadd($levelBudgetTotal, (string) $budget->budget_amount, 8);
            }
            if (bccomp(
                bcadd($levelBudgetTotal, (string) $funding->rounding_unallocated, 8),
                (string) $funding->pool_amount, 8
            ) !== 0) {
                throw new RuntimeException('Funded level budget total does not reconcile.');
            }

            // Create an all-members/all-active-packages snapshot; never silently
            // truncate. For larger networks use a future audited batch worker.
            $users = DB::table('users')->select('id', 'status', 'sponsor_user_id')
                ->orderBy('id')->limit(self::MAX_USERS + 1)->get();
            if ($users->count() > self::MAX_USERS) {
                throw new RuntimeException('Network exceeds safe snapshot size.');
            }
            $byId = [];
            foreach ($users as $user) {
                $byId[(int) $user->id] = $user;
            }

            $packages = DB::table('activation_packages')
                ->where('status', 'active')->select('user_id', 'investment_amount')
                ->orderBy('id')->limit(self::MAX_ACTIVE_PACKAGES + 1)->get();
            if ($packages->count() > self::MAX_ACTIVE_PACKAGES) {
                throw new RuntimeException('Active packages exceed safe snapshot size.');
            }

            $activeOwner = [];
            $weights = []; // [level][recipientId] => cumulative active package business
            $business = []; // [recipientId][level] => identical weights for qualification
            foreach ($packages as $package) {
                $amount = (string) $package->investment_amount;
                self::assertNonnegative($amount);
                $ownerId = (int) $package->user_id;
                if (!isset($byId[$ownerId])) {
                    throw new RuntimeException('Active package owner missing.');
                }
                $activeOwner[$ownerId] = true;
                $currentId = $ownerId;
                $visited = [$ownerId => true];
                for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
                    $parent = $byId[$currentId]->sponsor_user_id;
                    if ($parent === null) {
                        break;
                    }
                    $parent = (int) $parent;
                    if (!isset($byId[$parent]) || isset($visited[$parent])) {
                        throw new RuntimeException('Missing or cyclic sponsor chain.');
                    }
                    $visited[$parent] = true;
                    $weights[$level][$parent] = bcadd(
                        $weights[$level][$parent] ?? '0.00000000', $amount, 8
                    );
                    self::assertNonnegative($weights[$level][$parent]);
                    $business[$parent][$level] = $weights[$level][$parent];
                    $currentId = $parent;
                }
            }

            $rules = DB::table('generation_qualification_settings')
                ->orderBy('unlock_through_level')->get();
            if ($rules->isEmpty()) {
                throw new RuntimeException('Qualification rules missing.');
            }
            $lastUnlock = 0;
            foreach ($rules as $rule) {
                $from = (int) $rule->business_from_level;
                $to = (int) $rule->business_to_level;
                $unlocks = (int) $rule->unlock_through_level;
                if ($from < 1 || $to > 51 || $from > $to
                    || $unlocks < 1 || $unlocks > 51 || $unlocks <= $lastUnlock) {
                    throw new RuntimeException('Invalid qualification configuration.');
                }
                self::assertNonnegative((string) $rule->required_business);
                $lastUnlock = $unlocks;
            }
            if ((int) $rules->first()->unlock_through_level !== 1
                || $lastUnlock !== self::MAX_LEVEL) {
                throw new RuntimeException('Qualification range must cover levels 1-51.');
            }

            $unlockCache = [];
            $runId = DB::table('generation_allocation_runs')->insertGetId([
                'funding_id' => $fundingId,
                'approved_by' => $actualAdmin->id,
                'policy_version' => 'network_business_v1',
                'status' => 'classified_no_payout',
                'snapshot_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $allPayable = '0.00000000';
            $allHeld = '0.00000000';
            $allUnallocated = (string) $funding->rounding_unallocated;
            $candidateCount = 0;

            foreach ($budgets as $budget) {
                $level = (int) $budget->level_number;
                $amount = (string) $budget->budget_amount;
                $levelPayable = '0.00000000';
                $levelHeld = '0.00000000';

                $candidates = $weights[$level] ?? [];
                // An inactive level retains its complete amount unallocated.
                if (self::trueValue($budget->was_active) && bccomp($amount, '0', 8) > 0 && $candidates) {
                    ksort($candidates, SORT_NUMERIC);
                    $totalWeight = '0.00000000';
                    foreach ($candidates as $weight) {
                        $totalWeight = bcadd($totalWeight, $weight, 8);
                    }
                    if (bccomp($totalWeight, '0', 8) > 0) {
                        foreach ($candidates as $candidateId => $weight) {
                            // Exact decimal weighting; any fractional dust stays unallocated.
                            $share = bcdiv(bcmul($amount, $weight, 16), $totalWeight, 8);
                            if (bccomp($share, '0', 8) <= 0) {
                                continue;
                            }
                            $candidateCount++;
                            if ($candidateCount > self::MAX_ALLOCATION_ROWS) {
                                throw new RuntimeException('Too many allocation rows for safe processing.');
                            }
                            if (!isset($unlockCache[$candidateId])) {
                                $unlockCache[$candidateId] = self::unlockLevel(
                                    $business[$candidateId] ?? [], $rules
                                );
                            }
                            $unlocked = $unlockCache[$candidateId];
                            $reason = 'qualified';
                            if ((string) $byId[$candidateId]->status !== 'active') {
                                $reason = 'member_inactive';
                            } elseif (!isset($activeOwner[$candidateId])) {
                                $reason = 'no_active_package';
                            } elseif ($unlocked < $level) {
                                $reason = 'level_locked';
                            }
                            $qualified = $reason === 'qualified';
                            if ($qualified) {
                                $levelPayable = bcadd($levelPayable, $share, 8);
                            } else {
                                $levelHeld = bcadd($levelHeld, $share, 8);
                            }
                            DB::table('generation_member_allocations')->insert([
                                'allocation_run_id' => $runId,
                                'funding_id' => $fundingId,
                                'level_budget_id' => $budget->id,
                                'user_id' => $candidateId,
                                'level_number' => $level,
                                'network_business_snapshot' => $weight,
                                'share_amount' => $share,
                                'unlocked_through_level_snapshot' => $unlocked,
                                'classification' => $qualified ? 'payable' : 'held',
                                'qualification_reason' => $reason,
                                'status' => $qualified
                                    ? 'qualified_reserved_no_payout' : 'held_unqualified',
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                }

                $levelClassified = bcadd($levelHeld, $levelPayable, 8);
                if (bccomp($levelClassified, $amount, 8) > 0) {
                    throw new RuntimeException('Shares exceed level budget.');
                }
                $levelUnallocated = bcsub($amount, $levelClassified, 8);
                DB::table('generation_level_budgets')->where('id', $budget->id)->update([
                    'held_amount' => $levelHeld,
                    'payable_amount' => $levelPayable,
                    'unallocated_amount' => $levelUnallocated,
                    'updated_at' => now(),
                ]);
                $allHeld = bcadd($allHeld, $levelHeld, 8);
                $allPayable = bcadd($allPayable, $levelPayable, 8);
                $allUnallocated = bcadd($allUnallocated, $levelUnallocated, 8);
            }

            $classified = bcadd($allHeld, $allPayable, 8);
            $total = bcadd($classified, $allUnallocated, 8);
            if (bccomp($total, (string) $funding->pool_amount, 8) !== 0
                || bccomp((string) $pool->unallocated_balance, $classified, 8) < 0) {
                throw new RuntimeException('Global allocation does not reconcile.');
            }

            $oldUnallocated = (string) $pool->unallocated_balance;
            $oldHeld = (string) $pool->held_balance;
            $oldPayable = (string) $pool->payable_balance;
            $newUnallocated = bcsub($oldUnallocated, $classified, 8);
            $newHeld = bcadd($oldHeld, $allHeld, 8);
            $newPayable = bcadd($oldPayable, $allPayable, 8);
            self::assertNonnegative($newHeld);
            self::assertNonnegative($newPayable);

            DB::table('generation_pool_accounts')->where('id', 1)->update([
                'unallocated_balance' => $newUnallocated,
                'held_balance' => $newHeld,
                'payable_balance' => $newPayable,
                'updated_at' => now(),
            ]);

            DB::table('generation_allocation_runs')->where('id', $runId)->update([
                'payable_total' => $allPayable,
                'held_total' => $allHeld,
                'unallocated_total' => $allUnallocated,
                'candidate_rows' => $candidateCount,
                'updated_at' => now(),
            ]);
            DB::table('generation_profit_fundings')->where('id', $fundingId)->update([
                'status' => 'allocated_no_payout', 'updated_at' => now(),
            ]);

            // Zero-sum internal journal; preserves total pool balance.
            self::journal($runId, 'held', $allHeld, $oldUnallocated, $oldHeld);
            self::journal($runId, 'payable', $allPayable,
                bcsub($oldUnallocated, $allHeld, 8), $oldPayable);

            return (int) $runId;
        }, 3);
    }

    private static function unlockLevel(array $businessByLevel, $rules): int
    {
        $unlocked = 0;
        foreach ($rules as $rule) {
            $sum = '0.00000000';
            for ($level = (int) $rule->business_from_level;
                 $level <= (int) $rule->business_to_level; $level++) {
                $sum = bcadd($sum, $businessByLevel[$level] ?? '0.00000000', 8);
            }
            if (bccomp($sum, (string) $rule->required_business, 8) >= 0) {
                $unlocked = max($unlocked, (int) $rule->unlock_through_level);
            }
        }
        return $unlocked;
    }

    private static function journal(
        int $runId, string $bucket, string $amount,
        string $unallocatedBefore, string $bucketBefore
    ): void {
        if (bccomp($amount, '0', 8) <= 0) {
            return;
        }
        $unallocatedAfter = bcsub($unallocatedBefore, $amount, 8);
        $bucketAfter = bcadd($bucketBefore, $amount, 8);
        self::journalRow($runId, $bucket . '_reserve_out', 'generation_unallocated',
            '-' . $amount, $unallocatedBefore, $unallocatedAfter);
        self::journalRow($runId, $bucket . '_reserve_in', 'generation_' . $bucket,
            $amount, $bucketBefore, $bucketAfter);
    }

    private static function journalRow(
        int $runId, string $entry, string $wallet, string $amount,
        string $before, string $after
    ): void {
        DB::table('financial_ledger')->insert([
            'user_id' => null,
            'entry_type' => 'generation_allocation_' . $entry,
            'wallet_type' => $wallet,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => 'generation_allocation_run',
            'reference_id' => $runId,
            'transaction_key' => 'generation:allocation:' . $runId . ':' . $entry,
            'status' => 'completed',
            'description' => 'Phase 2: internal reserve classification only; no member payout.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private static function assertPoolReconciles(object $pool): void
    {
        foreach (['balance','unallocated_balance','held_balance',
                  'payable_balance','total_funded','total_paid'] as $column) {
            self::assertNonnegative((string) $pool->{$column});
        }
        $reconciled = bcadd(bcadd(
            (string) $pool->unallocated_balance, (string) $pool->held_balance, 8
        ), (string) $pool->payable_balance, 8);
        if (bccomp($reconciled, (string) $pool->balance, 8) !== 0
            || bccomp(bcsub((string) $pool->total_funded,
                (string) $pool->total_paid, 8), (string) $pool->balance, 8) !== 0
            || bccomp((string) $pool->total_paid, '0', 8) !== 0) {
            throw new RuntimeException('Pool reserve accounting is inconsistent.');
        }
    }

    private static function assertNonnegative(string $amount): void
    {
        if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount)
            || bccomp($amount, '0', 8) < 0
            || bccomp($amount, self::MAX_MONEY, 8) > 0) {
            throw new RuntimeException('Invalid nonnegative accounting amount.');
        }
    }

    private static function trueValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value),
            ['1', 't', 'true', 'yes', 'on'], true);
    }
}
