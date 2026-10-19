<?php
namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MemberTeamViewerService
{
    public static function view(int $root, int $level, string $search, int $page): array
    {
        if ($root < 1 || $level < 1 || $level > 51 || $page < 1 || mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Invalid team viewer parameters.');
        }
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql','pgsql'], true)) {
            throw new InvalidArgumentException('Unsupported database driver.');
        }
        // The visited-ID path prevents sponsor cycles. Root is excluded from its own downline.
        if ($driver === 'pgsql') {
            $cte = 'WITH RECURSIVE tree AS (SELECT u.id, 1 AS lvl, ARRAY[CAST(? AS BIGINT), u.id] AS visited FROM users u WHERE u.sponsor_id = (SELECT username FROM users WHERE id = ?) AND u.id <> ? UNION ALL SELECT u.id, t.lvl + 1, t.visited || u.id FROM users u JOIN tree t ON u.sponsor_id = (SELECT username FROM users WHERE id = t.id) WHERE t.lvl < 51 AND NOT (u.id = ANY(t.visited))) ';
        } else {
            $cte = "WITH RECURSIVE tree AS (SELECT u.id, 1 AS lvl, CAST(CONCAT(',', ?, ',', u.id, ',') AS CHAR(4096)) AS visited FROM users u WHERE u.sponsor_id = (SELECT username FROM users WHERE id = ?) AND u.id <> ? UNION ALL SELECT u.id, t.lvl + 1, CONCAT(t.visited, u.id, ',') FROM users u JOIN tree t ON u.sponsor_id = (SELECT username FROM users WHERE id = t.id) WHERE t.lvl < 51 AND LOCATE(CONCAT(',', u.id, ','), t.visited) = 0) ";
        }
        $base = [$root, $root, $root];
        $counts = array_fill(1, 51, 0);
        foreach (DB::select($cte.'SELECT lvl, COUNT(*) AS total FROM tree GROUP BY lvl', $base) as $r) {
            $counts[(int)$r->lvl] = (int)$r->total;
        }
        $filter = ' FROM tree t JOIN users u ON u.id = t.id WHERE t.lvl = ?';
        $bind = array_merge($base, [$level]);
        if ($search !== '') {
            $filter .= $driver === 'pgsql' ? " AND u.username ILIKE ?" : " AND u.username LIKE ?";
            $bind[] = '%'.addcslashes($search, '%_\\').'%';
        }
        $total = (int) DB::selectOne($cte.'SELECT COUNT(*) AS total'.$filter, $bind)->total;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $rows = DB::select($cte.'SELECT u.username, u.name, u.status, u.created_at'.$filter.' ORDER BY u.id DESC LIMIT ? OFFSET ?', array_merge($bind, [$perPage, $offset]));
        $paginator = new LengthAwarePaginator($rows, $total, $perPage, $page);
        $paginator->withPath(request()->url())->appends(request()->query());
        return ['counts'=>$counts, 'members'=>$paginator];
    }
}