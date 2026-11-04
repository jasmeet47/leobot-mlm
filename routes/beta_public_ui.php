<?php

use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\MagicMatrixAdminController;
use App\Http\Controllers\Admin\MemberSafeDeleteController;
use App\Http\Controllers\Admin\RegistrationEmailOtpSettingsController;
use App\Http\Controllers\Member\MagicIncomeDetailsController;
use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Controllers\Member\MemberProfileController;
use App\Http\Controllers\Mt5DisplayController;
use App\Http\Middleware\EnsureMemberPin;
use Illuminate\Support\Facades\Route;

Route::get('/lumiflowon-brand-logo', [BrandingController::class, 'logo'])->name('lumiflowon.brand.logo');
Route::middleware(['auth', 'account.not_blocked', 'admin'])->group(function (): void {
    Route::get('/admin/logo-settings', [BrandingController::class, 'index'])->name('admin.lumiflowon.logo.index');
    Route::post('/admin/logo-settings', [BrandingController::class, 'update'])->middleware('throttle:10,1')->name('admin.lumiflowon.logo.save');
    Route::get('/admin/registration-email-otp-settings', [RegistrationEmailOtpSettingsController::class, 'index'])->name('admin.registration-otp.index');
    Route::post('/admin/registration-email-otp-settings', [RegistrationEmailOtpSettingsController::class, 'update'])->middleware('throttle:5,1')->name('admin.registration-otp.update');
    Route::get('/admin/mt5/view', [Mt5DisplayController::class, 'admin'])->name('admin.mt5.view');
    Route::post('/admin/members/{member}/safe-delete', [MemberSafeDeleteController::class, 'destroy'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.safe-delete');
    Route::post('/admin/magic-matrix/display-levels', [MagicMatrixAdminController::class, 'updateDisplayLevels'])->middleware('throttle:5,1')->name('admin.magic.display-levels');
});
Route::middleware(['auth', 'account.not_blocked', EnsureMemberPin::class])->group(function (): void {
    Route::get('/member/page/{panel}', [MemberDashboardController::class, 'index'])
        ->where('panel', 'team-breakdown|magic-team|direct-referrals|referral-privacy|income-summary|wallet-history|financial-records|member-profile|account-settings|member-support')->name('member.detail');
    Route::get('/member/profile', [MemberProfileController::class, 'index'])->name('member.profile');
    Route::post('/member/profile/password', [MemberProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('member.profile.password');
    Route::get('/member/magic-income/details', [MagicIncomeDetailsController::class, 'index'])->name('member.magic-income.details');
});
