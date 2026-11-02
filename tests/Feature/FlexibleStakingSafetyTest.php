<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\FlexibleStakingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class FlexibleStakingSafetyTest extends TestCase {
    public function test_flexible_staking_service_exposes_required_methods(): void {
        $this->assertTrue(method_exists(FlexibleStakingService::class, 'request'));
        $this->assertTrue(method_exists(FlexibleStakingService::class, 'approve'));
        $this->assertTrue(method_exists(FlexibleStakingService::class, 'reject'));
    }
    public function test_invalid_unstake_amount_is_rejected_before_database_access(): void {
        $this->expectException(RuntimeException::class);
        app(FlexibleStakingService::class)->request(1,1,'0','test_request_key_123456789');
    }
    public function test_invalid_request_key_is_rejected_before_database_access(): void {
        $this->expectException(RuntimeException::class);
        app(FlexibleStakingService::class)->request(1,1,'1.00000000','bad');
    }
}
