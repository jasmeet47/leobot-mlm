<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LevelConfigController;
use App\Http\Controllers\Admin\IncomeSettingsController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Member\MemberDashboardController;
use App\Http\Middleware\EnsureMemberPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SecurityPinController;

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
])->name('login.submit');

/*
|--------------------------------------------------------------------------
| Protected Member Routes
|--------------------------------------------------------------------------
|
| Only authenticated users can access these routes.
|
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Protected Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/check-dashboard', [
        DashboardController::class,
        'getCounters',
    ])->middleware(EnsureMemberPin::class)->name('dashboard.counters');

    // Member-only read-only dashboard; security PIN must already be set.
    Route::get('/member/dashboard', [
        MemberDashboardController::class,
        'index',
    ])->middleware(EnsureMemberPin::class)->name('member.dashboard');

    // End the session safely (POST + CSRF; not a GET logout link).
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('register.form');
    })->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Security PIN Page
    |--------------------------------------------------------------------------
    |
    | GET: Show Security PIN form.
    | POST: Verify account password and save hashed PIN.
    |
    | Both routes require authentication.
    | POST route has CSRF protection through web middleware.
    |
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
    ])->name('admin.levels.update');

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

    /*
    |--------------------------------------------------------------------------
    | Save ROI and Magic Income Settings
    |--------------------------------------------------------------------------
    |
    | Admin protected.
    | CSRF protected.
    | Rate limited.
    |
    */

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

});
