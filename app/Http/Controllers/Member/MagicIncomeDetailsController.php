<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MagicIncomeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class MagicIncomeDetailsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(config('leobot_magic_dashboard.show_member_details', false) === true, 404);
        $member = $request->user();
        abort_unless($member, 401);
        abort_unless(Schema::hasTable('magic_income_entitlements'), 503);

        $summary = MagicIncomeReportService::summary((int) $member->id);
        $history = DB::table('magic_income_entitlements')
            ->where('beneficiary_user_id', (int) $member->id)
            ->orderByDesc('id')->paginate(20);
        return view('member.magic-income-details', compact('summary', 'history'));
    }
}
