<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SupportMessageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['message' => 'required|string|min:5|max:4000']);
        DB::transaction(function () use ($request, $data): void {
            $member = DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($member && $member->role === 'member' && ! $member->is_blocked && ! $member->deleted_at, 403);
            DB::table('support_messages')->insert(['user_id' => $member->id, 'message' => $data['message'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return redirect()->route('member.detail', ['panel' => 'member-support'])->with('support_status', 'Your support message was sent successfully.');
    }

    public function index(): View
    {
        return view('admin.support', ['messages' => DB::table('support_messages as s')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'u.username', 'u.name', 'u.email')->orderByDesc('s.id')->paginate(25)]);
    }
}
