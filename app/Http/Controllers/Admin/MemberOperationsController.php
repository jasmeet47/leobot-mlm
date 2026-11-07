<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberTransferPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class MemberOperationsController extends Controller
{
    public function transfers(): View
    {
        return view('admin.transfer-settings', ['settings' => MemberTransferPolicy::settings()]);
    }

    public function updateTransfers(Request $r): RedirectResponse
    {
        $d = $r->validate(['enabled' => 'required|boolean', 'minimum_usdt' => 'required|string|max:25', 'maximum_usdt' => 'nullable|string|max:25']);
        try {
            MemberTransferPolicy::update($r->user(), (bool) $d['enabled'], $d['minimum_usdt'], $d['maximum_usdt'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', 'Transfer controls saved successfully.');
    }

    public function deletion(): View
    {
        return view('admin.member-deletion', ['members' => DB::table('users')->where('role', 'member')->whereNull('deleted_at')->orderBy('username')->get(['id', 'username', 'name'])]);
    }

    public function delete(Request $r): RedirectResponse
    {
        $d = $r->validate(['member_id' => 'required|integer|min:1', 'reason' => 'nullable|string|min:10|max:500']);

        return DB::transaction(function () use ($r, $d): RedirectResponse {
            $actor = DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
            abort_unless($actor && in_array($actor->role, ['admin', 'super_admin'], true) && $actor->status === 'active' && ! $actor->is_blocked && ! $actor->deleted_at, 403);
            $member = User::query()->where('role', 'member')->findOrFail($d['member_id']);
            $r->merge(['confirm_username' => $member->username, 'reason' => $d['reason'] ?? 'Member selected by administrator for access deletion.']);
            app(MemberRetirementController::class)->retire($r, $member);

            return redirect()->route('admin.members.deletion')->with('success', 'Selected member ID deleted from access. Financial history is preserved.');
        });
    }
}
