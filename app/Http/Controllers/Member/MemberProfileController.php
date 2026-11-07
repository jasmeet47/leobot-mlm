<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberPaymentSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MemberProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('member.profile', ['member' => $request->user(), 'profileWallets' => MemberPaymentSecurity::wallets($request->user()->id), 'walletChangesEnabled' => MemberPaymentSecurity::changesEnabled()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed']]);
        $version = DB::transaction(function () use ($request, $data): int {
            $member = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($member->role === 'member' && $member->deleted_at === null && ! $member->is_blocked && $member->status === 'active', 403);
            abort_unless(Hash::check($data['current_password'], $member->password), 422);
            $member->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null,
                'member_login_version' => (int) $member->member_login_version + 1])->save();
            $member->tokens()->delete();

            return (int) $member->member_login_version;
        });
        $request->session()->regenerate(true);
        $request->session()->put('member_login_version', $version);

        return back()->with('success', 'Password changed. Other sessions and API tokens have been revoked.');
    }
}
