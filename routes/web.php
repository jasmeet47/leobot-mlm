<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Admin\LevelConfigController;
use App\Http\Controllers\Admin\IncomeSettingsController;
use App\Http\Controllers\Admin\ReferralPrivacyController;
use App\Http\Controllers\Admin\MemberManagementController;

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
| Protected Member Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

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

Route::middleware('admin')->group(function () {
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
