<?php

namespace Tests\Unit;

use App\Services\MagicTopupPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MagicTopupPolicyTest extends TestCase
{
    public function test_progressive_six_day_brackets_are_exact_at_boundaries(): void
    {
        $start = new DateTimeImmutable('2026-10-16T00:00:00.000000+00:00');
        foreach ([
            ['2026-10-16T00:00:00+00:00', 1],
            ['2026-10-22T00:00:00+00:00', 1],
            ['2026-10-22T00:00:00.000001+00:00', 2],
            ['2026-10-22T00:00:01+00:00', 2],
            ['2026-10-28T00:00:00+00:00', 2],
            ['2026-10-28T00:00:01+00:00', 3],
            ['2026-11-03T00:00:00+00:00', 3],
            ['2026-11-03T00:00:01+00:00', 4],
            ['2026-11-09T00:00:01+00:00', 5],
        ] as [$at, $expected]) {
            self::assertSame($expected, MagicTopupPolicy::multiplier($start, new DateTimeImmutable($at)), $at);
        }
    }

    public function test_early_payment_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MagicTopupPolicy::multiplier(
            new DateTimeImmutable('2026-10-16T00:00:00Z'),
            new DateTimeImmutable('2026-10-15T23:59:59Z')
        );
    }

    public function test_exact_deposit_quotes_use_decimal_arithmetic(): void
    {
        self::assertSame('50.00000000', MagicTopupPolicy::requiredAmount('50', 1));
        self::assertSame('100.00000000', MagicTopupPolicy::requiredAmount('50', 2));
        self::assertSame('180.00000000', MagicTopupPolicy::requiredAmount('60', 3));
        self::assertSame('400.00000000', MagicTopupPolicy::requiredAmount('100', 4));
    }

    public function test_zero_tier_or_zero_multiplier_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MagicTopupPolicy::requiredAmount('0', 1);
    }
}
