<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MemberAccountAdministration
{
    public static function find(string $identifier): ?object
    {
        $identifier = trim($identifier);
        $rows = DB::table('users')->where('role', 'member')->whereNull('deleted_at')->where(function ($q) use ($identifier): void {
            $q->whereRaw('lower(username)=?', [Str::lower($identifier)])->orWhereRaw('lower(email)=?', [Str::lower($identifier)]);
            if (ctype_digit($identifier)) {
                $q->orWhere('id', (int) $identifier);
            }
        })->limit(2)->get(['id', 'username', 'name', 'email', 'status', 'is_blocked', 'available_balance', 'activation_balance', 'created_at']);
        if ($rows->count() > 1) {
            throw new RuntimeException('More than one member matched. Use the exact User ID.');
        }

        return $rows->first();
    }

    private static function actor(User $admin): void
    {
        $u = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
        if (! $u || ! in_array($u->role, ['admin', 'super_admin'], true) || $u->status !== 'active' || $u->is_blocked || $u->deleted_at) {
            throw new AuthorizationException('Active administrator required.');
        }
    }

    private static function member(int $id): object
    {
        $u = DB::table('users')->where('id', $id)->lockForUpdate()->first();
        if (! $u || $u->role !== 'member' || $u->deleted_at) {
            throw new RuntimeException('Choose an existing member account.');
        }

        return $u;
    }

    private static function audit(int $actor, string $action, array $details): void
    {
        DB::table('member_security_audits')->insert(['actor_id' => $actor, 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private static function revoke(int $id): void
    {
        DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $id)->delete();
        DB::table('sessions')->where('user_id', $id)->delete();
        DB::table('password_reset_otps')->where('user_id', $id)->delete();
        foreach (['member_action_challenges', 'withdrawal_email_challenges'] as $table) {
            DB::table($table)->where('user_id', $id)->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);
        }
    }

    public static function status(User $admin, int $id, string $status, string $expectedStatus): void
    {
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Choose Active or Inactive.');
        }
        DB::transaction(function () use ($admin, $id, $status, $expectedStatus): void {
            self::actor($admin);
            if ($status === 'active') {
                throw new RuntimeException('Use the verified-balance activation form for Active.');
            }
            $u = self::member($id);
            if ($u->status !== $expectedStatus) {
                throw new RuntimeException('Member status changed. Find the member again before saving.');
            }
            if ($status === 'active' && $u->is_blocked) {
                throw new RuntimeException('This member is blocked. Use the separate unblock control first.');
            }
            if ($u->status === $status) {
                return;
            }
            DB::table('users')->where('id', $id)->update(['status' => $status, 'member_login_version' => (int) $u->member_login_version + 1, 'updated_at' => now()]);
            self::revoke($id);
            TeamCounterService::synchronize($id);
            self::audit($admin->id, 'admin_member_status', ['user_id' => $id, 'username' => $u->username, 'before' => $u->status, 'after' => $status, 'wallet_amount_changed' => false]);
        });
    }

    public static function email(User $admin, int $id, string $oldEmail, string $newEmail): void
    {
        $newEmail = Str::lower(trim($newEmail));
        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 255) {
            throw new RuntimeException('Enter a valid new email address.');
        }
        DB::transaction(function () use ($admin, $id, $oldEmail, $newEmail): void {
            self::actor($admin);
            $u = self::member($id);
            if (! hash_equals(Str::lower($u->email), Str::lower(trim($oldEmail)))) {
                throw new RuntimeException('Current email changed. Find the member again.');
            }
            if (DB::table('users')->where('id', '<>', $id)->whereRaw('lower(email)=?', [$newEmail])->exists()) {
                throw new RuntimeException('This email already belongs to another account.');
            }
            if ($u->email === $newEmail) {
                return;
            }
            DB::table('users')->where('id', $id)->update(['email' => $newEmail,  'remember_token' => null, 'member_login_version' => (int) $u->member_login_version + 1, 'updated_at' => now()]);
            self::revoke($id);
            self::audit($admin->id, 'admin_member_email', ['user_id' => $id, 'username' => $u->username, 'before' => $u->email, 'after' => $newEmail, 'email_verification' => 'required_for_future_otp_actions']);
        });
    }
}
