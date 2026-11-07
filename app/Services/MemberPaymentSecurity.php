<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberPaymentSecurity
{
    public static function changesEnabled(): bool
    {
        return in_array(DB::table('member_security_controls')->where('id', 1)->value('wallet_changes_enabled'), [true, 1, '1', 't', 'true'], true);
    }

    public static function assertChangesEnabled(): void
    {
        $policy = DB::table('member_security_controls')->where('id', 1)->lockForUpdate()->first();
        if (! $policy || ! in_array($policy->wallet_changes_enabled, [true, 1, '1', 't', 'true'], true)) {
            throw new RuntimeException('Profile wallet changes are disabled by the administrator.');
        }
    }

    public static function selectedId(int $userId, string $network): ?int
    {
        $id = DB::table('member_wallet_profiles')->where('user_id', $userId)->where('network', $network)->value('wallet_id');

        return $id ? (int) $id : (DB::table('usdt_member_wallets')->where('user_id', $userId)->where('network', $network)->whereNotNull('verified_at')->whereNull('revoked_at')->orderByDesc('id')->value('id'));
    }

    public static function wallets(int $userId): Collection
    {
        return DB::table('usdt_member_wallets')->where('user_id', $userId)->whereNotNull('verified_at')->whereNull('revoked_at')->whereIn('network', ['BEP20', 'TRC20'])->get(['id', 'network', 'address'])->filter(fn ($w) => (int) $w->id === self::selectedId($userId, $w->network))->values();
    }

    public static function select(int $userId, int $walletId, string $network, ?array $beforeSnapshot = null): void
    {
        DB::transaction(function () use ($userId, $walletId, $network, $beforeSnapshot): void {
            self::assertChangesEnabled();
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $wallet = DB::table('usdt_member_wallets')->where('id', $walletId)->where('user_id', $userId)->where('network', $network)->whereNotNull('verified_at')->whereNull('revoked_at')->lockForUpdate()->first();
            if (! $user || $user->role !== 'member' || $user->is_blocked || $user->deleted_at || ! $wallet) {
                throw new RuntimeException('A member-owned verified wallet is required.');
            }
            $before = $beforeSnapshot !== null ? $beforeSnapshot['wallet_id'] : self::selectedId($userId, $network);
            DB::table('member_wallet_profiles')->updateOrInsert(['user_id' => $userId, 'network' => $network], ['wallet_id' => $walletId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('member_security_audits')->insert(['actor_id' => $userId, 'action' => 'profile_wallet_verified', 'details' => json_encode(['network' => $network, 'before_wallet_id' => $before, 'wallet_id' => $walletId], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }

    public static function toggle(User $admin, bool $enabled): void
    {
        DB::transaction(function () use ($admin, $enabled): void {
            $policy = DB::table('member_security_controls')->where('id', 1)->lockForUpdate()->first();
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $policy || ! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Authorized active administrator required.');
            }
            DB::table('member_security_controls')->where('id', 1)->update(['wallet_changes_enabled' => $enabled, 'updated_at' => now()]);
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'wallet_changes_control', 'details' => json_encode(['before' => (bool) $policy->wallet_changes_enabled, 'after' => $enabled], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }
}
