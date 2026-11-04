<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class BrandingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_logo_is_a_raster_image_with_nosniff_and_etag(): void
    {
        $response = $this->get('/lumiflowon-brand-logo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertContains($response->headers->get('Content-Type'), ['image/png', 'image/jpeg', 'image/webp']);
        $this->withHeader('If-None-Match', $response->headers->get('ETag'))->get('/lumiflowon-brand-logo')->assertStatus(304);
    }

    public function test_only_active_administrator_can_upload_and_revisions_are_durable(): void
    {
        $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $this->actingAs($member)->post('/admin/logo-settings', ['logo' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_blocked' => false]);
        $this->actingAs($admin)->post('/admin/logo-settings', ['logo' => UploadedFile::fake()->image('first.png', 100, 100)])->assertSessionHas('logo_status');
        $first = $this->get('/lumiflowon-brand-logo')->assertOk()->getContent();
        $this->post('/admin/logo-settings', ['logo' => UploadedFile::fake()->image('second.png', 120, 120)])->assertSessionHas('logo_status');
        $this->assertNotSame($first, $this->get('/lumiflowon-brand-logo')->getContent());
        $this->assertDatabaseCount('branding_settings', 2);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_svg_and_excessive_dimensions_are_rejected_without_replacing_logo(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_blocked' => false]);
        $this->actingAs($admin)->post('/admin/logo-settings', ['logo' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertSessionHasErrors('logo');
        $this->post('/admin/logo-settings', ['logo' => UploadedFile::fake()->image('wide.png', 2500, 1)])->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('branding_settings', 0);
    }
}
