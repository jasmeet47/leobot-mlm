<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DemoSimulationController;
Route::middleware(['web','auth','account.not_blocked'])->group(function(){
 Route::get('/demo/simulated-wallet',[DemoSimulationController::class,'index'])->name('demo.simulation.index');
 Route::post('/demo/simulated-wallet/debit',[DemoSimulationController::class,'debit'])->middleware('throttle:10,1')->name('demo.simulation.debit');
 Route::post('/demo/simulated-wallet/credit',[DemoSimulationController::class,'credit'])->middleware(['admin','throttle:5,1'])->name('demo.simulation.credit');
});
