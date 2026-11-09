<?php

use App\Contracts\UsdtChainVerifier;
use App\Models\User;
use App\Services\MagicMatrixEnrollmentService;
use App\Services\ReadOnlyUsdtChainVerifier;
use App\Services\StakingPrincipalService;
use App\Services\StakingProfitService;
use App\Services\StakingService;
use App\Services\VerifiedDepositService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
config(['leobot.staking_enabled' => true, 'leobot.production_financial_approval' => true, 'leobot.withdrawals_enabled' => true, 'leobot.staking_profit_enabled' => true]);
echo "ready\n";
flush();
try {
    if (($argv[4] ?? '') === 'magic-enrollment') {
        config(['leobot_magic.magic_matrix_enrollment_enabled' => true]);
        $id = MagicMatrixEnrollmentService::synchronize((int) $argv[1]);
    } elseif (($argv[4] ?? '') === 'principal') {
        $id = StakingPrincipalService::request(User::findOrFail((int) $argv[1]), (int) $argv[5], $argv[2], $argv[3], (int) $argv[6]);
    } elseif (($argv[4] ?? '') === 'deposit-approve') {
        config(['leobot.deposits_enabled' => true, 'leobot_usdt_rpc.enabled' => true, 'leobot_usdt_rpc.bsc_rpc_url' => 'https://rpc.example.test']);
        $deposit = DB::table('member_deposit_requests')->find((int) $argv[2]);
        $receipt = ['status' => '0x1', 'transactionHash' => $deposit->tx_hash, 'blockHash' => '0x'.str_repeat('c', 64), 'blockNumber' => '0x64',
            'logs' => [['address' => '0x55d398326f99059ff775485246999027b3197955', 'removed' => false,
                'topics' => ['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef', '0x'.str_repeat('0', 64), '0x'.str_repeat('0', 24).substr($deposit->receiving_address, 2)],
                'data' => '0x'.str_pad('2b5e4ad178efe1ff2', 64, '0', STR_PAD_LEFT), 'logIndex' => '0x3b']]];
        $responses = ['eth_chainId' => '0x38', 'eth_getTransactionReceipt' => $receipt, 'eth_getTransactionByHash' => ['hash' => $deposit->tx_hash, 'blockHash' => $receipt['blockHash'], 'blockNumber' => '0x64'], 'eth_blockNumber' => '0x100'];
        Http::preventStrayRequests();
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $responses[$request['method']] ?? null]));
        $app->instance(UsdtChainVerifier::class, new ReadOnlyUsdtChainVerifier);
        VerifiedDepositService::approve((int) $argv[1], (int) $argv[2]);
        $id = (int) $argv[2];
    } elseif (($argv[4] ?? '') === 'profit') {
        StakingProfitService::approve(User::findOrFail((int) $argv[1]), (int) $argv[2]);
        $id = (int) $argv[2];
    } else {
        $id = (new StakingService)->create((int) $argv[1], $argv[2], $argv[3]);
    }
    echo json_encode(['ok' => true, 'id' => $id])."\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()])."\n";
}
