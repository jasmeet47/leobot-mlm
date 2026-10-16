<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MagicProposedRatesTest extends TestCase
{
    public function test_twenty_equal_proposed_rates_total_one_hundred_percent(): void {
        $rates=array_fill(1,20,'5.000000000');
        $total='0.000000000';
        foreach ($rates as $level=>$rate) {
            $this->assertGreaterThanOrEqual(1,$level);
            $this->assertLessThanOrEqual(20,$level);
            $total=bcadd($total,$rate,9);
        }
        $this->assertSame(0,bccomp('100.000000000',$total,9));
    }
    public function test_10000_trading_profit_produces_300_magic_pool_and_fifteen_per_level():void {
        $pool=bcdiv(bcmul('10000','3',8),'100',8);
        $this->assertSame('300.00000000',$pool);
        $level=bcdiv(bcmul($pool,'5',8),'100',8);
        $this->assertSame('15.00000000',$level);
    }
}
