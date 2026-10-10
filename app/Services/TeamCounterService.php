<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Financially read-only 51-level team counter cache.
 *
 * Synchronize a downline once during the same transaction that creates the
 * account or activates a package. A unique per-member applied-state record
 * makes repeat synchronizations idempotent. No wallets or commissions change.
 */
final class TeamCounterService
{
    private const MAX_LEVEL = 51;
    private const SCALE = 8;
    private const CHUNK_SIZE = 400;
    private const MAX_BUSINESS = '99999999999999999999.99999999'; // DECIMAL(28,8)

    public static function synchronize(int $memberId): void
    {
        if ($memberId <= 0) {
            throw new RuntimeException('Invalid team member ID.');
        }

        DB::transaction(function () use ($memberId): void {
            // Serializes all concurrent updates to one downline, including
            // back-to-back activations or repeated sync calls.
            $member = User::query()->whereKey($memberId)
                ->lockForUpdate()->firstOrFail();

            $parentId = $member->sponsor_user_id === null
                ? null : (int) $member->sponsor_user_id;

            $business = self::activePackageBusiness($memberId);
            $active = $member->status === 'active';

            $now = now();
            DB::table('member_team_states')->insertOrIgnore([
                'user_id' => $memberId,
                'sponsor_user_id' => $parentId,
                'is_counted' => false,
                'is_active' => false,
                'active_business' => '0.00000000',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $state = DB::table('member_team_states')
                ->where('user_id', $memberId)
                ->lockForUpdate()->first();

            if (!$state) {
                throw new RuntimeException('Team sync state is missing.');
            }

            // Reparenting needs a separate audited transfer process.
            // Never silently leave contributions with an old sponsor.
            $originalParent = $state->sponsor_user_id === null
                ? null : (int) $state->sponsor_user_id;
            if ($originalParent !== $parentId) {
                throw new RuntimeException('Sponsor tree change requires a controlled rebuild.');
            }

            $wasCounted = self::dbBool($state->is_counted);
            $wasActive = $wasCounted && self::dbBool($state->is_active);
            $wasInactive = $wasCounted && !self::dbBool($state->is_active);
            $isInactive = !$active;

            $activeDelta = (int) $active - (int) $wasActive;
            $inactiveDelta = (int) $isInactive - (int) $wasInactive;
            $businessDelta = bcsub(
                $business,
                $wasCounted ? (string) $state->active_business : '0',
                self::SCALE
            );

            if ($activeDelta !== 0 || $inactiveDelta !== 0 ||
                bccomp($businessDelta, '0', self::SCALE) !== 0) {
                foreach (self::uplineLevels($memberId, $parentId) as [$ancestorId, $level]) {
                    self::applyDelta(
                        $ancestorId, $level,
                        $activeDelta, $inactiveDelta, $businessDelta
                    );
                }
            }

            DB::table('member_team_states')->where('user_id', $memberId)->update([
                'is_counted' => true,
                'is_active' => $active,
                'active_business' => $business,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    /** @return array<int, array{0:int, 1:int}> */
    private static function uplineLevels(int $memberId, ?int $parentId): array
    {
        $visited = [$memberId => true];
        $ancestors = [];

        for ($level = 1; $level <= self::MAX_LEVEL && $parentId !== null; $level++) {
            if (isset($visited[$parentId])) {
                throw new RuntimeException('Cyclic sponsor relationship detected.');
            }
            $visited[$parentId] = true;

            $parent = DB::table('users')->where('id', $parentId)
                ->select('id', 'sponsor_user_id')->first();
            if (!$parent) {
                throw new RuntimeException('Broken sponsor relationship.');
            }
            $ancestors[] = [(int) $parent->id, $level];
            $parentId = $parent->sponsor_user_id === null
                ? null : (int) $parent->sponsor_user_id;
        }

        return $ancestors;
    }

    private static function activePackageBusiness(int $memberId): string
    {
        $business = '0.00000000';
        // Iterate decimal *strings*; SQLite's native SUM can use floating
        // point. BCMath preserves all 8 investment decimal places.
        DB::table('activation_packages')
            ->where('user_id', $memberId)
            ->where('status', 'active')
            ->orderBy('id')
            ->select('id', 'investment_amount')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use (&$business): void {
                foreach ($rows as $row) {
                    $amount = (string) $row->investment_amount;
                    if (!preg_match('/\A\d+(?:\.\d{1,8})?\z/', $amount) ||
                        bccomp($amount, '0', self::SCALE) < 0) {
                        throw new RuntimeException('Invalid active package business.');
                    }
                    $business = bcadd($business, $amount, self::SCALE);
                    if (bccomp($business, self::MAX_BUSINESS, self::SCALE) > 0) {
                        throw new RuntimeException('Active package business exceeds storage limit.');
                    }
                }
            });

        return $business;
    }

    /** Normalize SQLite, MySQL and PostgreSQL PDO boolean representations. */
    private static function dbBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private static function applyDelta(
        int $ancestorId,
        int $level,
        int $activeDelta,
        int $inactiveDelta,
        string $businessDelta
    ): void {
        $now = now();
        DB::table('member_teams')->insertOrIgnore([
            'user_id' => $ancestorId,
            'level' => $level,
            'active_count' => 0,
            'inactive_count' => 0,
            'total_business' => '0.00000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::table('member_teams')
            ->where('user_id', $ancestorId)
            ->where('level', $level)
            ->lockForUpdate()->first();
        if (!$row) {
            throw new RuntimeException('Team counter row missing.');
        }

        $newActive = (int) $row->active_count + $activeDelta;
        $newInactive = (int) $row->inactive_count + $inactiveDelta;
        $newBusiness = bcadd(
            (string) $row->total_business,
            $businessDelta,
            self::SCALE
        );
        if ($newActive < 0 || $newInactive < 0 ||
            $newActive > 4294967295 || $newInactive > 4294967295 ||
            bccomp($newBusiness, '0', self::SCALE) < 0 ||
            bccomp($newBusiness, self::MAX_BUSINESS, self::SCALE) > 0) {
            throw new RuntimeException('Team counter integrity limit exceeded.');
        }

        DB::table('member_teams')
            ->where('user_id', $ancestorId)
            ->where('level', $level)
            ->update([
                'active_count' => $newActive,
                'inactive_count' => $newInactive,
                'total_business' => $newBusiness,
                'updated_at' => now(),
            ]);
    }
}
