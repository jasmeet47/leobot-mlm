<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Read-only 20-level genealogy. Never calculates or pays commissions. */
final class MagicMatrixHierarchyService
{
    public static function ancestorsForMember(int $memberId): array
    {
        if ($memberId < 1) {
            throw new InvalidArgumentException('Invalid member ID.');
        }
        $current = DB::table('magic_matrix_positions')->where('user_id', $memberId)->first();
        if (!$current) {
            return [];
        }
        $visited = [(int) $current->id => true];
        $ancestors = [];
        for ($level = 1; $level <= 20 && $current->parent_id !== null; $level++) {
            $parent = DB::table('magic_matrix_positions')->where('id', $current->parent_id)->first();
            if (!$parent || isset($visited[(int) $parent->id]) ||
                (int) $current->depth !== (int) $parent->depth + 1) {
                throw new RuntimeException('Magic matrix genealogy is inconsistent.');
            }
            $visited[(int) $parent->id] = true;
            $ancestors[$level] = (int) $parent->user_id;
            $current = $parent;
        }
        if ($current->parent_id !== null) {
            throw new RuntimeException('Magic matrix exceeds 20 levels.');
        }
        return $ancestors;
    }
}
