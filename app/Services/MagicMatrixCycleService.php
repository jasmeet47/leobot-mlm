<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Financially isolated Magic eligibility, immutable matrix positions and verified top-ups.
 * No Trading Investment/Company Trading Wallet changes and NO Magic payouts.
 * Actual external receipt validation remains a human/operator obligation.
 */
final class MagicMatrixCycleService
{
    private const SCALE = 8;
    private const MONEY_LIMIT = '999999999999.99999999';

    public static function enrollFromActivePackage(User $member, int $packageId, string $sourceReference): int
    {
        self::requireGate('magic_matrix_enrollment_enabled');
        self::validateReference($sourceReference);
        $sourceReference = strtoupper($sourceReference);
        if ($packageId < 1 || !$member->exists) {
            throw new InvalidArgumentException('Valid member and package required.');
        }

        return DB::transaction(function () use ($member, $packageId, $sourceReference): int {
            $person = self::lockMember((int) $member->getKey());
            $settings = self::lockSettings('enrollment_enabled');
            $package = DB::table('activation_packages')->where('id', $packageId)
                ->lockForUpdate()->first();
            if (!$package || (int) $package->user_id !== (int) $person->id || $package->status !== 'active') {
                throw new RuntimeException('An ACTIVE, member-owned trading activation package is required.');
            }
            $amount = self::money((string) $package->investment_amount);
            $minimum = self::money((string) $settings->minimum_tier_usdt);
            // Final rule: individual ACTIVE package must be strictly GREATER than configured minimum.
            if (bccomp($amount, $minimum, self::SCALE) <= 0) {
                throw new RuntimeException('Activation package does not meet Magic minimum.');
            }
            $old = DB::table('magic_matrix_cycles')->where('source_reference', $sourceReference)->first();
            if ($old) {
                if ((int) $old->user_id !== (int) $person->id || (int) $old->activation_package_id !== $packageId) {
                    throw new RuntimeException('Reference already used for another enrollment.');
                }
                return (int) $old->id;
            }
            if (DB::table('magic_matrix_positions')->where('user_id', $person->id)->exists()) {
                throw new RuntimeException('Lifetime matrix position already exists; use verified re-topup instead.');
            }
            $positionId = self::newLifetimePosition((int) $person->id, (int) $settings->max_depth);
            $multiplier = self::capMultiplier($settings->earning_cap_multiplier);
            $cap = self::multiplyMoney($amount, $multiplier);
            return DB::table('magic_matrix_cycles')->insertGetId([
                'user_id' => $person->id, 'position_id' => $positionId,
                'activation_package_id' => $packageId,
                'source_reference' => $sourceReference, 'tier_usdt' => $amount,
                'earning_basis_usdt' => $amount, 'earning_multiplier' => $multiplier,
                'earning_cap_usdt' => $cap, 'magic_paid_usdt' => '0.00000000',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    /** Read-only quote. Actual amount is re-evaluated atomically when recording payment. */
    public static function quote(int $memberId, ?CarbonImmutable $asOf = null): array
    {
        if ($memberId < 1) {
            throw new InvalidArgumentException('Invalid member.');
        }
        $cycle = DB::table('magic_matrix_cycles')->where('user_id', $memberId)
            ->orderByDesc('id')->first();
        if (!$cycle || $cycle->status !== 'capped' || !$cycle->cap_reached_at) {
            throw new RuntimeException('No capped Magic cycle due for top-up.');
        }
        $settings = DB::table('magic_matrix_settings')->where('id', 1)->first();
        if (!$settings) {
            throw new RuntimeException('Missing Magic settings.');
        }
        $base = self::money((string) $cycle->tier_usdt);
        $factor = MagicTopupPolicy::multiplier(
            CarbonImmutable::parse($cycle->cap_reached_at, 'UTC'),
            ($asOf ?? CarbonImmutable::now('UTC'))->utc(),
            (int) $settings->grace_days
        );
        return ['previous_cycle_id' => (int) $cycle->id, 'base_tier_usdt' => $base,
            'delay_multiplier' => $factor,
            'required_usdt' => MagicTopupPolicy::requiredAmount($base, $factor)];
    }

    /**
     * Admin records an independently verified, settled external deposit (not an instruction
     * to take funds from a member's wallet). A real payment must preexist this operation.
     * Exact top-up only. Unique receipt. Atomic company MAGIC wallet accounting.
     */
    public static function recordVerifiedTopup(
        User $admin,
        int $memberId,
        string $receivedUsdt,
        string $paymentReference
    ): int {
        self::requireGate('magic_matrix_topup_enabled');
        self::validateReference($paymentReference);
        $paymentReference = strtoupper($paymentReference);
        $receivedUsdt = self::money($receivedUsdt);
        if ($memberId < 1 || !$admin->exists) {
            throw new InvalidArgumentException('Invalid admin or member.');
        }

        return DB::transaction(function () use ($admin, $memberId, $receivedUsdt, $paymentReference): int {
            self::lockAdmin((int) $admin->getKey());
            $person = self::lockMember($memberId);
            $settings = self::lockSettings('topup_enabled');
            // lock settings before topups to ensure serializable rate/eligibility snapshots
            $cycle = DB::table('magic_matrix_cycles')->where('user_id', $memberId)
                ->orderByDesc('id')->lockForUpdate()->first();
            // Retry is checked BEFORE the active-cycle guard. A successful top-up
            // already created an active next cycle; it must remain idempotent.
            $existing = DB::table('magic_matrix_topups')->where('payment_reference', $paymentReference)->first();
            if ($existing) {
                if ((int) $existing->user_id !== $memberId ||
                    (int) $existing->new_cycle_id !== (int) ($cycle->id ?? 0) ||
                    bccomp((string) $existing->received_usdt, $receivedUsdt, 8) !== 0) {
                    throw new RuntimeException('Payment reference reused with different details.');
                }
                return (int) $existing->new_cycle_id;
            }
            if (!$cycle || $cycle->status !== 'capped' || !$cycle->cap_reached_at) {
                throw new RuntimeException('A capped Magic cycle is required.');
            }
            if (DB::table('magic_matrix_topups')->where('previous_cycle_id', $cycle->id)->exists()) {
                throw new RuntimeException('Cycle already renewed; duplicate payment prohibited.');
            }
            // Evaluate time AFTER database locks. Admin cannot backdate payment.
            $verifiedAt = CarbonImmutable::now('UTC');
            $base = self::money((string) $cycle->tier_usdt);
            $factor = MagicTopupPolicy::multiplier(
                CarbonImmutable::parse($cycle->cap_reached_at, 'UTC'),
                $verifiedAt, (int) $settings->grace_days
            );
            $required = MagicTopupPolicy::requiredAmount($base, $factor);
            if (bccomp($required, $receivedUsdt, 8) !== 0) {
                throw new RuntimeException('Exact required Magic re-topup not received.');
            }
            $position = DB::table('magic_matrix_positions')->where('id', $cycle->position_id)->first();
            if (!$position || (int) $position->user_id !== $memberId) {
                throw new RuntimeException('Lifetime position integrity failure.');
            }
            $wallet = DB::table('company_magic_wallet')->where('id', 1)->lockForUpdate()->first();
            if (!$wallet) {
                throw new RuntimeException('Company Magic wallet is missing.');
            }
            $before = self::money((string) $wallet->balance);
            $after = self::addMoney($before, $receivedUsdt);
            $totalAfter = self::addMoney(self::money((string) $wallet->total_verified_topups), $receivedUsdt);
            $capMultiplier = self::capMultiplier($settings->earning_cap_multiplier);
            // Magic earning basis is the ACTUAL verified Magic-only deposit;
            // trading investment stays unchanged. Normal tier stays fixed for
            // next six-day quote, so late surcharges do not compound the tier.
            $newId = DB::table('magic_matrix_cycles')->insertGetId([
                'user_id' => $memberId, 'position_id' => $position->id,
                'source_reference' => 'RETOPUP:' . $paymentReference,
                'tier_usdt' => $base, 'earning_basis_usdt' => $receivedUsdt,
                'earning_multiplier' => $capMultiplier,
                'earning_cap_usdt' => self::multiplyMoney($receivedUsdt, $capMultiplier),
                'magic_paid_usdt' => '0.00000000', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $topupId = DB::table('magic_matrix_topups')->insertGetId([
                'user_id' => $memberId, 'previous_cycle_id' => $cycle->id,
                'new_cycle_id' => $newId, 'verified_by' => $admin->id,
                'payment_reference' => $paymentReference, 'base_tier_usdt' => $base,
                'delay_multiplier' => $factor, 'received_usdt' => $receivedUsdt,
                'verified_at' => $verifiedAt, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('company_magic_wallet')->where('id', 1)->update([
                'balance' => $after, 'total_verified_topups' => $totalAfter, 'updated_at' => now(),
            ]);
            DB::table('company_magic_wallet_ledger')->insert([
                'topup_id' => $topupId, 'transaction_key' => 'magic:topup:' . hash('sha256', $paymentReference),
                'amount' => $receivedUsdt, 'balance_before' => $before,
                'balance_after' => $after, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $newId;
        }, 3);
    }

    private static function newLifetimePosition(int $userId, int $maxDepth): int
    {
        // Caller holds FOR UPDATE lock on singleton settings row: serializes placement.
        $root = DB::table('magic_matrix_positions')->orderBy('id')->first();
        if (!$root) {
            return DB::table('magic_matrix_positions')->insertGetId([
                'user_id' => $userId, 'parent_id' => null, 'slot' => null,
                'depth' => 0, 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // SQL finds the first open parent without loading a 20-level tree into PHP.
        // The singleton settings row is locked by the caller and serializes insertions.
        $parent = DB::table('magic_matrix_positions as p')
            ->where('p.depth', '<', $maxDepth)
            ->whereRaw('(SELECT COUNT(*) FROM magic_matrix_positions AS c WHERE c.parent_id = p.id) < 2')
            ->orderBy('p.depth')->orderBy('p.id')->first(['p.id', 'p.depth']);
        if (!$parent) {
            throw new RuntimeException('20-level matrix capacity reached.');
        }
        $used = array_map('intval', DB::table('magic_matrix_positions')
            ->where('parent_id', $parent->id)->pluck('slot')->all());
        foreach ([1, 2] as $slot) {
            if (!in_array($slot, $used, true)) {
                return DB::table('magic_matrix_positions')->insertGetId([
                    'user_id' => $userId, 'parent_id' => $parent->id, 'slot' => $slot,
                    'depth' => (int) $parent->depth + 1, 'placed_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        throw new RuntimeException('Matrix slot inconsistency.');
    }

    private static function lockSettings(string $writeFlag): object
    {
        $settings = DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
        $global = DB::table('magic_income_settings')->where('id', 1)->first();
        if (!$settings || !$global || !self::enabled($global->magic_enabled) ||
            !self::enabled($settings->{$writeFlag})) {
            throw new RuntimeException('Magic Matrix operation is disabled.');
        }
        if ((int) $settings->max_depth !== 20 || (int) $settings->grace_days !== 6) {
            throw new RuntimeException('Magic Matrix final policy settings must be 20 levels / 6 days.');
        }
        return $settings;
    }

    private static function lockMember(int $id): object
    {
        $member = DB::table('users')->where('id', $id)->lockForUpdate()->first();
        if (!$member || $member->status !== 'active' || self::enabled($member->is_blocked)) {
            throw new AuthorizationException('Active unblocked member required.');
        }
        return $member;
    }

    private static function lockAdmin(int $id): object
    {
        $admin = DB::table('users')->where('id', $id)->lockForUpdate()->first();
        if (!$admin || !in_array($admin->role, ['admin', 'super_admin'], true) ||
            $admin->status !== 'active' || self::enabled($admin->is_blocked)) {
            throw new AuthorizationException('Active unblocked admin required.');
        }
        return $admin;
    }

    private static function requireGate(string $gate): void
    {
        if (config('leobot_magic.' . $gate, false) !== true ||
            (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Magic Matrix is disabled pending independent financial approval.');
        }
    }

    private static function validateReference(string $reference): void
    {
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{2,90}\z/D', $reference)) {
            throw new InvalidArgumentException('Invalid unique receipt/reference format.');
        }
    }

    private static function capMultiplier(mixed $value): int
    {
        if (!in_array((int) $value, [3, 4, 5], true) || (string) $value !== (string) (int) $value) {
            throw new RuntimeException('Magic earning multiplier must be 3, 4 or 5.');
        }
        return (int) $value;
    }

    private static function money(string $value): string
    {
        if (!preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/D', $value) ||
            bccomp($value, '0', self::SCALE) < 0 ||
            bccomp($value, self::MONEY_LIMIT, self::SCALE) > 0) {
            throw new InvalidArgumentException('Invalid financial amount.');
        }
        return bcadd($value, '0', self::SCALE);
    }

    private static function addMoney(string $a, string $b): string
    {
        return self::money(bcadd($a, $b, self::SCALE));
    }

    private static function multiplyMoney(string $a, int $multiplier): string
    {
        return self::money(bcmul($a, (string) $multiplier, self::SCALE));
    }

    private static function enabled(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 't', 'on'], true);
    }
}
