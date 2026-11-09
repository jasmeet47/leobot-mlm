<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Adds a missing lifetime position from an existing funded source; never debits or pays a wallet. */
final class MagicMatrixEnrollmentService
{
    public static function inspect(int $memberId): array
    {
        $member = DB::table('users')->where('id', $memberId)->first();
        $matrix = DB::table('magic_matrix_settings')->where('id', 1)->first();
        $global = DB::table('magic_income_settings')->where('id', 1)->first();
        $minimum = $matrix && bccomp((string) $matrix->minimum_tier_usdt, '50', 8) >= 0 ? (string) $matrix->minimum_tier_usdt : '50.00000000';
        $result = ['can_place' => false, 'reason' => '', 'minimum' => $minimum, 'source' => null];
        if (! $member || $member->role !== 'member' || $member->is_blocked || $member->deleted_at || ! in_array($member->status, ['active', 'inactive'], true)) {
            $result['reason'] = 'Current unblocked member required.';

            return $result;
        }
        if (DB::table('magic_matrix_positions')->where('user_id', $memberId)->exists()) {
            $result['reason'] = 'Already placed in the lifetime Magic Matrix.';

            return $result;
        }
        if (config('leobot_magic.magic_matrix_enrollment_enabled', false) !== true || (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true)) {
            $result['reason'] = 'Magic enrollment server approval is OFF.';

            return $result;
        }
        if (! $matrix || ! $global || ! $global->magic_enabled || ! $matrix->enrollment_enabled) {
            $result['reason'] = 'Global Magic or Matrix Enrollment is OFF in admin settings.';

            return $result;
        }
        $sources = [];
        $package = DB::table('activation_packages as p')->where('p.user_id', $memberId)->where('p.status', 'active')->where('p.investment_amount', '>=', $minimum)->whereExists(function ($q): void {
            $q->selectRaw('1')->from('financial_ledger as l')->whereColumn('l.user_id', 'p.user_id')->whereColumn('l.reference_id', 'p.id')->where('l.reference_type', 'activation_package')->where('l.wallet_type', 'activation_balance')->where('l.entry_type', 'package_activation')->where('l.status', 'completed')->whereRaw('l.amount = -p.investment_amount')->whereRaw("l.transaction_key = 'activation:debit:' || p.activation_key::text");
        })->orderBy('p.created_at')->orderBy('p.id')->first(['p.id', 'p.investment_amount as amount', 'p.created_at']);
        $stake = DB::table('staking_positions as p')->where('p.user_id', $memberId)->where('p.status', 'active')->whereRaw('p.amount-p.withdrawn_amount >= ?', [$minimum])->whereExists(function ($q): void {
            $q->selectRaw('1')->from('financial_ledger as l')->whereColumn('l.user_id', 'p.user_id')->whereColumn('l.reference_id', 'p.id')->where('l.reference_type', 'staking_position')->where('l.wallet_type', 'staking_principal')->where('l.status', 'completed')->whereColumn('l.amount', 'p.amount')->whereRaw("l.transaction_key = 'staking:principal:' || p.request_key");
        })->orderBy('p.created_at')->orderBy('p.id')->first(['p.id', DB::raw('p.amount-p.withdrawn_amount as amount'), 'p.created_at']);
        foreach (['package' => $package, 'stake' => $stake] as $type => $source) {
            if ($source) {
                $sources[] = ['type' => $type, 'id' => (int) $source->id, 'amount' => (string) $source->amount, 'created_at' => $source->created_at];
            }
        }
        usort($sources, fn ($a, $b) => [$a['created_at'], $a['type'], $a['id']] <=> [$b['created_at'], $b['type'], $b['id']]);
        $result['source'] = $sources[0] ?? null;
        $result['can_place'] = $result['source'] !== null;
        $result['reason'] = $result['can_place'] ? 'Qualified funded source found. Ready for Matrix placement.' : 'One active, funded activation or remaining stake must meet '.$minimum.' USDT. Smaller amounts are not added together.';

        return $result;
    }

    public static function enrollmentEnabled(): bool
    {
        if (config('leobot_magic.magic_matrix_enrollment_enabled', false) !== true
            || (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true)) {
            return false;
        }
        $matrix = DB::table('magic_matrix_settings')->where('id', 1)->first();
        $global = DB::table('magic_income_settings')->where('id', 1)->first();

        return $matrix && $global && $matrix->enrollment_enabled && $global->magic_enabled;
    }

    /** @return array<int, int> */
    public static function pendingMemberIds(): array
    {
        if (! self::enrollmentEnabled()) {
            return [];
        }
        $pending = [];
        $members = DB::table('users as u')->where('u.role', 'member')
            ->whereIn('u.status', ['active', 'inactive'])->where('u.is_blocked', false)->whereNull('u.deleted_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('magic_matrix_positions as p')->whereColumn('p.user_id', 'u.id');
            })->select('u.id')->lazyById(200, 'u.id', 'id');
        foreach ($members as $member) {
            $state = self::inspect((int) $member->id);
            if ($state['can_place']) {
                $pending[] = ['id' => (int) $member->id, 'qualified_at' => $state['source']['created_at']];
            }
        }
        usort($pending, fn ($a, $b) => [$a['qualified_at'], $a['id']] <=> [$b['qualified_at'], $b['id']]);

        return array_column($pending, 'id');
    }

    /** Catch up missed funded qualifications without moving existing lifetime positions. */
    public static function reconcilePending(): int
    {
        $placed = 0;
        foreach (self::pendingMemberIds() as $memberId) {
            if (self::synchronize($memberId) !== null) {
                if (! DB::table('magic_matrix_positions')->where('user_id', $memberId)->exists()) {
                    throw new RuntimeException('Magic cycle has no lifetime position; integrity review required.');
                }
                $placed++;
            }
        }

        return $placed;
    }

    public static function synchronize(int $memberId, ?User $admin = null): ?int
    {
        if (! $admin && (config('leobot_magic.magic_matrix_enrollment_enabled', false) !== true || (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true))) {
            return null;
        }

        return DB::transaction(function () use ($memberId, $admin): ?int {
            if ($admin) {
                $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
                if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                    throw new RuntimeException('Active unblocked administrator required.');
                }
            }
            $member = User::whereKey($memberId)->lockForUpdate()->firstOrFail();
            if ($member->role !== 'member' || $member->is_blocked || $member->deleted_at || ! in_array($member->status, ['active', 'inactive'], true)) {
                throw new RuntimeException('Current unblocked member required.');
            }
            DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
            if (DB::table('magic_matrix_positions')->where('user_id', $memberId)->exists()) {
                return DB::table('magic_matrix_cycles')->where('user_id', $memberId)->orderByDesc('id')->value('id');
            }
            $state = self::inspect($memberId);
            if (! $state['can_place']) {
                if ($admin) {
                    throw new RuntimeException($state['reason']);
                }

                return null;
            }
            $source = $state['source'];
            $cycle = $source['type'] === 'package'
                ? MagicMatrixCycleService::enrollFromActivePackage($member, $source['id'], 'ACT:'.$source['id'])
                : MagicMatrixCycleService::enrollFromStake($member, $source['id']);
            DB::table('member_security_audits')->insert(['actor_id' => $admin?->id ?? $memberId, 'action' => 'magic_matrix_enrollment_synchronized', 'details' => json_encode(['user_id' => $memberId, 'cycle_id' => $cycle, 'source_type' => $source['type'], 'source_id' => $source['id'], 'source_amount' => $source['amount'], 'wallet_debit' => false, 'income_payout' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $cycle;
        }, 3);
    }
}
