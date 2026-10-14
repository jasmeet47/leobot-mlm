<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TeamLevelViewerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberManagementController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->query('search', ''));

        $members = User::query()
            ->select([
                'id',
                'username',
                'name',
                'sponsor_id',
                'sponsor_user_id',
                'status',
                'is_blocked',
                'created_at',
            ])
            ->with('sponsor:id,username')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('username', 'ilike', '%' .
                    addcslashes($search, '%_\\') . '%');
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.members.index', [
            'members' => $members,
            'search' => $search,
        ]);
    }

    public function referrals(Request $request, User $member)
    {
        $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) $request->query('search', ''));

        $referrals = $member->referrals()
            ->select(['id', 'username', 'name', 'status', 'created_at', 'sponsor_user_id'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('username', 'ilike', '%' .
                    addcslashes($search, '%_\\') . '%');
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.members.referrals', [
            'member' => $member,
            'referrals' => $referrals,
            'search' => $search,
        ]);
    }
    public function teamLevels(Request $request, User $member)
    {
        $input = $request->validate([
            'level' => ['sometimes', 'integer', 'between:1,51'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $level = (int) ($input['level'] ?? 1);
        $page = (int) ($input['page'] ?? 1);
        $search = trim((string) ($input['search'] ?? ''));
        $data = TeamLevelViewerService::view($member->id, $level, $search, $page);

        return view('admin.members.team-levels', [
            'member' => $member,
            'level' => $level,
            'search' => $search,
            'counts' => $data['counts'],
            'teamMembers' => $data['members'],
        ]);
    }

    public function show(User $member)
    {
        $member = User::query()
            ->select([
                'id',
                'username',
                'name',
                'sponsor_user_id',
                'status',
                'is_blocked',
                'role',
                'created_at',
            ])
            ->with('sponsor:id,username')
            ->withCount('referrals')
            ->findOrFail($member->id);

        $blockAudits = DB::table('member_account_block_audits as a')
            ->join('users as admins', 'admins.id', '=', 'a.admin_user_id')
            ->where('a.member_user_id', $member->id)
            ->orderByDesc('a.id')->limit(20)
            ->get(['a.action', 'a.previous_status', 'a.new_status', 'a.reason', 'a.created_at', 'admins.username as admin_username']);

        return view('admin.members.show', [
            'member' => $member,
            'blockAudits' => $blockAudits,
        ]);
    }
}
