<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardQuickLinksPlatformsTest extends TestCase
{
    use RefreshDatabase;

    public static function platforms(): array
    {
        return [['whatsapp'], ['youtube'], ['arattai'], ['pdf']];
    }

    private function account(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'is_blocked' => false]);
    }

    private function anchor(TestResponse $response, string $platform): ?DOMElement
    {
        $response->assertOk();
        $dom = new DOMDocument;
        $dom->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new DOMXPath($dom))->query('//a[@data-quick-link="'.$platform.'"]');
        $this->assertLessThanOrEqual(1, $nodes->length);

        return $nodes->item(0);
    }

    #[DataProvider('platforms')]
    public function test_admin_saved_urls_render_and_update_without_cache_clear(string $platform): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $admin = $this->account('admin');
        $member = $this->account('member');
        DB::table('dashboard_quick_links')->where('platform', 'telegram')->update(['url' => 'https://t.me/lumiflowon', 'enabled' => true]);
        foreach ([1, 2] as $version) {
            $url = 'https://links.example.test/'.$platform.'/v'.$version.($platform === 'pdf' ? '.pdf' : '');
            $this->actingAs($admin)->post(route('admin.quick-links.update'), ['platform' => $platform, 'url' => $url, 'enabled' => 1])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('dashboard_quick_links', ['platform' => $platform, 'url' => $url, 'enabled' => true]);
            $pages = [$this->get(route('admin.quick-links.index')), $this->get(route('admin.dashboard')), $this->actingAs($member)->get(route('member.dashboard'))];
            foreach ($pages as $page) {
                $link = $this->anchor($page, $platform);
                $this->assertNotNull($link);
                $this->assertSame($url, $link->getAttribute('href'));
                $this->assertSame('_blank', $link->getAttribute('target'));
                $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
                $this->assertSame($platform === 'pdf', $link->hasAttribute('download'));
                $this->assertStringContainsString($platform === 'pdf' ? 'Download' : 'Join now', $link->textContent);
                $telegram = $this->anchor($page, 'telegram');
                $this->assertNotNull($telegram);
                $this->assertSame('https://t.me/lumiflowon', $telegram->getAttribute('href'));
            }
        }
        $this->assertDatabaseHas('dashboard_quick_links', ['platform' => 'telegram', 'url' => 'https://t.me/lumiflowon', 'enabled' => true]);
    }

    #[DataProvider('platforms')]
    public function test_disabled_empty_and_unsafe_links_are_not_clickable_on_dashboards(string $platform): void
    {
        config(['leobot_auth.member_pin_required' => false]);
        $admin = $this->account('admin');
        $member = $this->account('member');
        $url = 'https://links.example.test/'.$platform;
        $this->actingAs($admin)->post(route('admin.quick-links.update'), ['platform' => $platform, 'url' => $url, 'enabled' => 0])->assertSessionHasNoErrors();
        $this->assertNotNull($this->anchor($this->get(route('admin.quick-links.index')), $platform));
        $this->assertNull($this->anchor($this->get(route('admin.dashboard')), $platform));
        $this->assertNull($this->anchor($this->actingAs($member)->get(route('member.dashboard')), $platform));
        foreach (['', 'javascript:alert(1)', 'http://links.example.test/unsafe', 'https://user:pass@links.example.test/unsafe'] as $unsafe) {
            DB::table('dashboard_quick_links')->where('platform', $platform)->update(['url' => $unsafe, 'enabled' => true]);
            $this->assertNull($this->anchor($this->get(route('member.dashboard')), $platform));
            $this->actingAs($admin);
            $this->assertNull($this->anchor($this->get(route('admin.quick-links.index')), $platform));
            $this->assertNull($this->anchor($this->get(route('admin.dashboard')), $platform));
            $this->actingAs($member);
        }
    }

    #[DataProvider('platforms')]
    public function test_member_cannot_change_platform_url(string $platform): void
    {
        $url = 'https://links.example.test/original';
        DB::table('dashboard_quick_links')->where('platform', $platform)->update(['url' => $url, 'enabled' => true]);
        $this->actingAs($this->account('member'))->post(route('admin.quick-links.update'), ['platform' => $platform, 'url' => 'https://links.example.test/replaced', 'enabled' => 0])->assertForbidden();
        $this->assertDatabaseHas('dashboard_quick_links', ['platform' => $platform, 'url' => $url, 'enabled' => true]);
    }
}
