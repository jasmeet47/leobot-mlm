<?php

namespace Tests\Feature;

use App\Services\ActivationService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Legacy41LevelRemovedTest extends TestCase
{
    public function test_legacy_classes_are_not_available(): void
    {
        $this->assertFalse(class_exists('App\\Http\\Controllers\\Auth\\ActivationController'));
        $this->assertFalse(class_exists('App\\Services\\TreeService'));
    }

    public function test_legacy_activation_and_p2p_routes_remain_absent(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('ActivationController', $route->getActionName());
            $this->assertNotContains($route->uri(), [
                'api/activate-user',
                'api/p2p-transfer',
            ]);
        }
    }

    public function test_old_activation_and_p2p_endpoints_return_not_found(): void
    {
        $this->postJson('/api/activate-user', [])->assertNotFound();
        $this->postJson('/api/p2p-transfer', [])->assertNotFound();
    }

    public function test_legacy_roi_command_class_is_absent(): void
    {
        $this->assertFalse(
            class_exists('App\\Console\\Commands\\DistributeDailyROI')
        );
    }

    public function test_legacy_roi_command_is_not_registered(): void
    {
        $this->assertNotContains(
            'roi:distribute',
            array_keys(\Illuminate\Support\Facades\Artisan::all())
        );
    }

    public function test_legacy_commission_seeder_is_absent(): void
    {
        $this->assertFalse(
            class_exists('Database\\Seeders\\CommissionSettingsSeeder')
        );
    }

    public function test_obsolete_preview_removed_and_team_service_remains(): void
    {
        $this->assertFalse(
            class_exists(\App\Services\CommissionService::class)
        );

        $this->assertTrue(
            class_exists(\App\Services\TeamCounterService::class)
        );
    }
    public function test_current_activation_service_remains_available(): void
    {
        $this->assertTrue(class_exists(ActivationService::class));
    }
}
