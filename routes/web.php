<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Admin\LevelConfigController;
use App\Http\Controllers\Admin\IncomeSettingsController;
use App\Http\Controllers\Admin\ReferralPrivacyController;
use App\Http\Controllers\Admin\MemberManagementController;
use App\Http\Controllers\Admin\MemberBlockController;
use App\Http\Controllers\Admin\UnifiedFundingController;

use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SecurityPinController;

use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Controllers\Member\ReferralContactConsentController;
use App\Http\Middleware\EnsureMemberPin;

/*
|--------------------------------------------------------------------------
| Homepage
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

Route::get('/join', [
    RegisterController::class,
    'showRegistrationForm',
])->name('register.form');

Route::post('/register', [
    RegisterController::class,
    'register',
])->name('register.submit');

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

Route::post('/login', [
    RegisterController::class,
    'login',
])
    ->middleware('throttle:5,1')
    ->name('login.submit');

/*
|--------------------------------------------------------------------------
| Email OTP reset disabled: fail closed
| Named routes retained for existing Blade references
|--------------------------------------------------------------------------
*/
Route::get('/forgot-password', function () {
    abort(404);
})->name('password.otp.request');

Route::post('/forgot-password', function () {
    abort(404);
})->name('password.otp.send');

Route::get('/reset-password-otp', function () {
    abort(404);
})->name('password.otp.form');

Route::post('/reset-password-otp', function () {
    abort(404);
})->name('password.otp.reset');

/*
|--------------------------------------------------------------------------
| Protected Member Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'account.not_blocked'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Protected Dashboard
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | Member Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/member/dashboard', [
        MemberDashboardController::class,
        'index',
    ])
        ->middleware(EnsureMemberPin::class)
        ->name('member.dashboard');

    /*
    |--------------------------------------------------------------------------
    | Referral Contact Consent
    |--------------------------------------------------------------------------
    */

    Route::post('/member/referral-contact-consent', [
        ReferralContactConsentController::class,
        'update',
    ])
        ->middleware([EnsureMemberPin::class, 'throttle:10,1'])
        ->name('member.referral-contact-consent.update');

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register.form');
    })->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Security PIN
    |--------------------------------------------------------------------------
    */

    Route::get('/security-pin', function () {
        return view('security-pin');
    })->name('security-pin.form');

    Route::post('/security-pin', [
        SecurityPinController::class,
        'update',
    ])
        ->middleware('throttle:10,1')
        ->name('security-pin.update');

});

