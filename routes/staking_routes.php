<?php

use App\Http\Controllers\Admin\StakingControlController;
use App\Http\Controllers\Admin\StakingProfitController;
use App\Http\Controllers\Member\StakingController;
use App\Http\Controllers\TradingProfitReviewController;
use App\Http\Middleware\EnsureMemberPin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.not_blocked'])->group(function () {
    Route::get('/reviewer/trading-profit', [TradingProfitReviewController::class, 'index'])->middleware(EnsureMemberPin::class)->name('reviewer.trading-profit.index');
    Route::post('/reviewer/trading-profit/{id}', [TradingProfitReviewController::class, 'attest'])->whereNumber('id')->middleware(['throttle:5,1', EnsureMemberPin::class])->name('reviewer.trading-profit.attest');
    Route::get('/member/staking', [StakingController::class, 'index'])->middleware(EnsureMemberPin::class)->name('member.staking.index');
    Route::post('/member/staking/{id}/withdraw', [StakingController::class, 'withdraw'])->whereNumber('id')->middleware(['throttle:5,1', EnsureMemberPin::class])->name('member.staking.withdraw');
    Route::post('/member/staking', [StakingController::class, 'store'])->middleware(['throttle:10,1', EnsureMemberPin::class])->name('member.staking.store');
});
Route::middleware(['auth', 'account.not_blocked', 'admin'])->group(function () {
    Route::post('/admin/staking/profit/reviewers', [TradingProfitReviewController::class, 'permission'])->middleware('throttle:5,1')->name('admin.staking.profit.reviewers');
    Route::get('/admin/staking', [StakingControlController::class, 'index'])->name('admin.staking.index');
    Route::get('/admin/staking/profit', [StakingProfitController::class, 'index'])->name('admin.staking.profit.index');
    Route::post('/admin/staking/profit', [StakingProfitController::class, 'store'])->middleware('throttle:5,1')->name('admin.staking.profit.store');
    Route::post('/admin/staking/profit/{id}/reserve-reviewed', [StakingProfitController::class, 'reserve'])->whereNumber('id')->middleware('throttle:5,1')->name('admin.staking.profit.reserve');
    Route::post('/admin/staking/profit/{id}/approve', [StakingProfitController::class, 'approve'])->whereNumber('id')->middleware('throttle:5,1')->name('admin.staking.profit.approve');
    Route::post('/admin/staking/toggle', [StakingControlController::class, 'update'])->middleware('throttle:5,1')->name('admin.staking.toggle');
});
