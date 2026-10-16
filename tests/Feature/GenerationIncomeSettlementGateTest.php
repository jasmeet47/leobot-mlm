<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\GenerationIncomeSettlementService;
use RuntimeException;
use Tests\TestCase;
class GenerationIncomeSettlementGateTest extends TestCase {
    public function test_generation_settlement_fails_closed_without_database_writes(): void {
        config()->set('leobot.generation_income_settlement_enabled', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Generation settlement disabled.');
        GenerationIncomeSettlementService::settle(new User(), 1);
    }
}
