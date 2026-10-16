<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Staged admin policy settings. Does not enable global Magic or distribute funds. */
final class MagicMatrixSettingsService
{
    public static function update(User $admin, string $minimumUsdt, int $earningMultiplier, string $frequency): void
    {
        self::requireWriteApproval();
        if (!preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/D', $minimumUsdt)
            || bccomp($minimumUsdt, '50', 8) < 0
            || bccomp($minimumUsdt, '999999999999.99999999', 8) > 0
            || !in_array($earningMultiplier, [3, 4, 5], true)
            || !in_array($frequency, ['manual', 'daily', 'weekly', 'monthly'], true)) {
            throw new InvalidArgumentException('Invalid Magic policy.');
        }

        DB::transaction(function () use ($admin, $minimumUsdt, $earningMultiplier, $frequency): void {
            self::assertAdmin($admin);
            $setting = DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
            if (!$setting) {
                throw new RuntimeException('Matrix settings missing.');
            }
            $before = json_encode([
                'minimum_tier_usdt' => (string) $setting->minimum_tier_usdt,
                'earning_cap_multiplier' => (int) $setting->earning_cap_multiplier,
                'frequency' => (string) $setting->frequency,
            ], JSON_THROW_ON_ERROR);
            $after = json_encode([
                'minimum_tier_usdt' => bcadd($minimumUsdt, '0', 8),
                'earning_cap_multiplier' => $earningMultiplier,
                'frequency' => $frequency,
            ], JSON_THROW_ON_ERROR);
            DB::table('magic_matrix_settings')->where('id', 1)->update([
                'minimum_tier_usdt' => bcadd($minimumUsdt, '0', 8),
                'earning_cap_multiplier' => $earningMultiplier,
                'frequency' => $frequency,
                'updated_by' => $admin->id,
                'updated_at' => now(),
            ]);
            DB::table('magic_matrix_settings_audits')->insert([
                'admin_user_id' => $admin->id,
                'action' => 'policy_update', 'before_json' => $before,
                'after_json' => $after, 'created_at' => now(),
            ]);
        }, 3);
    }

    /** All 20 levels explicitly supplied; zero until rates are approved. Must sum to 100%. */
    public static function updateLevelRates(User $admin, array $rates): void
    {
        self::requireWriteApproval();
        if (count($rates) !== 20 || array_keys($rates) !== range(1, 20)) {
            throw new InvalidArgumentException('All 20 matrix levels are required, numbered 1 through 20.');
        }
        $total = '0.000000000';
        foreach ($rates as $rate) {
            if (!is_string($rate) || !preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,9})?\z/D', $rate)
                || bccomp($rate, '100', 9) > 0) {
                throw new InvalidArgumentException('Invalid matrix level percent.');
            }
            $total = bcadd($total, $rate, 9);
        }
        if (bccomp($total, '100', 9) !== 0) {
            throw new InvalidArgumentException('All 20 levels must allocate exactly 100% of Magic level pool.');
        }

        DB::transaction(function () use ($admin, $rates): void {
            self::assertAdmin($admin);
            $settings = DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
            if (!$settings) {
                throw new RuntimeException('Matrix settings missing.');
            }
            $prior = DB::table('magic_matrix_level_rates')->orderBy('level')->get();
            if ($prior->count() !== 20) {
                throw new RuntimeException('20-level rate rows are missing.');
            }
            $before = [];
            foreach ($prior as $row) {
                $before[(int) $row->level] = (string) $row->pool_percent;
            }
            foreach ($rates as $level => $percent) {
                DB::table('magic_matrix_level_rates')->where('level', $level)->update([
                    'pool_percent' => $percent, 'updated_at' => now(),
                ]);
            }
            DB::table('magic_matrix_settings_audits')->insert([
                'admin_user_id' => $admin->id, 'action' => 'level_rates_update',
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($rates, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }, 3);
    }

    private static function requireWriteApproval(): void
    {
        if (config('leobot_magic.magic_matrix_settings_write_enabled', false) !== true ||
            (app()->environment('production') && config('leobot_magic.production_financial_approval', false) !== true)) {
            throw new RuntimeException('Magic Matrix settings write disabled pending approval.');
        }
    }

    private static function assertAdmin(User $admin): void
    {
        if (!$admin->exists) {
            throw new AuthorizationException('Persisted admin required.');
        }
        $user = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
        if (!$user || !in_array((string) $user->role, ['admin', 'super_admin'], true)
            || $user->status !== 'active'
            || in_array(strtolower((string) $user->is_blocked), ['1', 't', 'true'], true)) {
            throw new AuthorizationException('Active unblocked admin required.');
        }
    }
}
