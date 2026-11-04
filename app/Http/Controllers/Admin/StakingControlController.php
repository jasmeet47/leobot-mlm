<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class StakingControlController extends Controller
{
    public function index(): View
    {
        $ready = Schema::hasTable('staking_controls');

        return view('admin.staking', ['ready' => $ready,
            'enabled' => $ready && (bool) DB::table('staking_controls')->orderBy('id')->value('enabled'),
            'canEnable' => config('leobot.staking_enabled') === true && config('leobot.production_financial_approval') === true,
            'positions' => DB::table('staking_positions')->orderByDesc('id')->paginate(30)]);
    }

    public function update(Request $request, StakingService $service)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        try {
            $service->toggle($request->user(), (bool) $data['enabled']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Staking approval required or controls unavailable'], 409)
                : back()->withErrors(['staking' => 'Staking could not be changed. Check the payout policy and financial readiness.']);
        }

        return $request->expectsJson()
            ? response()->json(['enabled' => (bool) $data['enabled']])
            : back()->with('status', $data['enabled'] ? 'Staking is ON.' : 'Staking is OFF. New stakes are disabled.');
    }
}
