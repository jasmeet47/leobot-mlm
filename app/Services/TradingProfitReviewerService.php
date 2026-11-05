<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** A narrowly scoped permission; never changes a member's role or grants admin access. */
final class TradingProfitReviewerService
{
    public static function canReview(int $userId): bool
    {
        return DB::table('trading_profit_reviewers as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.user_id', $userId)->where('r.enabled', true)->where('u.role', 'member')
            ->whereIn('u.status', ['active', 'inactive'])->where('u.is_blocked', false)->whereNull('u.deleted_at')->exists();
    }

    public static function setEnabled(User $admin, int $userId, bool $enabled): void
    {
        if ($enabled && config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Production financial approval is required to assign a reviewer.');
        }
        DB::transaction(function () use ($admin, $userId, $enabled): void {
            $actor = DB::table('users')->where('id', $admin->id)->lockForUpdate()->first(['id', 'role', 'status', 'is_blocked', 'deleted_at']);
            if (! self::active($actor) || ! in_array($actor->role, ['admin', 'super_admin'], true)) {
                throw new AuthorizationException('Active administrator required.');
            }
            $member = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'role', 'status', 'is_blocked', 'deleted_at']);
            if (! $member || ($enabled && (! self::loginMember($member) || $member->role !== 'member' || $userId === $admin->id))) {
                throw new RuntimeException('An existing login-eligible unblocked member is required.');
            }
            $prior = DB::table('trading_profit_reviewers')->where('user_id', $userId)->lockForUpdate()->first();
            if (($prior && self::yes($prior->enabled) === $enabled) || (! $prior && ! $enabled)) {
                return;
            }
            if ($prior) {
                DB::table('trading_profit_reviewers')->where('id', $prior->id)->update(['enabled' => $enabled, 'granted_by' => $actor->id, 'updated_at' => now()]);
            } else {
                DB::table('trading_profit_reviewers')->insert(['user_id' => $userId, 'enabled' => $enabled, 'granted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('trading_profit_reviewer_audits')->insert(['user_id' => $userId, 'admin_id' => $actor->id, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
    }

    public static function attest(User $reviewer, int $statementId, string $expectedDigest): int
    {
        self::gate();

        return DB::transaction(function () use ($reviewer, $statementId, $expectedDigest): int {
            $statement = DB::table('staking_profit_statements')->where('id', $statementId)->lockForUpdate()->first();
            if (! $statement || ! in_array($statement->status, ['pending_verification', 'independently_verified'], true) || $statement->distribution_version !== 'unified_67_30_3') {
                throw new RuntimeException('Only a pending unified profit statement can be reviewed.');
            }
            self::requireMember($reviewer->id, true);
            $rows = DB::table('staking_profit_allocations')->where('statement_id', $statementId)->orderBy('user_id')->lockForUpdate()->get();
            self::independent($reviewer->id, $statement, $rows);
            $digest = self::evidenceDigest($statement, $rows);
            if (! hash_equals($digest, $expectedDigest)) {
                throw new RuntimeException('Statement changed after display; reload and independently review it again.');
            }
            $prior = DB::table('staking_profit_reviews')->where('statement_id', $statementId)->orderByDesc('id')->first();
            if ($prior) {
                if (! hash_equals($prior->evidence_digest, $digest)) {
                    throw new RuntimeException('Reviewed evidence changed; independent reconciliation required.');
                }

                if ((int) $prior->reviewer_id === $reviewer->id && (int) $statement->verified_by === $reviewer->id && $statement->status === 'independently_verified') {
                    return (int) $prior->id;
                }
            }

            $reviewId = (int) DB::table('staking_profit_reviews')->insertGetId(['statement_id' => $statementId, 'reviewer_id' => $reviewer->id,
                'evidence_digest' => $digest, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('staking_profit_statements')->where('id', $statementId)->update([
                'status' => 'independently_verified', 'verified_by' => $reviewer->id,
                'verification_method' => 'independent_member_attestation', 'updated_at' => now(),
            ]);

            return $reviewId;
        }, 3);
    }

    /** Read first, then repeat with locks after funding reserves are locked, before commit. */
    public static function requireAttestation(object $statement, iterable $rows, bool $lock = false): int
    {
        $review = DB::table('staking_profit_reviews')->where('statement_id', $statement->id)->orderByDesc('id')->first();
        if (! $review) {
            throw new AuthorizationException('Independent trading-profit reviewer required.');
        }
        if ($statement->status !== 'independently_verified' || (int) $statement->verified_by !== (int) $review->reviewer_id
            || $statement->verification_method !== 'independent_member_attestation') {
            throw new AuthorizationException('Independent approval must be completed before reservation.');
        }
        self::requireMember((int) $review->reviewer_id, $lock);
        self::independent((int) $review->reviewer_id, $statement, $rows);
        if (! hash_equals($review->evidence_digest, self::evidenceDigest($statement, $rows))) {
            throw new RuntimeException('Reviewed evidence changed; independent reconciliation required.');
        }

        return (int) $review->reviewer_id;
    }

    public static function historicalAttestationMatches(object $statement, iterable $rows): bool
    {
        $review = DB::table('staking_profit_reviews')->where('statement_id', $statement->id)->where('reviewer_id', $statement->verified_by)->first();
        if (! $review || ! hash_equals($review->evidence_digest, self::evidenceDigest($statement, $rows))) {
            return false;
        }
        try {
            self::independent((int) $review->reviewer_id, $statement, $rows);
        } catch (AuthorizationException) {
            return false;
        }

        return true;
    }

    private static function requireMember(int $id, bool $lock): void
    {
        $users = DB::table('users')->where('id', $id);
        $grants = DB::table('trading_profit_reviewers')->where('user_id', $id);
        if ($lock) {
            $users->lockForUpdate();
            $grants->lockForUpdate();
        }
        $member = $users->first(['id', 'role', 'status', 'is_blocked', 'deleted_at']);
        $grant = $grants->first();
        if (! self::loginMember($member) || $member->role !== 'member' || ! $grant || ! self::yes($grant->enabled)) {
            throw new AuthorizationException('Active independent reviewer permission required.');
        }
    }

    private static function independent(int $reviewerId, object $statement, iterable $rows): void
    {
        if ($reviewerId === (int) $statement->submitted_by) {
            throw new AuthorizationException('Submitter cannot review their own statement.');
        }
        foreach ($rows as $row) {
            if ((int) $row->user_id === $reviewerId) {
                throw new AuthorizationException('A profit beneficiary cannot review that statement.');
            }
        }
    }

    public static function evidenceDigest(object $statement, iterable $rows): string
    {
        $allocations = [];
        foreach ($rows as $row) {
            $allocations[(int) $row->user_id] = bcadd((string) $row->amount, '0', 8);
        }
        ksort($allocations, SORT_NUMERIC);

        return hash('sha256', json_encode(['statement_id' => (int) $statement->id, 'submitted_by' => (int) $statement->submitted_by,
            'amount' => bcadd((string) $statement->amount, '0', 8), 'reference' => $statement->evidence_reference,
            'version' => $statement->distribution_version, 'allocations' => $allocations], JSON_THROW_ON_ERROR));
    }

    private static function loginMember(?object $user): bool
    {
        return $user && in_array($user->status, ['active', 'inactive'], true) && $user->deleted_at === null && ! self::yes($user->is_blocked);
    }

    private static function active(?object $user): bool
    {
        return $user && $user->status === 'active' && $user->deleted_at === null && ! self::yes($user->is_blocked);
    }

    private static function yes(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }

    private static function gate(): void
    {
        if (config('leobot.staking_profit_enabled', false) !== true || config('leobot.production_financial_approval', false) !== true) {
            throw new RuntimeException('Trading-profit review is disabled.');
        }
    }
}
