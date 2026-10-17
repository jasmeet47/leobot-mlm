<?php

namespace Tests\Unit;

use App\Services\ReadOnlyUsdtChainVerifier;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyUsdtChainVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('leobot_usdt_rpc.enabled', false);
        config()->set('leobot_usdt_rpc.bsc_rpc_url', 'https://rpc.example.test');
        config()->set('leobot_usdt_rpc.tron_full_node_url', 'https://tron.example.test');
        config()->set('leobot_usdt_rpc.tron_solidity_node_url', 'https://tron.example.test');
        Http::preventStrayRequests();
    }

    public function test_verification_is_disabled_by_default(): void
    {
        Http::fake();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disabled');
        (new ReadOnlyUsdtChainVerifier())->verified('BEP20', '0x'.str_repeat('a',64), '0x'.str_repeat('b',40), '1');
    }

    public function test_invalid_network_and_amount_fail_without_network_requests(): void
    {
        config()->set('leobot_usdt_rpc.enabled', true);
        Http::fake();
        $verifier = new ReadOnlyUsdtChainVerifier();
        $this->assertFalse($verifier->verified('UNKNOWN', 'anything', 'anything', '1'));
        $this->assertFalse($verifier->verified('BEP20', 'anything', 'anything', '0'));
        $this->assertFalse($verifier->verified('BEP20', 'anything', 'anything', '1.000000001'));
        $this->assertFalse($verifier->verified('BEP20', 'anything', 'anything', '-1'));
        Http::assertNothingSent();
    }

    public function test_invalid_bep20_transaction_hash_and_recipient_fail_closed(): void
    {
        config()->set('leobot_usdt_rpc.enabled', true);
        Http::fake();
        $verifier = new ReadOnlyUsdtChainVerifier();
        $this->assertFalse($verifier->verified('BEP20', 'not-a-hash', '0x'.str_repeat('b',40), '1'));
        $this->assertFalse($verifier->verified('BEP20', '0x'.str_repeat('a',64), 'invalid', '1'));
        Http::assertNothingSent();
    }

    public function test_wrong_bsc_chain_id_is_rejected(): void
    {
        config()->set('leobot_usdt_rpc.enabled', true);
        Http::fake(['rpc.example.test' => Http::response(['jsonrpc'=>'2.0', 'id'=>1, 'result'=>'0x1'], 200)]);
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20', '0x'.str_repeat('a',64), '0x'.str_repeat('b',40), '1'));
        Http::assertSentCount(1);
    }

    public function test_invalid_tron_transaction_hash_is_rejected_without_network(): void
    {
        config()->set('leobot_usdt_rpc.enabled', true);
        Http::fake();
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('TRC20', 'invalid', 'T'.str_repeat('a',33), '1'));
        Http::assertNothingSent();
    }
}
