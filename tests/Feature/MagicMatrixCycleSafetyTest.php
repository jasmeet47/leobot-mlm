<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicMatrixCycleService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** NEVER run against production, staging, or valuable test data. */
final class MagicMatrixCycleSafetyTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshDatabaseWithLaravelTrait;
    }

    public function refreshDatabase()
    {
        $this->beforeTruncatingDatabase();
        $this->refreshDatabaseWithLaravelTrait();
    }

    protected function beforeTruncatingDatabase(): void
    {
        $connection = DB::connection();
        $cfg = $connection->getConfig();
        $safe = app()->environment('testing')
            && $connection->getDriverName() === 'pgsql'
            && ($cfg['host'] ?? null) === '127.0.0.1'
            && (string) ($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? '') === 'lumiflowon_final_regression_20261024'
            && empty($cfg['url']);
        if ($safe) {
            $identity = DB::selectOne('SELECT current_database() AS database_name, inet_server_port() AS server_port');
            $safe = $identity && $identity->database_name === 'lumiflowon_final_regression_20261024'
                && (int) $identity->server_port === 5433;
        }
        if (!$safe) {
            throw new RuntimeException('SAFETY STOP: local disposable PostgreSQL only.');
        }
        \Tests\Support\FinancialSeedState::captureIfNeeded();
    }

    protected function afterTruncatingDatabase(): void
    {
        \Tests\Support\FinancialSeedState::restoreOrCapture();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('leobot_magic.magic_matrix_enrollment_enabled', false);
        config()->set('leobot_magic.magic_matrix_topup_enabled', false);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function person(string $role = 'member'): User
    {
        $id = DB::table('users')->insertGetId([
            'username' => 'MAG' . strtoupper(Str::random(8)),
            'name' => 'Magic Test',
            'email' => Str::random(18) . '@example.test',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password' => Hash::make('Example123!'),
            'security_pin' => null,
            'status' => 'active', 'role' => $role,
            'activation_balance' => '0.00000000',
            'available_balance' => '0.00000000',
            'total_investment' => '0.00000000',
            'lifetime_income' => '0.00000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::findOrFail($id);
    }

    private function package(User $user, string $amount = '50.00000000'): int
    {
        return DB::table('activation_packages')->insertGetId([
            'user_id' => $user->id,
            'activation_key' => 'MAG-' . strtoupper(Str::random(12)),
            'investment_amount' => $amount, 'status' => 'active',
            'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function unlock(): void
    {
        config()->set('leobot_magic.magic_matrix_enrollment_enabled', true);
        config()->set('leobot_magic.magic_matrix_topup_enabled', true);
        DB::table('magic_income_settings')->where('id', 1)->update(['magic_enabled' => true]);
        DB::table('magic_matrix_settings')->where('id', 1)->update([
            'enrollment_enabled' => true, 'topup_enabled' => true,
            'minimum_tier_usdt' => '49.99999999',
        ]);
    }

    public function test_default_off_protects_enrollment(): void
    {
        $member = $this->person();
        $package = $this->package($member);
        $this->expectException(RuntimeException::class);
        MagicMatrixCycleService::enrollFromActivePackage($member, $package, 'ENROLL-GATE-01');
    }

    public function test_50_tier_qualifies_when_minimum_is_below_50_and_position_never_changes(): void
    {
        $this->unlock();
        $member = $this->person();
        $package = $this->package($member, '50.00000000');
        $cycleId = MagicMatrixCycleService::enrollFromActivePackage($member, $package, 'ENROLL-FIFTY');
        $old = DB::table('magic_matrix_cycles')->find($cycleId);
        $this->assertSame($cycleId, MagicMatrixCycleService::enrollFromActivePackage($member, $package, 'ENROLL-FIFTY'));
        $this->assertSame(1, DB::table('magic_matrix_positions')->where('user_id', $member->id)->count());
        $this->assertSame('150.00000000', (string) $old->earning_cap_usdt);
        DB::table('magic_matrix_cycles')->where('id', $cycleId)->update([
            'status' => 'capped', 'magic_paid_usdt' => '150.00000000',
            'cap_reached_at' => CarbonImmutable::parse('2026-10-16 00:00:00', 'UTC'),
        ]);
        CarbonImmutable::setTestNow('2026-10-23 00:00:00 UTC');
        $admin = $this->person('admin');
        $nextId = MagicMatrixCycleService::recordVerifiedTopup($admin, $member->id, '100.00000000', 'PAY-MAG-001');
        $this->assertSame($nextId, MagicMatrixCycleService::recordVerifiedTopup($admin, $member->id, '100.00000000', 'PAY-MAG-001'));
        $new = DB::table('magic_matrix_cycles')->find($nextId);
        $this->assertSame((int) $old->position_id, (int) $new->position_id);
        $this->assertSame('50.00000000', (string) $new->tier_usdt);
        $this->assertSame('100.00000000', (string) $new->earning_basis_usdt);
        $this->assertSame('300.00000000', (string) $new->earning_cap_usdt);
        $this->assertSame(1, DB::table('magic_matrix_topups')->count());
        $this->assertSame(1, DB::table('company_magic_wallet_ledger')->count());
        $this->assertSame('100.00000000', (string) DB::table('company_magic_wallet')->value('balance'));
        $this->assertSame('50.00000000', (string) DB::table('activation_packages')->find($package)->investment_amount);
    }

    public function test_below_required_amount_rejected_without_side_effects(): void
    {
        $this->unlock();
        $member = $this->person();
        $id = MagicMatrixCycleService::enrollFromActivePackage($member, $this->package($member), 'ENROLL-LOW-01');
        DB::table('magic_matrix_cycles')->where('id', $id)->update([
            'status' => 'capped', 'magic_paid_usdt' => '150.00000000',
            'cap_reached_at' => CarbonImmutable::parse('2026-10-16 00:00:00 UTC'),
        ]);
        CarbonImmutable::setTestNow('2026-10-29 00:00:00 UTC'); // >12 days => 3x (150)
        $admin = $this->person('admin');
        try {
            MagicMatrixCycleService::recordVerifiedTopup($admin, $member->id, '100.00000000', 'PAY-LOW-001');
            $this->fail('Undersized top-up should fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Exact required', $e->getMessage());
        }
        $this->assertSame(0, DB::table('magic_matrix_topups')->count());
        $this->assertSame('0.00000000', (string) DB::table('company_magic_wallet')->value('balance'));
    }

    public function test_non_admin_cannot_record_verified_payment(): void
    {
        $this->unlock();
        $member = $this->person();
        $id = MagicMatrixCycleService::enrollFromActivePackage($member, $this->package($member), 'ENROLL-NOADMIN');
        DB::table('magic_matrix_cycles')->where('id', $id)->update([
            'status' => 'capped', 'cap_reached_at' => now(),
        ]);
        $this->expectException(AuthorizationException::class);
        MagicMatrixCycleService::recordVerifiedTopup($member, $member->id, '50', 'PAY-NOADMIN-01');
    }
    public function test_49_usdt_package_fails_at_50_minimum(): void
    {
        $this->unlock();
        $member = $this->person();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['minimum_tier_usdt' => '50.00000000']);
        $package = $this->package($member, '49.99999999');
        $this->expectException(RuntimeException::class);
        MagicMatrixCycleService::enrollFromActivePackage($member, $package, 'ENROLL-BELOW50');
    }

    public function test_exact_minimum_is_rejected_and_one_unit_above_qualifies(): void
    {
        $this->unlock();
        DB::table('magic_matrix_settings')->where('id', 1)->update(['minimum_tier_usdt' => '50.00000000']);
        $equal = $this->person();
        try {
            MagicMatrixCycleService::enrollFromActivePackage($equal, $this->package($equal, '50.00000000'), 'MAGIC-EXACT-MIN');
            $this->fail('Equal-to-minimum package must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('minimum', $e->getMessage());
        }
        $this->assertSame(0, DB::table('magic_matrix_positions')->where('user_id', $equal->id)->count());
        $above = $this->person();
        $id = MagicMatrixCycleService::enrollFromActivePackage($above, $this->package($above, '50.00000001'), 'MAGIC-ABOVE-MIN');
        $this->assertGreaterThan(0, $id);
    }
    public function test_two_child_positions_and_ancestor_lookup_are_deterministic(): void
    {
        $this->unlock();
        $root = $this->person();
        MagicMatrixCycleService::enrollFromActivePackage($root, $this->package($root), 'MATRIX-ROOT');
        $left = $this->person();
        MagicMatrixCycleService::enrollFromActivePackage($left, $this->package($left), 'MATRIX-LEFT');
        $right = $this->person();
        MagicMatrixCycleService::enrollFromActivePackage($right, $this->package($right), 'MATRIX-RIGHT');
        $leftPos = DB::table('magic_matrix_positions')->where('user_id', $left->id)->first();
        $rightPos = DB::table('magic_matrix_positions')->where('user_id', $right->id)->first();
        $this->assertSame(1, (int) $leftPos->slot);
        $this->assertSame(2, (int) $rightPos->slot);
        $this->assertSame([1 => $root->id], \App\Services\MagicMatrixHierarchyService::ancestorsForMember($left->id));
        $this->assertSame([1 => $root->id], \App\Services\MagicMatrixHierarchyService::ancestorsForMember($right->id));
    }

    public function test_member_wallet_and_trading_wallet_remain_unchanged_by_magic_topup(): void
    {
        $this->unlock();
        $member = $this->person();
        $package = $this->package($member, '60.00000000');
        $id = MagicMatrixCycleService::enrollFromActivePackage($member, $package, 'ENROLL-ISOLATE');
        DB::table('magic_matrix_cycles')->where('id', $id)->update([
            'status' => 'capped', 'magic_paid_usdt' => '180.00000000',
            'cap_reached_at' => CarbonImmutable::parse('2026-10-16 00:00:00 UTC'),
        ]);
        $beforeTrading = (string) DB::table('company_wallet')->where('id', 1)->value('balance');
        $beforeMember = (string) DB::table('users')->where('id', $member->id)->value('available_balance');
        CarbonImmutable::setTestNow('2026-10-29 00:00:00 UTC'); // 13 days => 3x tier
        $this->assertSame('180.00000000', MagicMatrixCycleService::quote($member->id)['required_usdt']);
        MagicMatrixCycleService::recordVerifiedTopup($this->person('admin'), $member->id, '180.00000000', 'PAY-ISOLATE-001');
        $this->assertSame($beforeTrading, (string) DB::table('company_wallet')->where('id', 1)->value('balance'));
        $this->assertSame($beforeMember, (string) DB::table('users')->where('id', $member->id)->value('available_balance'));
        $this->assertSame('60.00000000', (string) DB::table('activation_packages')->find($package)->investment_amount);
        $this->assertSame('180.00000000', (string) DB::table('company_magic_wallet')->where('id', 1)->value('balance'));
    }

    public function test_admin_can_set_magic_tier_cap_and_twenty_rates_only_under_test_gate(): void
    {
        $this->unlock();
        $admin = $this->person('admin');
        $this->expectException(RuntimeException::class);
        \App\Services\MagicMatrixSettingsService::update($admin, '60', 4, 'weekly');
    }

    public function test_admin_rate_changes_are_audited_without_any_member_payout(): void
    {
        $this->unlock();
        $admin = $this->person('admin');
        config()->set('leobot_magic.magic_matrix_settings_write_enabled', true);
        config()->set('leobot_magic.magic_matrix_rates_write_enabled', true);
        \App\Services\MagicMatrixSettingsService::update($admin, '60', 4, 'weekly');
        \App\Services\MagicMatrixSettingsService::updateLevelRates($admin, array_fill_keys(range(1, 20), '5.000000000'));
        $this->assertSame('60.00000000', (string) DB::table('magic_matrix_settings')->where('id', 1)->value('minimum_tier_usdt'));
        $this->assertSame(4, (int) DB::table('magic_matrix_settings')->where('id', 1)->value('earning_cap_multiplier'));
        $this->assertSame(20, DB::table('magic_matrix_level_rates')->count());
        $this->assertSame(2, DB::table('magic_matrix_settings_audits')->count());
        $this->assertSame(0, DB::table('company_magic_wallet_ledger')->count());
    }

}
