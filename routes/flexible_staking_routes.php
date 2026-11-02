<?php
// STAGING ONLY. Not included from web.php until isolated tests and release approval.
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','account.not_blocked',\App\Http\Middleware\EnsureMemberPin::class,'throttle:5,1'])->post(
    '/member/staking/unstake', [\App\Http\Controllers\Member\FlexibleUnstakeController::class,'store']
)->name('member.staking.unstake');
Route::middleware(['auth','account.not_blocked','admin','throttle:5,1'])->group(function () {
    Route::post('/admin/staking/unstake/{id}/approve', [\App\Http\Controllers\Admin\FlexibleUnstakeApprovalController::class,'approve'])->whereNumber('id')->name('admin.staking.unstake.approve');
    Route::post('/admin/staking/unstake/{id}/reject', [\App\Http\Controllers\Admin\FlexibleUnstakeApprovalController::class,'reject'])->whereNumber('id')->name('admin.staking.unstake.reject');
});
