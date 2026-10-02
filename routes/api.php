<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\P2PTransferController;

// सभी रूट्स को सार्वजनिक (Public) कर दें ताकि सीधे बिना टोकन टेस्ट हो सके
Route::post('/verify-sponsor', [RegisterController::class, 'verifySponsor']);
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login'])->name('login');

// P2P ट्रांसफर को सुरक्षित घेरे से बाहर निकाल दिया है
Route::post('/p2p-transfer', [P2PTransferController::class, 'transfer']);
// केवल लॉगिन टोकन वाले सुरक्षित रूट्स (Protected APIs)
Route::middleware('auth:sanctum')->group(function () {
    
    // P2P फंड ट्रांसफर रूट अब पूरी तरह सुरक्षित है
    Route::post('/p2p-transfer', [P2PTransferController::class, 'transfer']);
    
});
use App\Http\Controllers\Auth\DashboardController;

// केवल लॉगिन टोकन वाले सुरक्षित रूट्स (Protected APIs)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/p2p-transfer', [P2PTransferController::class, 'transfer']);
    
    // डैशबोर्ड के एक्टिव/इनएक्टिव और 41 लेवल काउंटर्स देखने की एपीआई
    Route::get('/dashboard-counters', [DashboardController::class, 'getCounters']);
});
Route::post('/activate-user', [\App\Http\Controllers\Auth\ActivationController::class, 'activate']);
