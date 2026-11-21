<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Member-scoped withdrawal switch, persisted as append-only administrator audit events. */
final class MemberWithdrawalControl
{
    public static function enabled(int $memberId): bool
    {
        $row = DB::table('member_security_audits')->where('action', 'admin_member_withdrawals')->whereRaw("details->>'user_id' = ?", [(string) $memberId])->latest('id')->selectRaw("details->>'enabled' as enabled")->first();

        return $row === null || $row->enabled === 'true';
    }

    public static function assertAllowed(int $memberId): void
    {
        $member = DB::table('users')->where('id', $memberId)->lockForUpdate()->first(['id']);
        if (! $member || ! self::enabled($memberId)) {
            throw new RuntimeException('Withdrawals are OFF for this member. Contact the administrator.');
        }
    }

    public static function set(User $admin, int $memberId, bool $enabled, bool $expected): void
    {
        DB::transaction(function () use ($admin, $memberId, $enabled, $expected): void {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new AuthorizationException('Active administrator required.');
            }
            $member = DB::table('users')->where('id', $memberId)->lockForUpdate()->first();
            if (! $member || $member->role !== 'member' || $member->deleted_at) {
                throw new RuntimeException('Choose an existing member account.');
            }
            $before = self::enabled($memberId);
            if ($before === $enabled) {
                return;
            }
            if ($before !== $expected) {
                throw new RuntimeException('Withdrawal control changed. Refresh this member before saving.');
            }
            DB::table('users')->where('id', $memberId)->update(['updated_at' => now()]);
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'admin_member_withdrawals', 'details' => json_encode(['user_id' => $memberId, 'username' => $member->username, 'before' => $before, 'enabled' => $enabled, 'wallet_amount_changed' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }, 3);
    }
}
