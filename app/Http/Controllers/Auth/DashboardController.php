<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TeamLevelViewerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getCounters(Request $request)
    {
        /*
         * SECURITY:
         * Only the authenticated member can view
         * their own dashboard information.
         */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        /*
         * Team summary for the logged-in member.
         */
        $teamSummary = DB::table('member_teams')
            ->where('user_id', $user->id)
            ->selectRaw(
                'COALESCE(SUM(active_count), 0) as total_active_team,
                 COALESCE(SUM(inactive_count), 0) as total_inactive_team,
                 COALESCE(SUM(total_business), 0) as total_team_business'
            )
            ->first();

        /*
         * Level-wise team data.
         * Supports the configured levels, including 51 levels.
         */
        $levelWiseData = DB::table('member_teams')
            ->where('user_id', $user->id)
            ->whereBetween('level', [1, 51])
            ->select(
                'level',
                'active_count',
                'inactive_count',
                'total_business'
            )
            ->orderBy('level', 'asc')
            ->get();

        $levelWiseData = $levelWiseData->keyBy('level');
        $statusCounts = TeamLevelViewerService::statusCounts((int) $user->id);
        for ($level = 1; $level <= 51; $level++) {
            $entry = $levelWiseData->get($level) ?? (object) ['level' => $level, 'total_business' => '0.00000000'];
            $entry->active_count = (int) ($statusCounts->get($level)->active_count ?? 0);
            $entry->inactive_count = (int) ($statusCounts->get($level)->inactive_count ?? 0);
            $levelWiseData->put($level, $entry);
        }
        $teamSummary->total_active_team = $levelWiseData->sum('active_count');
        $teamSummary->total_inactive_team = $levelWiseData->sum('inactive_count');
        $levelWiseData = $levelWiseData->values();

        /*
         * Return only the logged-in member's information.
         */
        return response()->json([
            'success' => true,
            'message' => 'Dashboard data loaded successfully.',

            'data' => [
                'user_info' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'status' => $user->status,
                    'available_balance' => $user->available_balance,
                    'activation_balance' => $user->activation_balance,
                    'total_investment' => $user->total_investment,
                    'lifetime_income' => $user->lifetime_income,
                ],

                'overview_counters' => [
                    'total_active_team' => (int) ($teamSummary->total_active_team ?? 0),

                    'total_inactive_team' => (int) ($teamSummary->total_inactive_team ?? 0),

                    'total_team_business' => (string) ($teamSummary->total_team_business ?? '0'),
                ],

                'level_wise_breakdown' => $levelWiseData,
            ],
        ], 200);
    }
}
