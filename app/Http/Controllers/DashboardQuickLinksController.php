<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DashboardQuickLinksController extends Controller
{
    private const PLATFORMS = [
        'telegram', 'whatsapp', 'youtube', 'arattai', 'pdf',
    ];

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

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $links = Schema::hasTable('dashboard_quick_links')
            ? DB::table('dashboard_quick_links')->get()->keyBy('platform')
            : collect();

        return view('admin.dashboard-quick-links', compact('links'));
    }

    public function update(Request $request)
    {
        $this->requireAdmin($request);

        abort_unless(Schema::hasTable('dashboard_quick_links'), 503);

        $data = $request->validate([
            'platform' => ['required', Rule::in(self::PLATFORMS)],
            'url' => ['nullable', 'string', 'max:2048', 'url'],
            'enabled' => ['required', 'boolean'],
        ]);

        $url = trim($data['url'] ?? '');
        $enabled = (bool) $data['enabled'];

        if ($url !== '') {
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');

            $valid = is_array($parts)
                && strtolower($parts['scheme'] ?? '') === 'https'
                && $host !== ''
                && !isset($parts['user'])
                && !isset($parts['pass'])
                && !isset($parts['fragment'])
                && $host !== 'localhost'
                && !str_ends_with($host, '.localhost')
                && !filter_var($host, FILTER_VALIDATE_IP);

            if (!$valid) {
                throw ValidationException::withMessages([
                    'url' => 'Only public HTTPS links are allowed.',
                ]);
            }
        }

        if ($enabled && $url === '') {
            throw ValidationException::withMessages([
                'url' => 'Enter an HTTPS URL before enabling this link.',
            ]);
        }

        $old = DB::transaction(function () use ($data, $url, $enabled) {
            $row = DB::table('dashboard_quick_links')
                ->where('platform', $data['platform'])
                ->lockForUpdate()
                ->first();

            abort_unless($row !== null, 503);

            DB::table('dashboard_quick_links')
                ->where('platform', $data['platform'])
                ->update([
                    'url' => $url === '' ? null : $url,
                    'enabled' => $enabled,
                    'updated_at' => now(),
                ]);

            return $row;
        });

        Log::info('Dashboard Quick Link changed', [
            'admin_id' => $request->user()->id,
            'platform' => $data['platform'],
            'old_enabled' => (bool) $old->enabled,
            'new_enabled' => $enabled,
            'url_changed' => $old->url !== ($url === '' ? null : $url),
        ]);

        return back()->with('success', 'Quick Link settings updated.');
    }
}