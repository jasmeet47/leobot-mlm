<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

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
});

/*
|--------------------------------------------------------------------------
| Temporary BCMath Test - Admin Only
|--------------------------------------------------------------------------
*/

Route::middleware('admin')->get('/admin/test-bcmath', function () {

    if (!extension_loaded('bcmath')) {
        return response()->json([
            'success' => false,
            'message' => 'BCMath extension is not installed.',
        ], 500);
    }

    return response()->json([
        'success' => true,
        'bcmath_installed' => true,
        'addition' => bcadd('100.25', '50.75', 8),
        'subtraction' => bcsub('100.25', '50.75', 8),
        'comparison' => bccomp('100.25', '50.75', 8),
    ]);
});

/*
|--------------------------------------------------------------------------
| Temporary ADMIN Authentication Diagnostic
|--------------------------------------------------------------------------
| Requires a logged-in user.
| Does not expose passwords, keys, or user details.
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->get('/admin/check-auth', function () {

    $user = Auth::user();

    return response()->json([
        'authenticated' => true,
        'username_is_admin' =>
            strtoupper((string) $user->username) === 'ADMIN',
        'status_is_active' =>
            $user->status === 'active',
        'admin_access_allowed' =>
            strtoupper((string) $user->username) === 'ADMIN'
            && $user->status === 'active',
    ]);
});