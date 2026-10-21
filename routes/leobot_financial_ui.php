<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Member\DepositTransferController;
use App\Http\Controllers\Admin\DepositReviewController;
Route::middleware(['auth','account.not_blocked',\App\Http\Middleware\EnsureMemberPin::class])->group(function(){
 Route::get('/member/deposit-transfer',[DepositTransferController::class,'index'])->name('member.deposit-transfer.index');
 Route::post('/member/deposit-request',[DepositTransferController::class,'deposit'])->middleware('throttle:3,1')->name('member.deposit-request.store');
 Route::post('/member/fund-transfer',[DepositTransferController::class,'transfer'])->middleware('throttle:3,1')->name('member.fund-transfer.store');
});
Route::middleware(['auth','account.not_blocked','admin'])->group(function(){
 Route::get('/admin/deposit-review',[DepositReviewController::class,'index'])->name('admin.deposit-review.index');
 Route::post('/admin/deposit-review/{id}/approve',[DepositReviewController::class,'approve'])->whereNumber('id')->middleware('throttle:3,1')->name('admin.deposit-review.approve');
 Route::post('/admin/deposit-review/{id}/reject',[DepositReviewController::class,'reject'])->whereNumber('id')->middleware('throttle:3,1')->name('admin.deposit-review.reject');
});
