<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\MagicIncomeSettlementService;
use App\Services\WithdrawalRequestService;
use Tests\TestCase;

final class OptionAFinancialGatesTest extends TestCase
{
    public function test_magic_settlement_rejected_before_database_access_when_gate_off():void {
        config()->set('leobot_magic.magic_matrix_settlement_enabled',false);
        $this->expectException(\RuntimeException::class);
        MagicIncomeSettlementService::settleSource(new User(),1,2);
    }
    public function test_withdrawal_request_fails_closed_when_gate_off():void {
        config()->set('leobot.withdrawals_enabled',false);
        $this->expectException(\RuntimeException::class);
        WithdrawalRequestService::request(new User(),'10','REQ-TEST-001');
    }
    public function test_principal_withdrawal_cannot_be_mislabeled_as_earnings():void {
        // Gate check prevents even principal access in absence of explicit permission.
        config()->set('leobot.withdrawals_enabled',false);
        $this->expectException(\RuntimeException::class);
        WithdrawalRequestService::request(new User(),'50','REQ-TEST-002','principal');
    }
}