/*
|--------------------------------------------------------------------------
| Financial Operations Temporarily Disabled
|--------------------------------------------------------------------------
|
| Activation and P2P transfers remain disabled until
| wallet, ledger and commission security tests are complete.
|
*/

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'account.not_blocked', 'admin'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Main Admin Dashboard
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Read-Only Member Management
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/members', [
        MemberManagementController::class,
        'index',
    ])->name('admin.members.index');

    Route::get('/admin/members/{member}', [
        MemberManagementController::class,
        'show',
    ])->whereNumber('member')->name('admin.members.show');

    Route::get('/admin/members/{member}/referrals', [
        MemberManagementController::class,
        'referrals',
    ])->whereNumber('member')->name('admin.members.referrals');

    Route::get('/admin/members/{member}/team-levels', [
        MemberManagementController::class,
        'teamLevels',
    ])->whereNumber('member')->name('admin.members.team-levels');

    Route::post('/admin/members/{member}/block', [
        MemberBlockController::class,
        'block',
    ])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.block');

    Route::post('/admin/members/{member}/unblock', [
        MemberBlockController::class,
        'unblock',
    ])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.unblock');
    // Reserve-only unified funding audit; write access is independently gated OFF by default.
    Route::get('/admin/unified-funding', [UnifiedFundingController::class, 'index'])
        ->name('admin.unified-funding.index');
    Route::post('/admin/unified-funding', [UnifiedFundingController::class, 'store'])
        ->middleware('throttle:5,1')->name('admin.unified-funding.store');
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');


    /*
    |--------------------------------------------------------------------------
    | 51-Level Commission Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/level-config', [
        LevelConfigController::class,
        'index',
    ])->name('admin.levels.index');

    Route::post('/admin/level-config/update', [
        LevelConfigController::class,
        'update',
    ])->middleware('throttle:10,1')->name('admin.levels.update');

    /*
    |--------------------------------------------------------------------------
    | 51-Level Audit History
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/level-config/history', [
        LevelConfigController::class,
        'history',
    ])->name('admin.levels.history');

    /*
    |--------------------------------------------------------------------------
    | ROI and Magic Income Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/income-settings', [
        IncomeSettingsController::class,
        'index',
    ])->name('admin.income.index');

    Route::post('/admin/income-settings/update', [
        IncomeSettingsController::class,
        'update',
    ])
        ->middleware('throttle:10,1')
        ->name('admin.income.update');

    /*
    |--------------------------------------------------------------------------
    | ROI and Magic Income Audit History
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/income-settings/history', [
        IncomeSettingsController::class,
        'history',
    ])->name('admin.income.history');

    /*
    |--------------------------------------------------------------------------
    | Direct Referral Privacy Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/referral-privacy', [
        ReferralPrivacyController::class,
        'index',
    ])->name('admin.referral-privacy.index');

    Route::post('/admin/referral-privacy/update', [
        ReferralPrivacyController::class,
        'update',
    ])
        ->middleware('throttle:10,1')
        ->name('admin.referral-privacy.update');

});

/*
|--------------------------------------------------------------------------
| LeoBot Option A: staging-only audited finance UI. Every WRITE is gated.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'account.not_blocked', 'admin'])->group(function () {
    Route::get('/admin/magic-matrix', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'index'])
        ->name('admin.magic.index');
    Route::post('/admin/magic-matrix/policy', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'updatePolicy'])
        ->middleware('throttle:5,1')->name('admin.magic.policy');
    Route::post('/admin/magic-matrix/rates', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'updateRates'])
        ->middleware('throttle:5,1')->name('admin.magic.rates');
    Route::post('/admin/magic-matrix/switches', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'updateSwitches'])
        ->middleware('throttle:5,1')->name('admin.magic.switches');
    Route::post('/admin/magic-matrix/settle', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'settle'])
        ->middleware('throttle:3,1')->name('admin.magic.settle');
    Route::get('/admin/withdrawals', [\App\Http\Controllers\Admin\WithdrawalAdminController::class, 'index'])
        ->name('admin.withdrawals.index');
    Route::post('/admin/withdrawals/{id}/approve', [\App\Http\Controllers\Admin\WithdrawalAdminController::class, 'approve'])
        ->whereNumber('id')->middleware('throttle:5,1')->name('admin.withdrawals.approve');
    Route::post('/admin/withdrawals/{id}/reject', [\App\Http\Controllers\Admin\WithdrawalAdminController::class, 'reject'])
        ->whereNumber('id')->middleware('throttle:5,1')->name('admin.withdrawals.reject');
    Route::post('/admin/withdrawals/fee', [\App\Http\Controllers\Admin\WithdrawalAdminController::class, 'updateFee'])
        ->middleware('throttle:5,1')->name('admin.withdrawals.fee');
});
Route::middleware(['auth','account.not_blocked',\App\Http\Middleware\EnsureMemberPin::class])->group(function () {
    Route::get('/member/withdrawals', [\App\Http\Controllers\Member\WithdrawalMemberController::class, 'index'])
        ->name('member.withdrawals.index');
    Route::post('/member/withdrawals', [\App\Http\Controllers\Member\WithdrawalMemberController::class, 'store'])
        ->middleware('throttle:3,1')->name('member.withdrawals.store');
});

// LEOBOT_AUTH_DIAGNOSTIC_V1
// TEMPORARY: remove after production authentication diagnosis.
\Route::middleware(['web', 'auth'])->get(
    '/internal/auth-diagnostic',
    function (\Illuminate\Http\Request $request) {
        $user = $request->user();

        $role = (string) ($user?->role ?? '');
        $status = (string) ($user?->status ?? '');

        return response()->json([
            'authenticated' => $user !== null,
            'admin_role' => in_array($role, ['admin', 'super_admin'], true),
            'active' => $status === 'active',
            'blocked' => (bool) ($user?->is_blocked ?? true),
            'session_version_matches' =>
                (int) ($user?->member_login_version ?? -1)
                === (int) $request->session()->get('member_login_version', -2),
        ])->header('Cache-Control', 'no-store');
    }
)->name('internal.auth-diagnostic');

