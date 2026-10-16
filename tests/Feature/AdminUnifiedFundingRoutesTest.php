<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UnifiedFundingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/** Route and kill-switch tests intentionally do not connect to a database. */
class AdminUnifiedFundingRoutesTest extends TestCase
{
    public function test_admin_funding_routes_require_auth_and_admin_middleware(): void
    {
        foreach (['admin.unified-funding.index', 'admin.unified-funding.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('account.not_blocked', $middleware);
            $this->assertContains('admin', $middleware);
        }
        $this->assertContains('throttle:5,1', Route::getRoutes()->getByName('admin.unified-funding.store')->gatherMiddleware());
    }

    public function test_entry_is_fail_closed_by_default_before_database_access(): void
    {
        config()->set('leobot.admin_unified_funding_ui_enabled', false);
        $this->expectException(NotFoundHttpException::class);
        (new UnifiedFundingController())->store(Request::create('/admin/unified-funding', 'POST'));
    }

    public function test_legacy_generation_only_funding_route_is_not_exposed(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.trading-profit.fund'));
    }
}
