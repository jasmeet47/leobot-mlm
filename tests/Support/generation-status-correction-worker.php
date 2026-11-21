<?php

use App\Models\User;
use App\Services\GenerationStatusCorrectionService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
config(['leobot.production_financial_approval' => true, 'leobot.generation_income_settlement_enabled' => true, 'leobot.admin_profit_verification_enabled' => true]);
try {
    echo 'STATUS_CORRECTION_OK '.GenerationStatusCorrectionService::settle(User::findOrFail((int) $argv[1]), (int) $argv[2], $argv[3]).PHP_EOL;
} catch (Throwable $error) {
    echo get_class($error).': '.$error->getMessage().PHP_EOL;
    exit(1);
}
