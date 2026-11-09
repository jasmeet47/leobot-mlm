<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'Release notice', 'heading' => 'Welcome', 'content' => '<p>Hello <strong>members</strong></p>', 'type' => 'text', 'position' => 'center', 'audience' => 'all', 'pages' => ['home'], 'status' => 'draft', 'priority' => 'normal', 'frequency' => 'session', 'delay_seconds' => 0], $overrides);
    }

    private function create(array $overrides = []): int
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.save'), $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect(route('admin.announcements.index'));

        return DB::table('announcements')->max('id');
    }

    public function test_create_edit_refresh_persistence_and_audit(): void
    {
        $id = $this->create();
        $this->assertDatabaseHas('announcements', ['id' => $id, 'title' => 'Release notice', 'revision' => 1]);
        $this->post(route('admin.announcements.save', $id), $this->payload(['title' => 'Edited notice', 'revision' => 1]))->assertSessionHas('success');
        $this->get(route('admin.announcements.index', ['edit' => $id]))->assertOk()->assertSee('Edited notice')->assertSee('Popup & Announcement Manager', false);
        $this->assertDatabaseCount('announcement_audits', 2);
        $this->assertDatabaseHas('announcements', ['id' => $id, 'revision' => 2]);
    }

    public function test_guest_member_and_blocked_admin_cannot_manage(): void
    {
        $this->get(route('admin.announcements.index'))->assertRedirect('/login');
        foreach ([['role' => 'member', 'status' => 'active'], ['role' => 'admin', 'status' => 'active', 'is_blocked' => true], ['role' => 'admin', 'status' => 'inactive']] as $values) {
            $user = User::factory()->create($values);
            $r = $this->actingAs($user)->post(route('admin.announcements.save'), $this->payload());
            $this->assertContains($r->status(), [302, 403]);
        }
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_drafts_and_private_audiences_not_leaked(): void
    {
        $this->create(['title' => 'SECRET_DRAFT']);
        $this->create(['title' => 'ADMIN_ONLY', 'audience' => 'admins', 'status' => 'published']);
        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->get('/')->assertDontSee('SECRET_DRAFT')->assertDontSee('ADMIN_ONLY');
    }

    public function test_published_photo_is_reencoded_and_appears_with_safe_content(): void
    {
        $id = $this->create(['type' => 'combined', 'status' => 'published', 'image' => UploadedFile::fake()->image('photo.png', 800, 500), 'image_alt' => 'Platform notice', 'content' => '<script>alert(1)</script><p onclick="evil()">Safe <a href="javascript:evil()">link</a></p>']);
        $a = DB::table('announcements')->find($id);
        $this->assertSame('image/webp', $a->image_mime);
        $this->assertStringNotContainsString('script', $a->content);
        $this->assertStringNotContainsString('javascript', $a->content);
        $this->assertStringNotContainsString('onclick', $a->content);
        $this->assertNotEmpty($a->image_base64);
        $this->get('/')->assertOk()->assertSee('Platform notice');
    }

    public function test_invalid_upload_url_schedule_and_missing_photo_do_not_save(): void
    {
        foreach ([
            ['type' => 'photo'], ['button_text' => 'Open', 'button_url' => 'javascript:alert(1)'], ['starts_at' => '2027-01-03T12:00', 'ends_at' => '2027-01-01T12:00'], ['image' => UploadedFile::fake()->createWithContent('bad.php', '<?php echo 1;')], ['audience' => 'group'], ['status' => 'published', 'ends_at' => '2020-01-01T12:00'], ['pages' => ['member.withdrawals']],
        ] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.announcements.save'), $this->payload($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('announcement_audits', 0);
    }

    public function test_pause_resume_schedule_expiry_and_financial_page_exclusion(): void
    {
        $id = $this->create(['status' => 'published', 'title' => 'VISIBILITY_MARKER']);
        $this->get('/')->assertSee('VISIBILITY_MARKER');
        $this->post(route('admin.announcements.action', $id), ['action' => 'pause', 'revision' => 1])->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('VISIBILITY_MARKER');
        $this->post(route('admin.announcements.action', $id), ['action' => 'resume', 'revision' => 2])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('VISIBILITY_MARKER');
        DB::table('announcements')->where('id', $id)->update(['starts_at' => now()->addDay()]);
        $this->get('/')->assertDontSee('VISIBILITY_MARKER');
        DB::table('announcements')->where('id', $id)->update(['starts_at' => null, 'ends_at' => now()->subSecond()]);
        $this->get('/')->assertDontSee('VISIBILITY_MARKER');
        DB::table('announcements')->where('id', $id)->update(['ends_at' => null, 'pages' => json_encode(['public', 'admin_dashboard'])]);
        $this->get('/admin/withdrawals')->assertOk()->assertDontSee('VISIBILITY_MARKER');
    }

    public function test_server_audience_groups_and_selected_pages(): void
    {
        $id = $this->create(['audience' => 'group', 'member_group' => 'active', 'status' => 'published', 'title' => 'GROUP_MARKER']);
        $this->get('/')->assertDontSee('GROUP_MARKER');
        $active = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $inactive = User::factory()->create(['role' => 'member', 'status' => 'inactive']);
        $this->actingAs($active)->get('/')->assertSee('GROUP_MARKER');
        $this->actingAs($inactive)->get('/')->assertDontSee('GROUP_MARKER');
        DB::table('announcements')->where('id', $id)->update(['audience' => 'all', 'pages' => json_encode(['registration'])]);
        $this->get('/')->assertDontSee('GROUP_MARKER');
        $this->get('/join')->assertSee('GROUP_MARKER');
    }

    public function test_real_event_dedup_frequency_and_forgery_rejection(): void
    {
        $id = $this->create(['status' => 'published', 'frequency' => 'once']);
        $response = $this->get('/');
        $this->assertDatabaseCount('announcement_events', 0);
        $r = Request::create('/');
        $r->setLaravelSession($this->app['session']->driver());
        $r->setUserResolver(fn () => $this->admin);
        $a = AnnouncementService::forPage($r)[0];
        $event = array_intersect_key($a, array_flip(['revision', 'page', 'window', 'token'])) + ['event' => 'view'];
        $this->postJson(route('announcements.event', $id), array_replace($event, ['token' => str_repeat('0', 64)]))->assertForbidden();
        $this->postJson(route('announcements.event', $id), $event)->assertOk();
        $this->postJson(route('announcements.event', $id), $event)->assertOk();
        $this->assertDatabaseCount('announcement_events', 1);
        $this->get('/')->assertDontSee('Release notice');
        $this->postJson(route('announcements.event', $id), array_replace($event, ['event' => 'click']))->assertOk();
        $this->postJson(route('announcements.event', $id), array_replace($event, ['event' => 'click']))->assertOk();
        $this->assertSame(1, DB::table('announcement_events')->whereNotNull('clicked_at')->count());
        DB::table('announcements')->where('id', $id)->update(['status' => 'paused']);
        $this->postJson(route('announcements.event', $id), $event)->assertNotFound();
    }

    public function test_stale_edit_fails_and_delete_cleans_image_and_events_but_retains_audit(): void
    {
        $id = $this->create(['image' => UploadedFile::fake()->image('a.jpg'), 'image_alt' => 'Photo']);
        $this->from(route('admin.announcements.index'))->post(route('admin.announcements.save', $id), $this->payload(['revision' => 0, 'image_alt' => 'Photo']))->assertStatus(409)->assertSee('data-lf-toast="error"', false);
        $this->assertDatabaseHas('announcements', ['id' => $id, 'revision' => 1]);
        $this->post(route('admin.announcements.action', $id), ['action' => 'delete', 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('announcements', ['id' => $id]);
        $this->assertDatabaseHas('announcement_audits', ['announcement_id' => $id, 'action' => 'deleted']);
    }

    public function test_upload_limit_setting_and_after_login_filter(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.settings'), ['upload_limit_kb' => 128])->assertSessionHas('success');
        $this->assertDatabaseHas('announcement_settings', ['id' => 1, 'upload_limit_kb' => 128]);
        $this->create(['frequency' => 'after_login', 'status' => 'published', 'pages' => ['home', 'admin_dashboard'], 'title' => 'LOGIN_ONLY_MARKER']);
        $this->get('/')->assertDontSee('LOGIN_ONLY_MARKER');
        $this->get('/admin/dashboard')->assertSee('LOGIN_ONLY_MARKER');
    }

    public function test_sanitizer_keeps_safe_formatting_and_rejects_active_markup(): void
    {
        $safe = AnnouncementService::sanitize('<p style="text-align:center;color:#aabbcc;background:url(javascript:x)"><b>Bold</b><font color="#112233">Color</font><a href="/join">Join</a><svg onload="x()"/><iframe src="x"></iframe></p>');
        $this->assertStringContainsString('text-align:center', $safe);
        $this->assertStringContainsString('color:#112233', $safe);
        $this->assertStringContainsString('href="/join"', $safe);
        $this->assertStringNotContainsString('javascript', $safe);
        $this->assertStringNotContainsString('iframe', $safe);
        $this->assertStringNotContainsString('svg', $safe);
    }

    public function test_daily_frequency_resets_next_day_and_urgent_order_wins(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $this->create(['status' => 'published', 'priority' => 'normal']);
        $id = $this->create(['status' => 'published', 'priority' => 'urgent', 'frequency' => 'daily']);
        $r = Request::create('/');
        $r->setLaravelSession($this->app['session']->driver());
        $r->setUserResolver(fn () => $this->admin);
        $items = AnnouncementService::forPage($r);
        $this->assertSame($id, $items[0]['id']);
        $event = array_intersect_key($items[0], array_flip(['revision', 'page', 'window', 'token'])) + ['event' => 'view'];
        $this->postJson(route('announcements.event', $id), $event)->assertOk();
        $this->assertNotContains($id, array_column(AnnouncementService::forPage($r), 'id'));
        $this->travel(1)->days();
        $this->assertContains($id, array_column(AnnouncementService::forPage($r), 'id'));
        $this->travelBack();
    }

    public function test_announcement_migration_is_reversible_without_touching_users(): void
    {
        $before = DB::table('users')->count();
        $migration = require database_path('migrations/2026_11_05_071933_create_announcements.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('announcements'));
        $this->assertSame($before, DB::table('users')->count());
        $migration->up();
        $this->assertTrue(Schema::hasTable('announcements'));
    }

    public function test_invalid_field_shapes_return_to_an_usable_form_without_a_server_error(): void
    {
        $this->actingAs($this->admin)->from(route('admin.announcements.index'))
            ->post(route('admin.announcements.save'), $this->payload([
                'title' => ['invalid'], 'content' => ['invalid'], 'pages' => 'home',
                'starts_at' => ['invalid'], 'ends_at' => ['invalid'],
            ]))->assertRedirect(route('admin.announcements.index'))->assertSessionHasErrors(['title', 'content', 'pages', 'starts_at']);
        $this->withCookie(config('session.cookie').'_admin', session()->getId())
            ->get(route('admin.announcements.index'))->assertOk()
            ->assertSee('data-lf-toast="error"', false)->assertDontSee('data-lf-toast="success"', false);
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_edit_form_keeps_saved_ist_schedule_after_validation_failure(): void
    {
        $id = $this->create(['starts_at' => '2027-01-05T12:00', 'ends_at' => '2027-01-06T12:00']);
        $url = route('admin.announcements.index', ['edit' => $id]);
        $this->get($url)->assertOk()->assertSee('value="2027-01-05T12:00"', false)->assertSee('value="2027-01-06T12:00"', false);
        $this->from($url)->post(route('admin.announcements.save', $id), $this->payload(['title' => ['invalid'], 'revision' => 1]))->assertSessionHasErrors('title');
        $this->withCookie(config('session.cookie').'_admin', session()->getId())->get($url)->assertOk()->assertSee('value="2027-01-05T12:00"', false);
        $this->assertDatabaseHas('announcements', ['id' => $id, 'revision' => 1]);
    }
}
