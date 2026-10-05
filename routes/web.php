<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Admin\LevelConfigController;


/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
|
| Main website URL:
| https://leobot-mlm-1.onrender.com/
|
*/

Route::get('/', [RegisterController::class, 'showRegistrationForm'])
    ->name('home');


/*
|--------------------------------------------------------------------------
| JOIN / REGISTRATION PAGE
|--------------------------------------------------------------------------
|
| Example:
| /join
| /join?ref=TX123456
|
*/

Route::get('/join', [RegisterController::class, 'showRegistrationForm'])
    ->name('register.form');


/*
|--------------------------------------------------------------------------
| REGISTER
|--------------------------------------------------------------------------
*/

Route::post('/register', [RegisterController::class, 'register'])
    ->name('register.submit');


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

Route::post('/login', [RegisterController::class, 'login'])
    ->name('login.submit');


/*
|--------------------------------------------------------------------------
| USER ACTIVATION
|--------------------------------------------------------------------------
*/

Route::post('/activate-user', [ActivationController::class, 'activate'])
    ->name('activate.user');


/*
|--------------------------------------------------------------------------
| DASHBOARD COUNTERS
|--------------------------------------------------------------------------
*/

Route::get('/check-dashboard', [DashboardController::class, 'getCounters'])
    ->name('dashboard.counters');


/*
|--------------------------------------------------------------------------
| ADMIN LEVEL CONFIGURATION
|--------------------------------------------------------------------------
*/

Route::get('/admin/level-config', [LevelConfigController::class, 'index'])
    ->name('admin.levels.index');


Route::post('/admin/level-config/update', [LevelConfigController::class, 'update'])
    ->name('admin.levels.update');