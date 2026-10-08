<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\LevelConfigController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\RegisterController;

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
| Protected Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/check-dashboard', [
        DashboardController::class,
        'getCounters',
    ])->name('dashboard.counters');

});

/*
|--------------------------------------------------------------------------
| Financial Operations Temporarily Disabled
|--------------------------------------------------------------------------
| Activation and P2P transfers remain disabled until
| wallet, ledger and commission security tests are complete.
*/

/*
|--------------------------------------------------------------------------
| Protected Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware('admin')->group(function () {

    /*
     * View 51-Level Commission Settings
     */
    Route::get('/admin/level-config', [
        LevelConfigController::class,
        'index',
    ])->name('admin.levels.index');

    /*
     * Update 51-Level Commission Settings
     */
    Route::post('/admin/level-config/update', [
        LevelConfigController::class,
        'update',
    ])->name('admin.levels.update');

    /*
     * View Admin Audit History
     * Only authorized admins can access this route.
     */
    Route::get('/admin/level-config/history', [
        LevelConfigController::class,
        'history',
    ])->name('admin.levels.history');

});
