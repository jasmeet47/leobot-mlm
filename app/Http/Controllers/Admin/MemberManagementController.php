<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

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
    public function show(User $member)
    {
        $member = User::query()
            ->select([
                'id',
                'username',
                'name',
                'sponsor_user_id',
                'status',
                'created_at',
            ])
            ->with('sponsor:id,username')
            ->withCount('referrals')
            ->findOrFail($member->id);

        return view('admin.members.show', [
            'member' => $member,
        ]);
    }
}
