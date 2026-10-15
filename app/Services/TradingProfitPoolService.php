<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Retired legacy generation-only funding entry point.
 * Historical financial tables and ledger entries are deliberately retained.
 * New profit events must use the separately approved unified 67/30/3 flow.
 */
final class TradingProfitPoolService
{
    public static function fund(User $admin, string $verifiedProfitAmount, string $profitReference): int
    {
        throw new RuntimeException('Legacy generation-only funding has been permanently retired.');
    }
}
