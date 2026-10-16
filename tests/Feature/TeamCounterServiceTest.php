<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\TeamCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TeamCounterServiceTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private leobotOriginalRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        $name = config('database.default');
        $cfg = config('database.connections.' . $name, []);

        $safe = app()->environment('testing')
            && $name === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (string) ($cfg['port'] ?? '') === '5433'
            && ($cfg['database'] ?? '') === 'leobot_financial_disposable'
            && empty($cfg['url']);

        if (!$safe) {
            throw new \RuntimeException(
                'SAFETY STOP: Disposable PostgreSQL required.'
            );
        }

        $identity = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT current_database() AS database_name, inet_server_port() AS server_port'
        );

        if (!$identity
            || $identity->database_name !== 'leobot_financial_disposable'
            || (int) $identity->server_port !== 5433) {
            throw new \RuntimeException(
                'SAFETY STOP: Database identity mismatch.'
            );
        }

        $this->leobotOriginalRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Positive-path activation tests only; live configuration remains OFF.
        config()->set('leobot.member_activation_enabled', true);
    }

    private int $sequence = 0;

    private function member(?User $sponsor = null, string $status = 'inactive', string $wallet = '0'): User
    {
        $n = ++$this->sequence;
        $id = DB::table('users')->insertGetId([
            'username' => 'TEAM' . $n,
            'sponsor_id' => $sponsor?->username,
            'sponsor_user_id' => $sponsor?->id,
            'name' => 'Test team user ' . $n,
            'email' => 'team' . $n . '@example.test',
            'phone' => '9000000000',
            'password' => Hash::make('TestPassword123!'),
            'security_pin' => null,
            'status' => $status,
            'activation_balance' => $wallet,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function team(User $upline, int $level = 1): object
    {
        return DB::table('member_teams')
            ->where('user_id', $upline->id)
            ->where('level', $level)
            ->firstOrFail();
    }

    private function assertMoney(string $expected, mixed $actual): void
    {
        $this->assertSame(0, bccomp($expected, (string) $actual, 8));
    }

    public function test_new_registration_and_repeat_sync_do_not_double_count(): void
    {
        $sponsor = $this->member();
        $downline = $this->member($sponsor);

        TeamCounterService::synchronize($downline->id);
        TeamCounterService::synchronize($downline->id);

        $row = $this->team($sponsor);
        $this->assertSame(0, (int) $row->active_count);
        $this->assertSame(1, (int) $row->inactive_count);
        $this->assertMoney('0.00000000', $row->total_business);
        $this->assertSame(1, DB::table('member_team_states')->where('user_id', $downline->id)->count());
    }

    public function test_synchronization_reaches_exactly_51_ancestors(): void
    {
        $chain = [$this->member()];
        for ($level = 1; $level <= 52; $level++) {
            $chain[] = $this->member($chain[$level - 1]);
        }

        $leaf = $chain[52];
        TeamCounterService::synchronize($leaf->id);

        $this->assertSame(1, (int) $this->team($chain[51], 1)->inactive_count);
        $this->assertSame(1, (int) $this->team($chain[1], 51)->inactive_count);
        $this->assertSame(0, DB::table('member_teams')->where('user_id', $chain[0]->id)->count());
        $this->assertSame(51, DB::table('member_teams')->count());
    }

    public function test_registration_endpoint_updates_team_counter_atomically(): void
    {
        $sponsor = $this->member();
        $email = (string) Str::uuid() . '@example.test';
        $this->post('/register', [
            'sponsor_id' => $sponsor->username,
            'name' => 'Registered Member',
            'email' => $email,
            'mobile' => '9000000000',
            'password' => 'TestPassword123!',
            'password_confirmation' => 'TestPassword123!',
        ])->assertSessionHasNoErrors();

        $registered = User::where('email', $email)->firstOrFail();
        $this->assertSame($sponsor->id, $registered->sponsor_user_id);
        $this->assertSame(1, (int) $this->team($sponsor)->inactive_count);
    }

    public function test_first_activation_moves_count_and_adds_eight_decimal_business(): void
    {
        $sponsor = $this->member();
        $child = $this->member($sponsor, 'inactive', '500.00000000');
        TeamCounterService::synchronize($child->id);

        ActivationService::activateSelf($child, '50.12345678', (string) Str::uuid());

        $row = $this->team($sponsor);
        $this->assertSame(1, (int) $row->active_count);
        $this->assertSame(0, (int) $row->inactive_count);
        $this->assertMoney('50.12345678', $row->total_business);
        $this->assertMoney('0', $sponsor->fresh()->lifetime_income);
    }

    public function test_multiple_packages_and_retry_do_not_double_count_member_or_business(): void
    {
        $sponsor = $this->member();
        $child = $this->member($sponsor, 'inactive', '500.00000000');
        TeamCounterService::synchronize($child->id);
        $key = (string) Str::uuid();

        ActivationService::activateSelf($child, '10.12345678', $key);
        ActivationService::activateSelf($child, '10.12345678', $key);
        ActivationService::activateSelf($child, '20.00000001', (string) Str::uuid());
        TeamCounterService::synchronize($child->id);

        $row = $this->team($sponsor);
        $this->assertSame(1, (int) $row->active_count);
        $this->assertSame(0, (int) $row->inactive_count);
        $this->assertMoney('30.12345679', $row->total_business);
        $this->assertSame(2, DB::table('activation_packages')->count());
    }

    public function test_failed_activation_keeps_counters_unchanged(): void
    {
        $sponsor = $this->member();
        $child = $this->member($sponsor, 'inactive', '1');
        TeamCounterService::synchronize($child->id);

        try {
            ActivationService::activateSelf($child, '100', (string) Str::uuid());
            $this->fail('Expected insufficient balance rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('balance', strtolower($exception->getMessage()));
        }

        $row = $this->team($sponsor);
        $this->assertSame(1, (int) $row->inactive_count);
        $this->assertSame(0, (int) $row->active_count);
        $this->assertMoney('0', $row->total_business);
    }

    public function test_closed_package_is_removed_from_business_on_explicit_resync(): void
    {
        $sponsor = $this->member();
        $child = $this->member($sponsor, 'inactive', '500');
        TeamCounterService::synchronize($child->id);
        $packageId = ActivationService::activateSelf($child, '50', (string) Str::uuid());

        DB::table('activation_packages')->where('id', $packageId)->update(['status' => 'closed']);
        TeamCounterService::synchronize($child->id);

        $row = $this->team($sponsor);
        $this->assertSame(1, (int) $row->active_count);
        $this->assertMoney('0', $row->total_business);
    }

    public function test_reparenting_is_rejected_without_corrupting_cached_counts(): void
    {
        $first = $this->member();
        $second = $this->member();
        $child = $this->member($first);
        TeamCounterService::synchronize($child->id);
        DB::table('users')->where('id', $child->id)->update([
            'sponsor_user_id' => $second->id, 'sponsor_id' => $second->username,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sponsor tree change');
        TeamCounterService::synchronize($child->id);
    }
}
