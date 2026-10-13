<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class MemberManagementController extends Controller
{
    public function index(Request $request)
    {
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
}
