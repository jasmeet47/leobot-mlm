<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberDashboardController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();
        abort_unless($member !== null, 401);

        // Scope ALL team information to the authenticated member's ID.
        $teamSummary = DB::table('member_teams')
            ->where('user_id', $member->id)
            ->selectRaw('COALESCE(SUM(active_count), 0) AS total_active_team,
                COALESCE(SUM(inactive_count), 0) AS total_inactive_team,
                COALESCE(SUM(total_business), 0) AS total_team_business')
            ->first();

        $levelRows = DB::table('member_teams')
            ->where('user_id', $member->id)
            ->whereBetween('level', [1, 51])
            ->select('level', 'active_count', 'inactive_count', 'total_business')
            ->orderBy('level')
            ->get()
            ->keyBy('level');

        return view('member.dashboard', [
            'member' => $member,
            'teamSummary' => $teamSummary,
            'levelRows' => $levelRows,
        ]);
    }
}
