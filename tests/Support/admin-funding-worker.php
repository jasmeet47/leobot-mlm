<?php

use App\Models\User;
use App\Services\AdminFundingWalletService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
config(['leobot.production_financial_approval' => true, 'leobot.admin_member_activation_enabled' => true, 'leobot.staking_enabled' => true, 'leobot_magic.magic_matrix_enrollment_enabled' => true, 'leobot_magic.production_financial_approval' => true]);
try {
    $admin = User::findOrFail((int) $argv[2]);
    $id = $argv[1] === 'topup' ? AdminFundingWalletService::topUp($admin, $argv[3], $argv[4], true, $argv[5] ?? null) : AdminFundingWalletService::activate($admin, (int) $argv[3], $argv[4], $argv[5]);
    echo 'ADMIN_FUND_OK '.$id.PHP_EOL;
} catch (RuntimeException $error) {
    echo 'ADMIN_FUND_REJECTED '.$error->getMessage().PHP_EOL;
    exit(2);
} catch (Throwable $error) {
    echo get_class($error).': '.$error->getMessage().PHP_EOL;
    exit(1);
}
