<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\LevelConfigController;
use App\Http\Controllers\Auth\ActivationController;
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
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/check-dashboard', [
    DashboardController::class,
    'getCounters',
])->name('dashboard.counters');

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
| User Activation
|--------------------------------------------------------------------------
*/

Route::post('/activate-user', [
    ActivationController::class,
    'activate',
])->name('activate.user');

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

    /*
    |--------------------------------------------------------------------------
    | Temporary Production DB Schema Check
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/debug-users-schema', function () {

        $columns = DB::table('information_schema.columns')
            ->select([
                'column_name',
                'data_type',
                'is_nullable',
                'column_default',
            ])
            ->where('table_schema', 'public')
            ->where('table_name', 'users')
            ->orderBy('ordinal_position')
            ->get();

        return response()->json([
            'success' => true,
            'columns' => $columns,
        ]);
    });
});