<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SelfIncomeSettlementService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SelfIncomeSettlementGateTest extends TestCase
{
    #[Test]
    public function settlement_is_disabled_by_default_without_database_access(): void
    {
        config()->set('leobot.self_income_settlement_enabled', false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Self income settlement is disabled.');
        SelfIncomeSettlementService::settle(new User(), 1);
    }
}
