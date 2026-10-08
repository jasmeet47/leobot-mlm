
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\P2PTransferController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\ActivationController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| These routes do not require authentication.
*/

Route::post('/verify-sponsor', [
    RegisterController::class,
    'verifySponsor',
]);

Route::post('/register', [
    RegisterController::class,
    'register',
]);

Route::post('/login', [
    LoginController::class,
    'login',
])->name('login');

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
| Authentication is required for all routes below.
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/p2p-transfer', [
        P2PTransferController::class,
        'transfer',
    ]);

    Route::post('/activate-user', [
        ActivationController::class,
        'activate',
    ]);

    Route::get('/dashboard-counters', [
        DashboardController::class,
        'getCounters',
    ]);

});
