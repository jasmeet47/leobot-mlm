<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberSafeDeletionService;
use Illuminate\Http\Request;

final class MemberSafeDeleteController extends Controller
{
    public function destroy(Request $request, User $member, MemberSafeDeletionService $service)
    {
        // Explicitly disabled unless approved after database regression testing.
        abort_unless(config('leobot_safe_delete.enabled', false) === true, 403, 'Safe delete is not approved.');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:500'],
            'confirm_username' => ['required', 'string'],
        ]);
        $service->delete((int) $member->id, (int) $request->user()->id,
            trim($data['reason']), $data['confirm_username']);
        return redirect()->route('admin.members.index')->with('success', 'Unused member deleted; identity permanently reserved.');
    }
}
