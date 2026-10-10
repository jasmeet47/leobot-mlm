<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class BusinessQualificationService
{
    private const MAX_LEVEL = 51;

    private const SCALE = 8;

    private const BATCH_SIZE = 400;

    /**
     * Calculate team business and unlocked levels.
     *
     * Read-only: no wallets or commissions are changed.
     *
     * Business comes from ACTIVE activation packages.
     * Each downline member is counted only once.
     */
    public static function calculate(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Invalid member ID.'
            );
        }

        $member = User::findOrFail($userId);

        $levelBusiness = [];

        for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
            $levelBusiness[$level] = '0.00000000';
        }

        // Prevent a member from being counted twice.
        $visited = [
            (int) $member->id => true,
        ];

        // Start with the requested member.
        $currentLevelUsers = [
            (int) $member->id,
        ];

        for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
            $nextLevelUsers = [];

            /*
             * Find direct referrals of every member
             * at the preceding level.
             */
            foreach (
                array_chunk(
                    $currentLevelUsers,
                    self::BATCH_SIZE
                ) as $batch
            ) {
                $children = DB::table('users')
                    ->whereIn('sponsor_user_id', $batch)
                    ->select('id')
                    ->get();

                foreach ($children as $child) {
                    $childId = (int) $child->id;

                    if (isset($visited[$childId])) {
                        continue;
                    }

                    $visited[$childId] = true;

                    $nextLevelUsers[] = $childId;
                }
            }

            if (empty($nextLevelUsers)) {
                break;
            }

            /*
             * Calculate this level's business.
             *
             * Only ACTIVE activation packages count.
             * BCMath avoids floating-point money errors.
             */
            $business = '0.00000000';

            foreach (
                array_chunk(
                    $nextLevelUsers,
                    self::BATCH_SIZE
                ) as $batch
            ) {
                $packages = DB::table('activation_packages')
                    ->whereIn('user_id', $batch)
                    ->where('status', 'active')
                    ->select('investment_amount')
                    ->cursor();

                foreach ($packages as $package) {
                    $amount = (string)
                        $package->investment_amount;

                    if (
                        bccomp(
                            $amount,
                            '0',
                            self::SCALE
                        ) < 0
                    ) {
                        throw new RuntimeException(
                            'Invalid package business amount.'
                        );
                    }

                    $business = bcadd(
                        $business,
                        $amount,
                        self::SCALE
                    );
                }
            }

            $levelBusiness[$level] = $business;

            $currentLevelUsers = $nextLevelUsers;
        }

        /*
         * Total business across all 51 levels.
         */
        $totalBusiness = '0.00000000';

        foreach ($levelBusiness as $business) {
            $totalBusiness = bcadd(
                $totalBusiness,
                $business,
                self::SCALE
            );
        }

        /*
         * Apply admin-configured qualification rules.
         *
         * The rules are shared by both:
         * - Trading Profit Generation
         * - Investment Distribution
         */
        $rules = DB::table(
            'generation_qualification_settings'
        )
            ->orderBy('unlock_through_level')
            ->get();

        if ($rules->isEmpty()) {
            throw new RuntimeException(
                'Business qualification settings are missing.'
            );
        }

        $unlockedThroughLevel = 0;

        $qualificationResults = [];

        foreach ($rules as $rule) {
            $fromLevel = (int)
                $rule->business_from_level;

            $toLevel = (int)
                $rule->business_to_level;

            $unlockLevel = (int)
                $rule->unlock_through_level;

            if (
                $fromLevel < 1 ||
                $toLevel > self::MAX_LEVEL ||
                $fromLevel > $toLevel ||
                $unlockLevel < 1 ||
                $unlockLevel > self::MAX_LEVEL
            ) {
                throw new RuntimeException(
                    'Invalid qualification configuration.'
                );
            }

            $qualifyingBusiness = '0.00000000';

            for (
                $level = $fromLevel;
                $level <= $toLevel;
                $level++
            ) {
                $qualifyingBusiness = bcadd(
                    $qualifyingBusiness,
                    $levelBusiness[$level],
                    self::SCALE
                );
            }

            $requiredBusiness = (string)
                $rule->required_business;

            $qualified = bccomp(
                $qualifyingBusiness,
                $requiredBusiness,
                self::SCALE
            ) >= 0;

            if ($qualified) {
                $unlockedThroughLevel = max(
                    $unlockedThroughLevel,
                    $unlockLevel
                );
            }

            $qualificationResults[] = [
                'unlock_through_level' => $unlockLevel,
                'business_from_level' => $fromLevel,
                'business_to_level' => $toLevel,
                'required_business' => $requiredBusiness,
                'qualifying_business' => $qualifyingBusiness,
                'qualified' => $qualified,
            ];
        }

        return [
            'user_id' => (int) $member->id,
            'level_business' => $levelBusiness,
            'total_team_business' => $totalBusiness,
            'unlocked_through_level' =>
                $unlockedThroughLevel,
            'qualification_results' =>
                $qualificationResults,
        ];
    }

    /**
     * Check whether business qualification
     * has unlocked a particular level.
     *
     * Member activation status and income caps
     * must also be checked by CommissionService.
     */
    public static function isLevelUnlocked(
        int $userId,
        int $level
    ): bool {
        if ($level < 1 || $level > self::MAX_LEVEL) {
            return false;
        }

        $result = self::calculate($userId);

        return $level <=
            $result['unlocked_through_level'];
    }
}
