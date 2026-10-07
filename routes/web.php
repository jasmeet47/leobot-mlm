<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Admin\LevelConfigController;

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
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/check-dashboard', [
    DashboardController::class,
    'getCounters'
])->name('dashboard.counters');

/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

Route::get('/join', [
    RegisterController::class,
    'showRegistrationForm'
])->name('register.form');

Route::post('/register', [
    RegisterController::class,
    'register'
])->name('register.submit');

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

Route::post('/login', [
    RegisterController::class,
    'login'
])->name('login.submit');

/*
|--------------------------------------------------------------------------
| User Activation
|--------------------------------------------------------------------------
*/

Route::post('/activate-user', [
    ActivationController::class,
    'activate'
])->name('activate.user');

/*
|--------------------------------------------------------------------------
| Admin - 51 Level Configuration
|--------------------------------------------------------------------------
*/

Route::middleware('admin')->group(function () {

    Route::get('/admin/level-config', [
        LevelConfigController::class,
        'index'
    ])->name('admin.levels.index');

    Route::post('/admin/level-config/update', [
        LevelConfigController::class,
        'update'
    ])->name('admin.levels.update');
});
