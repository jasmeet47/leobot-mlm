<?php
namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\MemberTeamViewerService;
use Illuminate\Http\Request;

final class MemberTeamViewerController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();
        abort_unless($member, 401);
        $input = $request->validate([
            'level' => ['sometimes','integer','between:1,51'],
            'page' => ['sometimes','integer','min:1','max:100000'],
            'search' => ['sometimes','nullable','string','max:100'],
        ]);
        $level = (int)($input['level'] ?? 1);
        $search = trim((string)($input['search'] ?? ''));
        // Never accept a member/root ID from the URL or query string.
        $data = MemberTeamViewerService::view((int)$member->id, $level, $search, (int)($input['page'] ?? 1));
        return view('member.team-levels', [
            'member'=>$member, 'level'=>$level, 'search'=>$search,
            'counts'=>$data['counts'], 'teamMembers'=>$data['members'],
        ]);
    }
}