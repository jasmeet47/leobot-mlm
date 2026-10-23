<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only member-specific Magic Matrix genealogy; no financial operations. */
final class MagicMatrixTeamReadService
{
    public static function forMember(int $memberId): array
    {
        $result = [
            'available' => false, 'visible_levels' => 0, 'total' => 0,
            'direct' => 0, 'filled_levels' => 0, 'has_position' => false, 'levels' => [],
        ];
        if (!Schema::hasTable('magic_matrix_settings') ||
            !Schema::hasColumn('magic_matrix_settings', 'visible_levels') ||
            !Schema::hasTable('magic_matrix_positions')) return $result;

        $setting = DB::table('magic_matrix_settings')->where('id', 1)->first();
        if (!$setting) return $result;
        $limit = max(0, min(20, (int) $setting->visible_levels));
        $result['visible_levels'] = $limit;
        if ($limit === 0) return $result;

        $root = DB::table('magic_matrix_positions')->where('user_id', $memberId)->first(['id']);
        $result['available'] = true;
        $result['has_position'] = (bool) $root;
        for ($level = 1; $level <= $limit; $level++) $result['levels'][$level] = 0;
        if (!$root) return $result;

        $frontier = [(int) $root->id];
        $seen = [(int) $root->id => true];
        for ($level = 1; $level <= $limit && $frontier; $level++) {
            $next = [];
            foreach (array_chunk($frontier, 500) as $parents) {
                $children = DB::table('magic_matrix_positions')
                    ->whereIn('parent_id', $parents)->get(['id']);
                foreach ($children as $child) {
                    $id = (int) $child->id;
                    if (isset($seen[$id])) continue;
                    $seen[$id] = true;
                    $next[] = $id;
                }
            }
            $result['levels'][$level] = count($next);
            $result['total'] += count($next);
            if ($level === 1) $result['direct'] = count($next);
            if ($next) $result['filled_levels']++;
            $frontier = $next;
        }
        return $result;
    }
}
