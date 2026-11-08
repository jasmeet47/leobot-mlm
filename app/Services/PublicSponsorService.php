<?php

namespace App\Services;

use App\Models\User;

final class PublicSponsorService
{
    public static function resolve(string $identifier, bool $lock = false): ?User
    {
        $query = User::whereRaw('upper(username) = ?', [mb_strtoupper(trim($identifier))])->whereNull('deleted_at')->where('is_blocked', false);
        if (config('leobot.company_referral_enabled', false) === true) {
            $query->where('role', 'member');
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        $rows = $query->limit(2)->get();

        return $rows->count() === 1 ? $rows->first() : null;
    }
}
