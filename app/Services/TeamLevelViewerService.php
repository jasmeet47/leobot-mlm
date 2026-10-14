<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Read-only sponsor-chain viewer. Source is users.sponsor_user_id, not 41-level legacy data. */
final class TeamLevelViewerService
{
    private const MAX_LEVEL = 51;

    private const TEAM_SQL = <<<'SQL'
WITH RECURSIVE team AS (
    SELECT u.id, 1 AS level, ARRAY[CAST(? AS BIGINT), u.id] AS path
    FROM users u WHERE u.sponsor_user_id = ? AND u.id <> ?
    UNION ALL
    SELECT u.id, team.level + 1, team.path || u.id
    FROM users u JOIN team ON u.sponsor_user_id = team.id
    WHERE team.level < 51 AND NOT (u.id = ANY(team.path))
)
SQL;

    /** @return array{counts: array<int,int>, members: LengthAwarePaginator} */
    public static function view(int $rootId, int $level, string $search, int $page = 1): array
    {
        if ($rootId < 1 || $level < 1 || $level > self::MAX_LEVEL || $page < 1) {
            throw new InvalidArgumentException('Invalid root, level, or page.');
        }
        // PostgreSQL is required on the real application and disposable test DB.
        if (DB::getDriverName() !== 'pgsql') {
            throw new InvalidArgumentException('PostgreSQL is required for this read-only viewer.');
        }
        $base = [$rootId, $rootId, $rootId];
        $counts = array_fill(1, self::MAX_LEVEL, 0);
        foreach (DB::select(self::TEAM_SQL . ' SELECT level, COUNT(*) AS total FROM team GROUP BY level', $base) as $row) {
            $counts[(int) $row->level] = (int) $row->total;
        }

        $pattern = '%' . addcslashes($search, '%_\\') . '%';
        $filter = ' FROM team t JOIN users u ON u.id = t.id WHERE t.level = ?';
        $bind = array_merge($base, [$level]);
        if ($search !== '') {
            $filter .= ' AND u.username ILIKE ?';
            $bind[] = $pattern;
        }
        $total = DB::selectOne(self::TEAM_SQL . ' SELECT COUNT(*) AS total' . $filter, $bind);
        $totalCount = (int) $total->total;
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;
        $rows = DB::select(self::TEAM_SQL . ' SELECT u.id, u.username, u.name, u.status, u.is_blocked, u.created_at'
            . $filter . ' ORDER BY u.id DESC LIMIT ? OFFSET ?',
            array_merge($bind, [$pageSize, $offset]));
        $members = new LengthAwarePaginator($rows, $totalCount, $pageSize, $page);
        $members->withPath(request()->url())->appends(request()->query());

        return ['counts' => $counts, 'members' => $members];
    }
}
