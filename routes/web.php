
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
| Activation Temporarily Disabled
|--------------------------------------------------------------------------
| The old activation controller is not safe for financial
| transactions. Activation will be enabled only after
| authentication, wallet ledger, tree logic and security
| checks are completed.
*/

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware('admin')->group(function () {

    Route::get('/admin/level-config', [
        LevelConfigController::class,
        'index',
    ])->name('admin.levels.index');

    Route::post('/admin/level-config/update', [
        LevelConfigController::class,
        'update',
    ])->name('admin.levels.update');

});
