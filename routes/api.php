<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Middleware\EnsureMemberPin;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| Registration, login and sponsor verification.
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
| Authentication required.
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/dashboard-counters', [
        DashboardController::class,
        'getCounters',
    ])->middleware(EnsureMemberPin::class);

});

/*
|--------------------------------------------------------------------------
| Financial Operations Temporarily Disabled
|--------------------------------------------------------------------------
| POST /api/p2p-transfer
| POST /api/activate-user
|
| These endpoints will be restored only after:
| - Secure authenticated sender verification
| - Hashed security PIN verification
| - Atomic wallet transactions
| - Financial ledger recording
| - 51-level commission and capping validation
| - Automated security tests
|--------------------------------------------------------------------------
*/
