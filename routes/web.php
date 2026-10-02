<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\DashboardController;

// डिफ़ॉल्ट वेलकम पेज (जो अभी आपको दिख रहा है)
Route::get('/', function () {
    return view('welcome');
});

// बिना टोकन के सीधे गूगल क्रोम ब्राउज़र पर डैशबोर्ड काउंटर्स देखने का लाइव रास्ता
Route::get('/check-dashboard', [DashboardController::class, 'getCounters']);
use App\Http\Controllers\Auth\ActivationController;

Route::post('/activate-user', [ActivationController::class, 'activate']); // <--- इस लाइन को यहाँ से हटा दें
Route::get('/join', function () {
    return view('auth-page');
});