<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Retired standalone Magic funding API, retained only to fail closed for old callers.
 * The ONLY supported funding path is UnifiedProfitSharingService 67/30/3.
 * Historical financial records and migrations remain untouched.
 */
final class MagicPoolFundingService
{
    public static function fundFromTradingProfit(
        User $admin,
        string $verifiedProfitAmount,
        string $sourceReference
    ): int {
        throw new RuntimeException('Independent Magic funding disabled: use Unified Trading Profit 67/30/3.');
    }

    public static function fundDirectPool(
        User $admin,
        string $poolAmount,
        string $sourceReference
    ): int {
        throw new RuntimeException('Standalone Magic pool funding disabled: use Unified Trading Profit.');
    }
}