<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MemberTransferPolicy
{
    public static function settings(): ?object
    {
        return DB::table('member_transfer_settings')->where('id', 1)->first();
    }

    public static function assertAmount(string $amount): void
    {
        $s = DB::table('member_transfer_settings')->where('id', 1)->lockForUpdate()->first();
        if (! $s || ! in_array($s->enabled, [true, 1, '1', 't', 'true'], true)) {
            throw new RuntimeException('Internal transfers are OFF in admin settings.');
        }
        if (bccomp($amount, (string) $s->minimum_usdt, 8) < 0 || ($s->maximum_usdt !== null && bccomp($amount, (string) $s->maximum_usdt, 8) > 0)) {
            throw new RuntimeException('Amount is outside the administrator-set transfer limits.');
        }
    }

    public static function update(User $admin, bool $enabled, string $minimum, ?string $maximum): void
    {
        foreach (array_filter([$minimum, $maximum], fn ($x) => $x !== null) as $value) {
            if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/D', $value) || bccomp($value, '0', 8) <= 0 || bccomp($value, '999999999999.99999999', 8) > 0) {
                throw new RuntimeException('Enter valid positive transfer limits.');
            }
        }
        if ($maximum !== null && bccomp($maximum, $minimum, 8) < 0) {
            throw new RuntimeException('Maximum must be at least the minimum.');
        }
        DB::transaction(function () use ($admin, $enabled, $minimum, $maximum): void {
            $before = DB::table('member_transfer_settings')->where('id', 1)->lockForUpdate()->first();
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $before || ! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            DB::table('member_transfer_settings')->where('id', 1)->update(['enabled' => $enabled, 'minimum_usdt' => $minimum, 'maximum_usdt' => $maximum, 'updated_at' => now()]);
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'transfer_policy', 'details' => json_encode(['before' => $before, 'after' => self::settings()], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }
}
