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
            'placement' => null, 'members' => [],
        ];
        if (! Schema::hasTable('magic_matrix_settings') ||
            ! Schema::hasColumn('magic_matrix_settings', 'visible_levels') ||
            ! Schema::hasTable('magic_matrix_positions')) {
            return $result;
        }

        $setting = DB::table('magic_matrix_settings')->where('id', 1)->first();
        if (! $setting) {
            return $result;
        }
        $limit = max(0, min(20, (int) $setting->visible_levels));
        $result['visible_levels'] = $limit;
        if ($limit === 0) {
            return $result;
        }

        $root = DB::table('magic_matrix_positions as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('magic_matrix_positions as parent', 'parent.id', '=', 'p.parent_id')
            ->leftJoin('users as parent_user', 'parent_user.id', '=', 'parent.user_id')
            ->where('p.user_id', $memberId)
            ->first(['p.id', 'p.parent_id', 'p.slot', 'p.placed_at', 'u.username', 'u.name', 'parent_user.username as parent_username']);
        $result['available'] = true;
        $result['has_position'] = (bool) $root;
        for ($level = 1; $level <= $limit; $level++) {
            $result['levels'][$level] = 0;
        }
        if (! $root) {
            return $result;
        }

        $result['placement'] = [
            'username' => $root->username, 'name' => $root->name,
            'parent_username' => $root->parent_username,
            'slot' => $root->slot === null ? null : (int) $root->slot,
            'placed_at' => $root->placed_at,
        ];
        $frontier = [(int) $root->id];
        $seen = [(int) $root->id => true];
        for ($level = 1; $level <= $limit && $frontier; $level++) {
            $next = [];
            foreach (array_chunk($frontier, 500) as $parents) {
                $children = DB::table('magic_matrix_positions as p')
                    ->whereIn('p.parent_id', $parents)->orderBy('p.parent_id')->orderBy('p.slot')
                    ->get(['p.id', 'p.user_id', 'p.slot']);
                $displayIds = $children->take(max(0, 100 - count($result['members'])))->pluck('user_id');
                $displayMembers = $displayIds->isEmpty() ? collect() : DB::table('users')
                    ->whereIn('id', $displayIds)->get(['id', 'username', 'name'])->keyBy('id');
                foreach ($children as $child) {
                    $id = (int) $child->id;
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $next[] = $id;
                    if (count($result['members']) < 100 && $displayMembers->has($child->user_id)) {
                        $person = $displayMembers->get($child->user_id);
                        $result['members'][] = ['level' => $level, 'username' => $person->username, 'name' => $person->name, 'slot' => (int) $child->slot];
                    }
                }
            }
            $result['levels'][$level] = count($next);
            $result['total'] += count($next);
            if ($level === 1) {
                $result['direct'] = count($next);
            }
            if ($next) {
                $result['filled_levels']++;
            }
            $frontier = $next;
        }

        return $result;
    }
}
