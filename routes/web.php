<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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
|
| Temporary live API for testing dashboard counters.
|
*/

Route::get('/check-dashboard', [DashboardController::class, 'getCounters'])
    ->name('dashboard.counters');


/*
|--------------------------------------------------------------------------
| Temporary Database Debug
|--------------------------------------------------------------------------
|
| Used only to verify database columns on the live server.
| DELETE this route after testing.
|
*/

Route::get('/debug-db', function () {

    return response()->json([
        'success' => true,

        'users_columns' => DB::select(
            'SHOW COLUMNS FROM users'
        ),

        'member_teams_columns' => DB::select(
            'SHOW COLUMNS FROM member_teams'
        ),
    ], 200, [], JSON_UNESCAPED_UNICODE);

});


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

Route::get('/admin/level-config', [
    LevelConfigController::class,
    'index'
])->name('admin.levels.index');


Route::post('/admin/level-config/update', [
    LevelConfigController::class,
    'update'
])->name('admin.levels.update');