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

/* LEOBOT_GET_LOGIN_REDIRECT_V1 */
Route::get('/login', function () {
    return redirect()->route('register.form');
})->name('login.form');
Route::post('/login', [
    RegisterController::class,
    'login',
])
    ->middleware(\App\Http\Middleware\MeasureLoginRequest::class)
    ->middleware('throttle:5,1')
    ->name('login.submit');

/*
|--------------------------------------------------------------------------
| Email OTP reset disabled: fail closed
| Named routes retained for existing Blade references
|--------------------------------------------------------------------------
*/
Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'requestForm'])->name('password.otp.request');
Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'send'])->middleware('throttle:3,1')->name('password.otp.send');
Route::get('/reset-password-otp', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'resetForm'])->name('password.otp.form');
Route::post('/reset-password-otp', [\App\Http\Controllers\Auth\PasswordOtpController::class, 'reset'])->middleware('throttle:5,1')->name('password.otp.reset');

/*
|--------------------------------------------------------------------------
| Protected Member Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'account.not_blocked'])->group(function () {
    // Read-only 51-level member viewer: authenticated user is the only root.
    Route::get('/member/team-levels', [\App\Http\Controllers\Member\MemberTeamViewerController::class, 'index'])
        ->middleware(\App\Http\Middleware\EnsureMemberPin::class)
        ->name('member.team-levels');

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
    // TEMPORARY DEMO-ONLY DATABASE IDENTITY DIAGNOSTIC
    Route::get('/admin/demo-db-identity', function () {
        abort_unless(
            rtrim((string) config('app.url'), '/') ===
                'https://leobot-demo.onrender.com',
            404
        );

        $connection = \Illuminate\Support\Facades\DB::connection();

        abort_unless(
            $connection->getDriverName() === 'pgsql',
            503
        );

        $connection->getPdo();

        $host = strtolower(trim(
            (string) $connection->getConfig('host')
        ));

        abort_unless(
            config('app.env') !== 'production'
            && $host === 'ep-shy-thunder-b4edgbov.c-6.us-east-2.aws.neon.tech',
            503
        );

        $db = $connection->selectOne(
            'SELECT current_database() AS database_name'
        );

        return response()->json([
            'audit' => 'LUMIFLOWON_DEMO_DB_V1',
            'driver' => 'pgsql',
            'query' => $db ? 'PASS' : 'FAIL',
            'host_sha256' => hash('sha256', $host),
            'database_sha256' => hash(
                'sha256',
                (string) $db->database_name
            ),
        ])->header('Cache-Control', 'no-store');
    })->middleware('throttle:5,1');

    Route::get('/admin/dashboard-quick-links', [\App\Http\Controllers\DashboardQuickLinksController::class, 'index'])->name('admin.quick-links.index');
    Route::post('/admin/dashboard-quick-links', [\App\Http\Controllers\DashboardQuickLinksController::class, 'update'])->middleware('throttle:10,1')->name('admin.quick-links.update');
    Route::get('/admin/mt5/view', [\App\Http\Controllers\Mt5DisplayController::class, 'admin'])->name('admin.mt5.view');
    Route::post('/admin/mt5/toggle', [\App\Http\Controllers\Mt5DisplayController::class, 'toggle'])->middleware('throttle:5,1')->name('admin.mt5.toggle');
    Route::get('/admin/registration-email-otp-settings', [\App\Http\Controllers\Admin\RegistrationEmailOtpSettingsController::class, 'index'])->name('admin.registration-otp.index');
    Route::post('/admin/registration-email-otp-settings', [\App\Http\Controllers\Admin\RegistrationEmailOtpSettingsController::class, 'update'])->middleware('throttle:5,1')->name('admin.registration-otp.update');
    Route::post('/admin/members/{member}/soft-delete', [\App\Http\Controllers\Admin\MemberRetirementController::class, 'retire'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.soft-delete');
    Route::post('/admin/members/{member}/restore', [\App\Http\Controllers\Admin\MemberRetirementController::class, 'restore'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.restore');
    Route::post('/admin/members/{member}/safe-delete', [\App\Http\Controllers\Admin\MemberSafeDeleteController::class, 'destroy'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.safe-delete');
    Route::get('/admin/password-otp-settings', [\App\Http\Controllers\Admin\PasswordOtpSettingsController::class, 'index'])->name('admin.password-otp.index');
    Route::post('/admin/password-otp-settings', [\App\Http\Controllers\Admin\PasswordOtpSettingsController::class, 'update'])->middleware('throttle:5,1')->name('admin.password-otp.update');

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

    Route::get('/admin/dashboard-quick-links', [\App\Http\Controllers\DashboardQuickLinksController::class, 'index'])->name('admin.quick-links.index');
    Route::post('/admin/dashboard-quick-links', [\App\Http\Controllers\DashboardQuickLinksController::class, 'update'])->middleware('throttle:10,1')->name('admin.quick-links.update');
    Route::get('/admin/mt5/view', [\App\Http\Controllers\Mt5DisplayController::class, 'admin'])->name('admin.mt5.view');
    Route::post('/admin/mt5/toggle', [\App\Http\Controllers\Mt5DisplayController::class, 'toggle'])->middleware('throttle:5,1')->name('admin.mt5.toggle');
    Route::get('/admin/registration-email-otp-settings', [\App\Http\Controllers\Admin\RegistrationEmailOtpSettingsController::class, 'index'])->name('admin.registration-otp.index');
    Route::post('/admin/registration-email-otp-settings', [\App\Http\Controllers\Admin\RegistrationEmailOtpSettingsController::class, 'update'])->middleware('throttle:5,1')->name('admin.registration-otp.update');
    Route::post('/admin/members/{member}/soft-delete', [\App\Http\Controllers\Admin\MemberRetirementController::class, 'retire'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.soft-delete');
    Route::post('/admin/members/{member}/restore', [\App\Http\Controllers\Admin\MemberRetirementController::class, 'restore'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.restore');
    Route::post('/admin/members/{member}/safe-delete', [\App\Http\Controllers\Admin\MemberSafeDeleteController::class, 'destroy'])->whereNumber('member')->middleware('throttle:5,1')->name('admin.members.safe-delete');
    Route::get('/admin/magic-matrix', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'index'])
        ->name('admin.magic.index');
    Route::post('/admin/magic-matrix/display-levels', [\App\Http\Controllers\Admin\MagicMatrixAdminController::class, 'updateDisplayLevels'])
        ->middleware('throttle:5,1')->name('admin.magic.display-levels');
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
    Route::get('/member/usdt-wallets/page', function () {
        return view('member.usdt-wallets');
    })->name('member.usdt-wallets.page');
    Route::get('/member/usdt-wallets',
        [\App\Http\Controllers\Member\UsdtWalletController::class, 'index'])
        ->middleware('throttle:30,1')->name('member.usdt-wallets.index');

    Route::post('/member/usdt-wallets/challenge',
        [\App\Http\Controllers\Member\UsdtWalletController::class, 'challenge'])
        ->middleware('throttle:5,1')->name('member.usdt-wallets.challenge');

    Route::post('/member/usdt-wallets/verify',
        [\App\Http\Controllers\Member\UsdtWalletController::class, 'verify'])
        ->middleware('throttle:5,1')->name('member.usdt-wallets.verify');
    Route::get('/member/withdrawals', [\App\Http\Controllers\Member\WithdrawalMemberController::class, 'index'])
        ->name('member.withdrawals.index');
    Route::post('/member/withdrawals', [\App\Http\Controllers\Member\WithdrawalMemberController::class, 'store'])
        ->middleware('throttle:3,1')->name('member.withdrawals.store');
});


/* LumiFlowOn: authenticated read-only Magic Income Details */
Route::middleware([
    'auth',
    'account.not_blocked',
    \App\Http\Middleware\EnsureMemberPin::class
])->get(
    '/member/magic-income/details',
    [\App\Http\Controllers\Member\MagicIncomeDetailsController::class, 'index']
)->name('member.magic-income.details');
Route::post('/registration-email-otp/send', [\App\Http\Controllers\Auth\RegistrationEmailOtpController::class, 'send'])->middleware(['web','throttle:5,1'])->name('registration-otp.send');
Route::post('/registration-email-otp/verify', [\App\Http\Controllers\Auth\RegistrationEmailOtpController::class, 'verify'])->middleware(['web','throttle:10,1'])->name('registration-otp.verify');
Route::middleware(['auth', 'account.not_blocked', \App\Http\Middleware\EnsureMemberPin::class])->group(function () {
    Route::get('/member/mt5/view', [\App\Http\Controllers\Mt5DisplayController::class, 'member'])->name('member.mt5.view');
});
require __DIR__.'/leobot_financial_ui.php';

