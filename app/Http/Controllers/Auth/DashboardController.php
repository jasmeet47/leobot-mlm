<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getCounters(Request $request)
    {
        // डेटाबेस में मौजूद सबसे पहला एक्टिव यूज़र ढूंढें
        $user = User::first();

        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'डेटाबेस में कोई यूजर नहीं मिला।'
            ], 404);
        }

        // 'member_teams' टेबल से 1 से 41 लेवल तक की पूरी टीम का समरी डेटा (जोड़) निकालना
        $teamSummary = DB::table('member_teams')
            ->where('user_id', $user->id)
            ->select(
                DB::raw('SUM(active_count) as total_active_team'),
                DB::raw('SUM(inactive_count) as total_inactive_team'),
                DB::raw('SUM(total_business) as total_team_business')
            )
            ->first();

        // 1 से 41 लेवल का अलग-अलग (Level-wise) ब्रेकडाउन डेटा प्राप्त करना
        $levelWiseData = DB::table('member_teams')
            ->where('user_id', $user->id)
            ->select('level', 'active_count', 'inactive_count', 'total_business')
            ->orderBy('level', 'asc')
            ->get();

        // एक ही साफ़-सुथरे रिस्पॉन्स में पूरा डैशबोर्ड काउंटर डेटा भेजना
        return response()->json([
            'success' => true,
            'message' => 'डेटाबेस से डैशबोर्ड काउंटर्स सफलतापूर्वक मिल गए हैं!',
            'data' => [
                'user_info' => [
                    'username'           => $user->username,
                    'name'               => $user->name,
                    'status'             => $user->status,
                    'available_balance'  => $user->available_balance,
                    'activation_balance' => $user->activation_balance,
                    'total_investment'   => $user->total_investment,
                    'lifetime_income'    => $user->lifetime_income
                ],
                'overview_counters' => [
                    'total_active_team'   => (int) ($teamSummary->total_active_team ?? 0),
                    'total_inactive_team' => (int) ($teamSummary->total_inactive_team ?? 0),
                    'total_team_business' => (float) ($teamSummary->total_team_business ?? 0.0000),
                ],
                'level_wise_breakdown' => $levelWiseData
            ]
               ], 200, [], JSON_UNESCAPED_UNICODE);

    }
}
