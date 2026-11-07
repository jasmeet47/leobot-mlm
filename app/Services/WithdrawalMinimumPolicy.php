<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WithdrawalMinimumPolicy
{
    public static function current(): string
    {
        return (string) (DB::table('withdrawal_settings')->where('id', 1)->value('minimum_withdrawal_usdt') ?? '1.00000000');
    }

    public static function assertAmount(string $amount): void
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Enter a valid positive withdrawal amount.');
        }
        $s = DB::table('withdrawal_settings')->where('id', 1)->lockForUpdate()->first();
        if (! $s || bccomp($amount, (string) $s->minimum_withdrawal_usdt, 8) < 0) {
            throw new RuntimeException('Minimum withdrawal is '.($s->minimum_withdrawal_usdt ?? '1').' USDT.');
        }
    }

    public static function update(User $admin, string $amount): void
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $amount) || bccomp($amount, '0', 8) <= 0 || bccomp($amount, '999999999999.99999999', 8) > 0) {
            throw new RuntimeException('Enter a positive minimum withdrawal amount.');
        }
        if (config('leobot.withdrawal_settings_write_enabled', false) !== true) {
            throw new RuntimeException('Withdrawal policy edits are disabled.');
        }
        DB::transaction(function () use ($admin, $amount): void {
            $s = DB::table('withdrawal_settings')->where('id', 1)->lockForUpdate()->first();
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $s || ! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new AuthorizationException('Active administrator required.');
            }DB::table('withdrawal_settings')->where('id', 1)->update(['minimum_withdrawal_usdt' => $amount, 'updated_at' => now()]);
            DB::table('withdrawal_settings_audits')->insert(['admin_user_id' => $admin->id, 'action' => 'minimum_withdrawal_usdt', 'old_value' => $s->minimum_withdrawal_usdt, 'new_value' => $amount, 'created_at' => now()]);
        });
    }
}
