<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;

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
// 🟢 51-लेवल जेनरेशन साइनअप और रजिस्ट्रेशन के असली रास्ते
Route::get('/join', [RegisterController::class, 'showRegistrationForm'])->name('register.form');
Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');

// इसे बिना किसी ग्रुप के, सीधे web.php की सबसे आखिरी लाइन पर पेस्ट करके Ctrl+S दबा दें!
Route::get('/admin/level-config', [\App\Http\Controllers\Admin\LevelConfigController::class, 'index']);
Route::post('/admin/level-config/update', [\App\Http\Controllers\Admin\LevelConfigController::class, 'update'])->name('admin.levels.update');
