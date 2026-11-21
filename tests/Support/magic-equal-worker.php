<?php

use App\Models\User;
use App\Services\MagicEqualIncomeSettlementService;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
config(['leobot_magic.magic_matrix_settlement_enabled' => true]);
try {
    $id = MagicEqualIncomeSettlementService::settle(User::findOrFail((int) $argv[1]), (int) $argv[2]);
    echo 'EQUAL_OK '.$id.PHP_EOL;
} catch (Throwable $error) {
    echo get_class($error).': '.$error->getMessage().PHP_EOL;
    exit(1);
}
