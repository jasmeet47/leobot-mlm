<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class MagicSplitPolicy
{
    public const VERSION = 'unified_65_30_3_2';

    public static function version(): string
    {
        if (! Schema::hasColumn('profit_sharing_settings', 'distribution_policy')) {
            return 'unified_67_30_3';
        }

        return (string) (DB::table('profit_sharing_settings')->where('id', 1)->value('distribution_policy') ?? 'unified_67_30_3');
    }

    public static function activate(User $admin): void
    {
        DB::transaction(function () use ($admin): void {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first();
            if (! $actor || ! in_array($actor->role, ['admin', 'super_admin'], true) || $actor->status !== 'active' || $actor->is_blocked || $actor->deleted_at) {
                throw new RuntimeException('Active administrator required.');
            }
            $before = DB::table('profit_sharing_settings')->where('id', 1)->lockForUpdate()->first();
            if (($before->distribution_policy ?? null) === self::VERSION) {
                return;
            }
            DB::table('profit_sharing_settings')->where('id', 1)->update(['self_profit_percent' => '65', 'distribution_policy' => self::VERSION, 'updated_at' => now()]);
            DB::table('generation_pool_settings')->where('id', 1)->update(['trading_profit_pool_percent' => '30', 'updated_at' => now()]);
            DB::table('magic_income_settings')->where('id', 1)->update(['trading_profit_pool_percent' => '5', 'updated_at' => now()]);
            DB::table('member_security_audits')->insert(['actor_id' => $admin->id, 'action' => 'magic_split_policy_activated', 'details' => json_encode(['before' => $before, 'policy' => self::VERSION, 'self' => 65, 'generation' => 30, 'matrix' => 3, 'equal' => 2, 'existing_fundings_changed' => false], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }, 3);
    }

    public static function fundingSnapshot(object $settings, string $self, string $generation, string $magic): array
    {
        if (($settings->distribution_policy ?? null) !== self::VERSION) {
            return [];
        }
        if (bccomp($self, '65', 9) !== 0 || bccomp($generation, '30', 9) !== 0 || bccomp($magic, '5', 9) !== 0) {
            throw new RuntimeException('The approved Matrix/Equal policy requires 65% Self, 30% Generation and 5% Magic.');
        }
        DB::table('magic_matrix_settings')->where('id', 1)->lockForUpdate()->first();
        $rates = DB::table('magic_matrix_level_rates')->orderBy('level')->pluck('pool_percent', 'level')->all();
        if (array_keys($rates) !== range(1, 20)) {
            throw new RuntimeException('All 20 Matrix rate rows are required.');
        }
        $total = '0';
        foreach ($rates as $rate) {
            if (! preg_match('/\A\d+(?:\.\d{1,9})?\z/D', (string) $rate) || bccomp((string) $rate, '100', 9) > 0) {
                throw new RuntimeException('Invalid Matrix rate.');
            }
            $total = bcadd($total, (string) $rate, 9);
        }
        if (bccomp($total, '100', 9) > 0) {
            throw new RuntimeException('Matrix rates exceed the Matrix budget.');
        }
        $members = [];
        foreach (DB::table('magic_matrix_positions')->orderBy('user_id')->limit(1001)->pluck('user_id') as $id) {
            if (MagicQualificationService::inspect((int) $id)['eligible']) {
                $cycle = DB::table('magic_matrix_cycles')->where('user_id', $id)->where('status', 'active')->orderByDesc('id')->first();
                if ($cycle) {
                    $members[(int) $id] = (int) $cycle->id;
                }
            }
        }
        if (DB::table('magic_matrix_positions')->count() > 1000) {
            throw new RuntimeException('Equal-share cohort exceeds the safe snapshot limit.');
        }

        return ['magic_distribution_version' => 'matrix_equal_v2', 'matrix_percent_snapshot' => '3', 'equal_percent_snapshot' => '2', 'matrix_rates_snapshot' => json_encode($rates, JSON_THROW_ON_ERROR), 'equal_members_snapshot' => json_encode($members, JSON_THROW_ON_ERROR)];
    }
}
