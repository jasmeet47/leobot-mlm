<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Mt5QuickLinksHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $cfg = config('database.connections.pgsql');

        $safe = app()->environment('testing')
            && config('database.default') === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (int) ($cfg['port'] ?? 0) === 5433
            && ($cfg['database'] ?? '') === 'lumiflowon_final_regression_20261024'
            && empty($cfg['url']);

        if (!$safe) {
            throw new \RuntimeException('SAFETY STOP: Wrong test database.');
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, inet_server_port() AS port'
        );

        if (!$identity
            || $identity->db !== 'lumiflowon_final_regression_20261024'
            || (int) $identity->port !== 5433) {
            throw new \RuntimeException('SAFETY STOP: Database identity mismatch.');
        }

        if (!Schema::hasTable('mt5_display_settings')
            || !Schema::hasTable('dashboard_quick_links')) {
            throw new \RuntimeException('SAFETY STOP: Required tables missing.');
        }
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'is_blocked' => false,
        ]);
    }

    private function member(): User
    {
        return User::factory()->create([
            'role' => 'member',
            'status' => 'active',
            'is_blocked' => false,
        ]);
    }

    public function test_guest_cannot_toggle_mt5(): void
    {
        $this->post(route('admin.mt5.toggle'), [
            'enabled' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('mt5_display_settings', [
            'id' => 1,
            'enabled' => false,
        ]);
    }

    public function test_member_cannot_toggle_mt5(): void
    {
        $this->actingAs($this->member())
            ->post(route('admin.mt5.toggle'), ['enabled' => 1])
            ->assertForbidden();
    }

    public function test_admin_can_toggle_mt5_on_and_off(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.mt5.toggle'), ['enabled' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('mt5_display_settings', [
            'id' => 1,
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.mt5.toggle'), ['enabled' => 0])
            ->assertRedirect();

        $this->assertDatabaseHas('mt5_display_settings', [
            'id' => 1,
            'enabled' => false,
        ]);
    }

    public function test_member_mt5_is_hidden_when_off(): void
    {
        DB::table('mt5_display_settings')
            ->where('id', 1)
            ->update(['enabled' => false]);

        // Verify that the member PIN middleware remains registered.
        $route = app('router')->getRoutes()->getByName('member.mt5.view');

        $this->assertNotNull($route);
        $this->assertContains(
            \App\Http\Middleware\EnsureMemberPin::class,
            $route->gatherMiddleware()
        );

        // Isolate the controller's OFF-state behavior.
        // The real application still enforces member PIN verification.
        $this->withoutMiddleware(
            \App\Http\Middleware\EnsureMemberPin::class
        );

        $this->actingAs($this->member())
            ->get(route('member.mt5.view'))
            ->assertNotFound();
    }

    public function test_admin_can_save_https_quick_link(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.quick-links.update'), [
                'platform' => 'telegram',
                'url' => 'https://t.me/lumiflowon',
                'enabled' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_quick_links', [
            'platform' => 'telegram',
            'url' => 'https://t.me/lumiflowon',
            'enabled' => true,
        ]);
    }

    public function test_http_quick_link_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.quick-links.update'), [
                'platform' => 'telegram',
                'url' => 'http://example.com',
                'enabled' => 1,
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_admin_can_disable_quick_link(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.quick-links.update'), [
                'platform' => 'telegram',
                'url' => 'https://t.me/lumiflowon',
                'enabled' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_quick_links', [
            'platform' => 'telegram',
            'enabled' => false,
        ]);
    }

    public function test_member_cannot_update_quick_links(): void
    {
        $this->actingAs($this->member())
            ->post(route('admin.quick-links.update'), [
                'platform' => 'telegram',
                'url' => 'https://example.com',
                'enabled' => 1,
            ])
            ->assertForbidden();
    }
}