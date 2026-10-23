<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberRetirementController extends Controller
{
    public function retire(Request $request, User $member)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500' ],
            'confirm_username' => ['required', 'string'],
        ]);
        abort_unless(hash_equals($member->username, $data['confirm_username']), 422, 'Username confirmation mismatch.');
        DB::transaction(function () use ($request, $member, $data) {
            $target = DB::table('users')->where('id', $member->id)->lockForUpdate()->first();
            abort_unless($target && $target->role === 'member', 403);
            abort_if((int)$target->id === (int)$request->user()->id, 403);
            abort_if($target->deleted_at !== null, 409, 'Already retired.');
            abort_unless(Schema::hasTable('member_retirement_audits') && Schema::hasTable('reserved_member_identities'), 503, 'Retirement migration required.');
            DB::table('reserved_member_identities')->insertOrIgnore([
                'original_user_id' => $target->id, 'username' => $target->username,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            abort_unless(DB::table('reserved_member_identities')->where('original_user_id', $target->id)->where('username', $target->username)->exists(), 409, 'Identity reservation mismatch.');
            DB::table('users')->where('id', $target->id)->update([
                'deleted_at' => now(), 'is_blocked' => true,
                'account_blocked_at' => now(),
                'member_login_version' => (int)$target->member_login_version + 1,
                'updated_at' => now(),
            ]);
            $member->tokens()->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }
            DB::table('member_retirement_audits')->insert([
                'member_user_id' => $target->id, 'member_username' => $target->username,
                'admin_user_id' => $request->user()->id, 'action' => 'soft_delete',
                'reason' => trim($data['reason']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.members.show', $member->id)->with('success', 'Member retired and access revoked.');
    }

    // Explicit binding by ID supports restoring rows marked deleted_at, without a global SoftDeletes scope.
    public function restore(Request $request, User $member)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500' ]]);
        DB::transaction(function () use ($request, $member, $data) {
            $target = DB::table('users')->where('id', $member->id)->lockForUpdate()->first();
            abort_unless($target && $target->role === 'member', 403);
            abort_if($target->deleted_at === null, 409, 'Not retired.');
            DB::table('users')->where('id', $target->id)->update([
                'deleted_at' => null, 'is_blocked' => true, 'account_blocked_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('member_retirement_audits')->insert([
                'member_user_id' => $target->id, 'member_username' => $target->username,
                'admin_user_id' => $request->user()->id, 'action' => 'restore',
                'reason' => trim($data['reason']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.members.show', $member->id)->with('success', 'Member restored but remains blocked until separately unblocked.');
    }

    public function safeDeletePreview(User $member)
    {
        // Fail closed. A complete schema-aware dependency checker and approved retention
        // policy are required before physical deletion is implemented.
        return redirect()->route('admin.members.show', $member->id)
            ->with('success', 'Permanent deletion is disabled pending dependency audit and tests. Identity remains reserved.');
    }
}
