<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class MemberBlockController extends Controller
{
    public function block(Request $request, User $member): Response
    {
        return $this->change($request, $member, 'blocked');
    }

    public function unblock(Request $request, User $member): Response
    {
        return $this->change($request, $member, 'unblocked');
    }

    private function change(Request $request, User $member, string $action): Response
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($member, $request, $action, $validated): void {
            $target = DB::table('users')->where('id', $member->id)
                ->lockForUpdate()->first(['id', 'role', 'status', 'is_blocked', 'member_login_version', 'deleted_at']);
            abort_unless($target && (string) $target->role === 'member', 403);
            abort_if((int) $target->id === (int) $request->user()->id, 403);
            abort_if($target->deleted_at !== null, 409, 'Retired member must be restored before access changes.');

            $blocked = in_array($target->is_blocked, [true, 1, '1', 't', 'true'], true);
            if ($action === 'blocked') {
                abort_if($blocked, 409);
                abort_unless(in_array((string) $target->status, ['active', 'inactive'], true), 409);
                $previous = (string) $target->status;
                $next = 'blocked';
            } else {
                abort_unless($blocked, 409);
                $previous = 'blocked';
                // Original active/inactive status is never modified by a block.
                $next = (string) $target->status;
            }

            DB::table('users')->where('id', $target->id)->update([
                'is_blocked' => $action === 'blocked',
                'account_blocked_at' => $action === 'blocked' ? now() : null,
                'member_login_version' => (int) $target->member_login_version + ($action === 'blocked' ? 1 : 0),
                'updated_at' => now(),
            ]);

            if ($action === 'blocked') {
                // Revoke the member's tokens on all devices, not other users'.
                $member->tokens()->delete();
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $target->id)->delete();
                }
            }

            DB::table('member_account_block_audits')->insert([
                'member_user_id' => $target->id,
                'admin_user_id' => $request->user()->id,
                'action' => $action,
                'previous_status' => $previous,
                'new_status' => $next,
                'reason' => trim($validated['reason']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.members.show', $member->id)
            ->with('success', $action === 'blocked'
                ? 'Member account blocked.' : 'Member account unblocked.');
    }
}
