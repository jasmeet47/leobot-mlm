<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DemoSimulationController;
Route::middleware(['web','auth','account.not_blocked'])->group(function(){
 Route::get('/demo/simulated-wallet',[DemoSimulationController::class,'index'])->name('demo.simulation.index');
 Route::post('/demo/simulated-wallet/debit',[DemoSimulationController::class,'debit'])->middleware('throttle:10,1')->name('demo.simulation.debit');
 Route::post('/demo/simulated-wallet/credit',[DemoSimulationController::class,'credit'])->middleware(['admin','throttle:5,1'])->name('demo.simulation.credit');
});

// TEMPORARY admin-authenticated, demo-only, read-only runtime identity diagnostic.
\Illuminate\Support\Facades\Route::middleware(['web','auth','account.not_blocked','admin','throttle:3,1'])
 ->get('/demo/admin/runtime-identity-check', function () {
    if(config('app.url')!=='https://leobot-demo.onrender.com') abort(404);
    $db=\Illuminate\Support\Facades\DB::connection();
    if($db->getDriverName()!=='pgsql') abort(404);
    $url=(string)$db->getConfig('url');
    $host=$url?(string)parse_url($url,PHP_URL_HOST):(string)$db->getConfig('host');
    $name=$db->selectOne('select current_database() as name')->name;
    $ok=$host==='ep-shy-thunder-b4edgbov.c-6.us-east-2.aws.neon.tech'
       && $name==='neondb'
       && \Illuminate\Support\Facades\Schema::hasTable('demo_sim_wallets')
       && \Illuminate\Support\Facades\Schema::hasTable('demo_sim_ledger');
    return response()->json(['runtime_database_identity'=>$ok?'PASS':'FAIL',
      'database_write'=>'NO','beta_release'=>'HOLD'],$ok?200:409)
      ->header('Cache-Control','no-store');
 })->name('demo.admin.runtime-identity-check');