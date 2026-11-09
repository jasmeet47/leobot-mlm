<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberTeamViewerService;
use App\Services\TeamLevelViewerService;
use Illuminate\Http\Request;

final class MemberTeamViewerController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();
        abort_unless($member, 401);
        $input = $request->validate([
            'team_member' => ['sometimes', 'integer', 'min:1'],
            'level' => ['sometimes', 'integer', 'between:1,51'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $level = (int) ($input['level'] ?? 1);
        $search = trim((string) ($input['search'] ?? ''));
        $rootId = (int) ($input['team_member'] ?? $member->id);
        abort_unless(TeamLevelViewerService::containsMember((int) $member->id, $rootId), 403, 'Only your own downline teams are available.');
        $teamOwner = User::query()->whereKey($rootId)->firstOrFail();
        $data = MemberTeamViewerService::view($rootId, $level, $search, (int) ($input['page'] ?? 1));

        return view('member.team-levels', [
            'member' => $member, 'teamOwner' => $teamOwner, 'level' => $level, 'search' => $search,
            'counts' => $data['counts'], 'teamMembers' => $data['members'],
        ]);
    }
}
