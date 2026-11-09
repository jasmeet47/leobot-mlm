<?php

namespace App\Services;

final class MemberTeamViewerService
{
    public static function view(int $root, int $level, string $search, int $page): array
    {
        return TeamLevelViewerService::view($root, $level, $search, $page);
    }
}
