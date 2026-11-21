<?php

use App\Models\User;
use App\Services\MemberWithdrawalControl;
use App\Support\DisposableDatabase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DisposableDatabase::assertSafe();
try {
    DB::transaction(function () use ($argv): void {
        MemberWithdrawalControl::set(User::findOrFail((int) $argv[1]), (int) $argv[2], false, true);
        echo 'OFF_LOCKED'.PHP_EOL;
        flush();
        DB::select('SELECT pg_sleep(0.5)');
    });
    echo 'OFF_COMMITTED'.PHP_EOL;
} catch (Throwable $error) {
    echo get_class($error).': '.$error->getMessage().PHP_EOL;
    exit(1);
}
