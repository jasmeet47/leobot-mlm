<?php

use App\Models\User;
use App\Services\LegacyIncomeCorrectionService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
config(['leobot.production_financial_approval' => true, 'leobot.staking_profit_enabled' => true, 'leobot.admin_profit_verification_enabled' => true, 'leobot.self_income_settlement_enabled' => true, 'leobot_magic.magic_matrix_settlement_enabled' => true]);
try {
    $id = LegacyIncomeCorrectionService::correct(User::findOrFail((int) $argv[1]), (int) $argv[2], '14', $argv[3]);
    echo 'CORRECTION_OK '.$id.PHP_EOL;
} catch (Throwable $error) {
    echo get_class($error).': '.$error->getMessage().PHP_EOL;
    exit(1);
}
