<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StakingService;
use Illuminate\Http\Request;

final class StakingControlController extends Controller
{
    public function update(Request $request, StakingService $service)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        try {
            $service->toggle($request->user(), (bool) $data['enabled']);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => 'Staking approval required or controls unavailable'], 409);
        }

        return response()->json(['enabled' => (bool) $data['enabled']]);
    }
}
