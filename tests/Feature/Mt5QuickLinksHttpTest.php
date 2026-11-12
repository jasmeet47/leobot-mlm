<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureMemberPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class Mt5QuickLinksHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $cfg = config('database.connections.pgsql');

        $safe = app()->environment('testing')
            && config('database.default') === 'pgsql'
            && ($cfg['host'] ?? '') === '127.0.0.1'
            && (int) ($cfg['port'] ?? 0) === 5433
            && ($cfg['database'] ?? '') === 'leobot_financial_disposable'
            && empty($cfg['url']);

        if (! $safe) {
            throw new \RuntimeException('SAFETY STOP: Wrong test database.');
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS db, inet_server_port() AS port'
        );

        if (! $identity
            || $identity->db !== 'leobot_financial_disposable'
            || (int) $identity->port !== 5433) {
            throw new \RuntimeException('SAFETY STOP: Database identity mismatch.');
        }

        if (! Schema::hasTable('mt5_display_settings')
            || ! Schema::hasTable('dashboard_quick_links')) {
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
            EnsureMemberPin::class,
            $route->gatherMiddleware()
        );

        // Isolate the controller's OFF-state behavior.
        // The real application still enforces member PIN verification.
        $this->withoutMiddleware(
            EnsureMemberPin::class
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

    private function telegramAnchor(TestResponse $response): ?\DOMElement
    {
        $response->assertOk();
        $dom = new \DOMDocument;
        $dom->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new \DOMXPath($dom))->query('//a[@data-quick-link="telegram"]');
        $this->assertLessThanOrEqual(1, $nodes->length);

        return $nodes->item(0);
    }

    public function test_saved_telegram_link_is_clickable_and_changes_are_visible_without_cache_clear(): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $admin = $this->admin();
        $member = $this->member();
        foreach (['https://t.me/lumiflowon', 'https://t.me/lumiflowon_updates'] as $url) {
            $this->actingAs($admin)->from(route('admin.quick-links.index'))->post(route('admin.quick-links.update'), [
                'platform' => 'telegram', 'url' => $url, 'enabled' => 1,
            ])->assertRedirect(route('admin.quick-links.index'))->assertSessionHas('success', 'Quick Link settings updated.');
            $this->assertDatabaseHas('dashboard_quick_links', ['platform' => 'telegram', 'url' => $url, 'enabled' => true]);
            $adminPage = $this->get(route('admin.quick-links.index'))->assertSee('Quick Link settings updated.');
            $adminDashboard = $this->get(route('admin.dashboard'));
            $memberPage = $this->actingAs($member)->get(route('member.dashboard'));
            foreach ([$adminPage, $adminDashboard, $memberPage] as $page) {
                $anchor = $this->telegramAnchor($page);
                $this->assertNotNull($anchor);
                $this->assertSame($url, $anchor->getAttribute('href'));
                $this->assertSame('_blank', $anchor->getAttribute('target'));
                $this->assertSame('noopener noreferrer', $anchor->getAttribute('rel'));
            }
        }
        $this->get(route('member.dashboard'))->assertDontSee('href="https://t.me/lumiflowon"', false);
    }

    public function test_disabled_telegram_stays_hidden_from_member_but_admin_can_preview_saved_url(): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $this->actingAs($this->admin())->post(route('admin.quick-links.update'), [
            'platform' => 'telegram', 'url' => 'https://t.me/lumiflowon', 'enabled' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($this->telegramAnchor($this->get(route('admin.quick-links.index'))));
        $this->assertNull($this->telegramAnchor($this->get(route('admin.dashboard'))));
        $this->assertNull($this->telegramAnchor($this->actingAs($this->member())->get(route('member.dashboard'))));
    }

    public function test_unsafe_or_empty_legacy_urls_never_become_member_or_admin_anchors(): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $admin = $this->admin();
        $member = $this->member();
        foreach (['', 'javascript:alert(1)', 'http://t.me/lumiflowon', 'https://user:pass@t.me/lumiflowon', 'https://localhost/link', 'https://127.0.0.1/link', 'https://[::1]/link', 'https://t.me/lumiflowon#fragment'] as $url) {
            DB::table('dashboard_quick_links')->where('platform', 'telegram')->update(['url' => $url, 'enabled' => true]);
            $this->assertNull($this->telegramAnchor($this->actingAs($member)->get(route('member.dashboard'))));
            $this->assertNull($this->telegramAnchor($this->actingAs($admin)->get(route('admin.quick-links.index'))));
        }
    }

    public function test_invalid_save_reports_error_and_preserves_existing_telegram_setting(): void
    {
        DB::table('dashboard_quick_links')->where('platform', 'telegram')->update(['url' => 'https://t.me/lumiflowon', 'enabled' => true]);
        $this->actingAs($this->admin())->from(route('admin.quick-links.index'))->post(route('admin.quick-links.update'), [
            'platform' => 'telegram', 'url' => 'http://example.com', 'enabled' => 1,
        ])->assertRedirect(route('admin.quick-links.index'))->assertSessionHasErrors('url');
        $this->assertDatabaseHas('dashboard_quick_links', ['platform' => 'telegram', 'url' => 'https://t.me/lumiflowon', 'enabled' => true]);
    }
}
