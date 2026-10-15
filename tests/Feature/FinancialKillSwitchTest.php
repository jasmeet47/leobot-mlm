<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivationService;
use App\Services\TradingProfitPoolService;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FinancialKillSwitchTest extends TestCase
{
    public function test_member_activation_is_disabled_without_database_access(): void
    {
        $this->assertFalse(config('leobot.member_activation_enabled'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Member activation is disabled');
        ActivationService::activateSelf(new User(), '100.00000000', (string) Str::uuid());
    }

    public function test_legacy_generation_only_funding_cannot_execute(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('permanently retired');
        TradingProfitPoolService::fund(new User(), '1000', 'TEST-LEGACY');
    }

    public function test_unsafe_p2p_controller_is_absent(): void
    {
        $this->assertFalse(class_exists('App\\Http\\Controllers\\Auth\\P2PTransferController'));
    }

    public function test_magic_pool_is_disabled_by_default(): void
    {
        $this->assertFalse(config('leobot.magic_pool_funding_enabled'));
    }
}
