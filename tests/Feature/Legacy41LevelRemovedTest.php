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

    public function test_current_activation_service_remains_available(): void
    {
        $this->assertTrue(class_exists(ActivationService::class));
    }
}
