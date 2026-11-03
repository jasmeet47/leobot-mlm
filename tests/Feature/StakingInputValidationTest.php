<?php
namespace Tests\Feature;

use App\Services\StakingService;
use RuntimeException;
use Tests\TestCase;

final class StakingInputValidationTest extends TestCase
{
    public function test_invalid_amounts_are_rejected_before_database_access(): void
    {
        $service = new StakingService();
        foreach (['0', '0.00000000', '-1', '1.000000001', 'abc', '1000000000000', '1e2'] as $amount) {
            try {
                $service->create(1, $amount, 'valid_key_1234567890');
                $this->fail('Accepted invalid amount: '.$amount);
            } catch (RuntimeException $e) {
                $this->assertSame('Invalid amount', $e->getMessage());
            }
        }
    }

    public function test_invalid_request_keys_are_rejected_before_database_access(): void
    {
        $service = new StakingService();
        foreach (['short', 'contains spaces 123456789', str_repeat('a', 101), 'unsafe/key_123456789'] as $key) {
            try {
                $service->create(1, '1.00000000', $key);
                $this->fail('Accepted invalid request key');
            } catch (RuntimeException $e) {
                $this->assertSame('Invalid request key', $e->getMessage());
            }
        }
    }
}