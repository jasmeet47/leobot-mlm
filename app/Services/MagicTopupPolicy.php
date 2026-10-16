<?php

namespace App\Services;

use DateTimeInterface;
use InvalidArgumentException;

/** Deterministic six-day brackets. Pure computation; never transfers funds. */
final class MagicTopupPolicy
{
    public const GRACE_DAYS = 6;

    public static function multiplier(DateTimeInterface $capReachedAt, DateTimeInterface $paidAt, int $periodDays = self::GRACE_DAYS): int
    {
        if ($periodDays !== 6) {
            throw new InvalidArgumentException('Final policy requires six-day periods.');
        }
        if ($paidAt < $capReachedAt) {
            throw new InvalidArgumentException('Re-topup time cannot precede cap.');
        }
        $seconds = $paidAt->getTimestamp() - $capReachedAt->getTimestamp();
        // Charge the next bracket even if only one microsecond past day six.
        if ((int) $paidAt->format('u') > (int) $capReachedAt->format('u')) {
            $seconds++;
        }
        $period = $periodDays * 86400;
        return max(1, intdiv(max(0, $seconds - 1), $period) + 1);
    }

    public static function requiredAmount(string $tierUsdt, int $multiplier): string
    {
        if (!preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,8})?\z/D', $tierUsdt)
            || $multiplier < 1 || $multiplier > 100000) {
            throw new InvalidArgumentException('Invalid tier or multiplier.');
        }
        if (!function_exists('bcmul')) {
            throw new \RuntimeException('BCMath extension required for financial operations.');
        }
        if (bccomp($tierUsdt, '0', 8) <= 0) {
            throw new InvalidArgumentException('Tier must be positive.');
        }
        $result = bcmul($tierUsdt, (string) $multiplier, 8);
        if (bccomp($result, '999999999999.99999999', 8) > 0) {
            throw new InvalidArgumentException('Amount exceeds financial limit.');
        }
        return $result;
    }
}
