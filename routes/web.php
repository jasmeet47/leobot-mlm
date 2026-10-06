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
| Used only to verify database columns on the live PostgreSQL server.
| DELETE this route after testing.
|
*/

Route::get('/debug-db', function () {

    try {

        /*
        |--------------------------------------------------------------------------
        | Users table columns
        |--------------------------------------------------------------------------
        */

        $usersColumns = DB::select("
            SELECT
                column_name,
                data_type,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'users'
            ORDER BY ordinal_position
        ");

        /*
        |--------------------------------------------------------------------------
        | Member Teams table columns
        |--------------------------------------------------------------------------
        */

        $memberTeamsColumns = DB::select("
            SELECT
                column_name,
                data_type,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'member_teams'
            ORDER BY ordinal_position
        ");

        return response()->json([
            'success' => true,
            'database_connection' => DB::connection()->getDriverName(),
            'users_columns' => $usersColumns,
            'member_teams_columns' => $memberTeamsColumns,
        ], 200, [], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
        ], 500, [], JSON_UNESCAPED_UNICODE);

    }

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
    /*
|--------------------------------------------------------------------------
| Temporary Migration Debug
|--------------------------------------------------------------------------
*/

Route::get('/debug-migrations', function () {

    try {

        $migrations = DB::table('migrations')
            ->orderBy('batch')
            ->orderBy('migration')
            ->get();

        return response()->json([
            'success' => true,
            'migrations' => $migrations,
        ], 200, [], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
        ], 500, [], JSON_UNESCAPED_UNICODE);

    }

});