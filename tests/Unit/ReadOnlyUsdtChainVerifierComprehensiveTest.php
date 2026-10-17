<?php
namespace Tests\Unit;

use App\Services\ReadOnlyUsdtChainVerifier;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Deterministic mocked-node tests: never makes a live RPC request. */
final class ReadOnlyUsdtChainVerifierComprehensiveTest extends TestCase
{
    private const BSC_USDT = '0x55d398326f99059ff775485246999027b3197955';
    private const TRANSFER = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    private const HASH = '0x'.'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const TO = '0x'.'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const TRON_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    // TRON USDT canonical contract address; use its own valid Base58Check as a fixture recipient.
    private const TRON_TO = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('leobot_usdt_rpc.enabled', true);
        config()->set('leobot_usdt_rpc.bsc_rpc_url', 'https://rpc.example.test');
        config()->set('leobot_usdt_rpc.bsc_confirmations', 30);
        config()->set('leobot_usdt_rpc.tron_full_node_url', 'https://tron.example.test');
        config()->set('leobot_usdt_rpc.tron_solidity_node_url', 'https://tron.example.test');
        Http::preventStrayRequests();
    }

    private function bscResponses(array $override = []): array
    {
        $receipt = [
            'status'=>'0x1','blockHash'=>'0x'.str_repeat('c',64),'blockNumber'=>'0x64',
            'logs'=>[[
                'address'=>self::BSC_USDT, 'removed'=>false,
                'topics'=>[self::TRANSFER,'0x'.str_repeat('0',24).str_repeat('d',40),'0x'.str_repeat('0',24).substr(self::TO,2)],
                'data'=>'0x'.str_pad(dechex(1000000000000000000),64,'0',STR_PAD_LEFT),
            ]],
        ];
        $receipt = array_replace_recursive($receipt, $override);
        return [
            'eth_chainId'=>'0x38',
            'eth_getTransactionReceipt'=>$receipt,
            'eth_getTransactionByHash'=>['blockHash'=>$receipt['blockHash'],'blockNumber'=>$receipt['blockNumber']],
            'eth_blockNumber'=>'0x81',
        ];
    }

    private function fakeBsc(array $responses): void
    {
        Http::fake(function ($request) use ($responses) {
            $method = $request['method'];
            return Http::response(['jsonrpc'=>'2.0','id'=>1,'result'=>$responses[$method] ?? null], 200);
        });
    }

    public function test_bep20_matching_confirmed_transfer_succeeds(): void
    {
        $this->fakeBsc($this->bscResponses());
        $this->assertTrue((new ReadOnlyUsdtChainVerifier())->verified('BEP20', self::HASH, self::TO, '1'));
        Http::assertSentCount(4);
    }

    public function test_bep20_wrong_contract_rejected(): void
    {
        $this->fakeBsc($this->bscResponses(['logs'=>[['address'=>'0x'.str_repeat('e',40)]]]));
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,self::TO,'1'));
    }

    public function test_bep20_wrong_recipient_rejected(): void
    {
        $this->fakeBsc($this->bscResponses());
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,'0x'.str_repeat('e',40),'1'));
    }

    public function test_bep20_wrong_amount_rejected(): void
    {
        $this->fakeBsc($this->bscResponses());
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,self::TO,'2'));
    }

    public function test_bep20_failed_receipt_rejected(): void
    {
        $this->fakeBsc($this->bscResponses(['status'=>'0x0']));
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,self::TO,'1'));
    }

    public function test_bep20_insufficient_confirmations_rejected(): void
    {
        $r=$this->bscResponses(); $r['eth_blockNumber']='0x65'; $this->fakeBsc($r);
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,self::TO,'1'));
    }

    public function test_bep20_rpc_error_throws_without_success(): void
    {
        Http::fake(fn() => Http::response(['jsonrpc'=>'2.0','id'=>1,'error'=>['code'=>-32000,'message'=>'down']],200));
        $this->expectException(RuntimeException::class);
        (new ReadOnlyUsdtChainVerifier())->verified('BEP20',self::HASH,self::TO,'1');
    }

    public function test_trc20_invalid_checksum_rejected_without_http(): void
    {
        Http::fake();
        $bad=substr(self::TRON_TO,0,-1).(substr(self::TRON_TO,-1)==='Q'?'R':'Q');
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,$bad,'1'));
        Http::assertNothingSent();
    }

    public function test_trc20_fractional_sun_amount_rejected_without_http(): void
    {
        Http::fake();
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,self::TRON_TO,'0.00000001'));
        Http::assertNothingSent();
    }

    private function fakeTron(array $infoChanges=[]): void
    {
        $info=array_replace_recursive([
            'id'=>self::TRON_HASH,'receipt'=>['result'=>'SUCCESS'],'blockNumber'=>1234,
            'log'=>[[
                'address'=>'a614f803b6fd780986a42c78ec9c7f77e6ded13c',
                'topics'=>[substr(self::TRANSFER,2),str_repeat('0',64),str_repeat('0',24).'a614f803b6fd780986a42c78ec9c7f77e6ded13c'],
                'data'=>str_pad(dechex(1000000),64,'0',STR_PAD_LEFT),
            ]],
        ],$infoChanges);
        Http::fake(function ($request) use ($info) {
            $path=parse_url($request->url(),PHP_URL_PATH);
            if ($path==='/wallet/gettransactionbyid') return Http::response(['txID'=>self::TRON_HASH,'ret'=>[['contractRet'=>'SUCCESS']]]);
            if ($path==='/walletsolidity/gettransactionbyid') return Http::response(['txID'=>self::TRON_HASH,'raw_data'=>['expiration'=>9999999999999]]);
            if ($path==='/walletsolidity/gettransactioninfobyid') return Http::response($info);
            return Http::response([],404);
        });
    }

    public function test_trc20_matching_confirmed_transfer_succeeds(): void
    {
        $this->fakeTron();
        $this->assertTrue((new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,self::TRON_TO,'1'));
        Http::assertSentCount(3);
    }

    public function test_trc20_failed_execution_rejected(): void
    {
        $this->fakeTron(['receipt'=>['result'=>'OUT_OF_ENERGY']]);
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,self::TRON_TO,'1'));
    }

    public function test_trc20_wrong_amount_rejected(): void
    {
        $this->fakeTron();
        $this->assertFalse((new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,self::TRON_TO,'2'));
    }

    public function test_trc20_node_error_throws_without_success(): void
    {
        Http::fake(fn()=>Http::response([],503));
        $this->expectException(RuntimeException::class);
        (new ReadOnlyUsdtChainVerifier())->verified('TRC20',self::TRON_HASH,self::TRON_TO,'1');
    }
}
