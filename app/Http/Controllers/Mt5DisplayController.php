<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class Mt5DisplayController extends Controller
{
    private function enabled(): bool
    {
        return Schema::hasTable('mt5_display_settings')
            && (bool) DB::table('mt5_display_settings')
                ->where('id', 1)
                ->value('enabled');
    }

    private function requireAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user
            && !$user->is_blocked
            && $user->status === 'active'
            && in_array($user->role, ['admin', 'super_admin'], true),
            403
        );
    }

    public function admin(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        return response()->json([
            'enabled' => $this->enabled(),
            'connected' => false,
            'status' => 'Read-only MT5 connector not configured',
            'accounts' => [],
            'positions' => [],
            'last_synced_at' => null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function member(): JsonResponse
    {
        abort_unless($this->enabled(), 404);

        return response()->json([
            'enabled' => true,
            'connected' => false,
            'status' => 'Awaiting verified read-only MT5 connection',
            'accounts' => [],
            'positions' => [],
            'last_synced_at' => null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function toggle(Request $request): RedirectResponse
    {
        $this->requireAdmin($request);

        abort_unless(Schema::hasTable('mt5_display_settings'), 503);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $new = (bool) $data['enabled'];

        $old = DB::transaction(function () use ($new) {
            $row = DB::table('mt5_display_settings')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            abort_unless($row !== null, 503);

            DB::table('mt5_display_settings')
                ->where('id', 1)
                ->update([
                    'enabled' => $new,
                    'updated_at' => now(),
                ]);

            return (bool) $row->enabled;
        });

        Log::info('MT5 display changed', [
            'admin_id' => $request->user()->id,
            'old_enabled' => $old,
            'new_enabled' => $new,
        ]);

        return redirect()->route('admin.dashboard')->with(
            'mt5_status',
            $new
                ? 'MT5 display ON; live connection not configured.'
                : 'MT5 display OFF for members.'
        );
    }
}