<?php

use App\Models\User;
use App\Services\MagicMatrixEnrollmentService;
use App\Services\StakingPrincipalService;
use App\Services\StakingProfitService;
use App\Services\StakingService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;

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
