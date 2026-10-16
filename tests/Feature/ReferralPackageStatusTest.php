<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralPackageStatusTest extends TestCase
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

    private function member(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 'active',
            'role' => 'member',
            'security_pin' => Hash::make('123456'),
        ], $attributes));
    }

    private function showPackages(bool $enabled): void
    {
        DB::table('referral_privacy_settings')
            ->where('id', 1)
            ->update([
                'show_package_status' => $enabled,
            ]);
    }

    private function package(User $member, string $status): void
    {
        DB::table('activation_packages')->insert([
            'user_id' => $member->id,
            'activation_key' => (string) Str::uuid(),
            'investment_amount' => '100.00000000',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertPackageStatus(
        User $sponsor,
        User $referral,
        string $expected
    ): void {
        $response = $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk();

        $referrals = $response->viewData('referrals');

        $displayed = $referrals->getCollection()
            ->firstWhere('id', $referral->id);

        $this->assertNotNull($displayed);

        $this->assertSame(
            $expected,
            $displayed->display_package_status
        );
    }

    public function test_no_package_displays_no_package(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'No Package'
        );
    }

    public function test_active_package_displays_active(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'active');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Active'
        );
    }

    public function test_pending_package_displays_pending(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'pending');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Pending'
        );
    }

    public function test_closed_package_displays_closed(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'closed');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Closed'
        );
    }

    public function test_active_has_priority_over_other_packages(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'closed');
        $this->package($referral, 'pending');
        $this->package($referral, 'active');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Active'
        );
    }

    public function test_pending_has_priority_over_closed(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'closed');
        $this->package($referral, 'pending');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Pending'
        );
    }

    public function test_privacy_off_hides_active_package(): void
    {
        $this->showPackages(false);

        $sponsor = $this->member();

        $referral = $this->member([
            'sponsor_user_id' => $sponsor->id,
        ]);

        $this->package($referral, 'active');

        $this->assertPackageStatus(
            $sponsor,
            $referral,
            'Hidden'
        );
    }

    public function test_batch_query_fetches_all_visible_referrals_once(): void
    {
        $this->showPackages(true);

        $sponsor = $this->member();

        for ($i = 0; $i < 5; $i++) {
            $referral = $this->member([
                'sponsor_user_id' => $sponsor->id,
            ]);

            $this->package($referral, 'active');
        }

        $packageQueries = 0;

        DB::listen(function ($query) use (&$packageQueries) {
            if (str_contains(
                strtolower($query->sql),
                'activation_packages'
            )) {
                $packageQueries++;
            }
        });

        $this->actingAs($sponsor)
            ->get(route('member.dashboard'))
            ->assertOk();

        $this->assertSame(1, $packageQueries);
    }
}
