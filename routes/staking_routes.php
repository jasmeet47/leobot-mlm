<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','account.not_blocked'])->group(function(){
 Route::post('/member/staking', [\App\Http\Controllers\Member\StakingController::class,'store'])->middleware(['throttle:10,1',\App\Http\Middleware\EnsureMemberPin::class])->name('member.staking.store');
});
Route::middleware(['auth','account.not_blocked','admin'])->group(function(){
 Route::post('/admin/staking/toggle',[\App\Http\Controllers\Admin\StakingControlController::class,'update'])->middleware('throttle:5,1')->name('admin.staking.toggle');
});
