<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PassengerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/passengers/{id}/check-otp', [PassengerController::class, 'apiCheckOtp']);
Route::get('/passengers/{id}/otp-jsonp', [PassengerController::class, 'apiCheckOtpJsonp']);
Route::get('/solve-captcha', [PassengerController::class, 'apiSolveCaptcha']);
Route::get('/captcha-jsonp', [PassengerController::class, 'apiSolveCaptchaJsonp']);

Route::post('/telegram/webhook', [App\Http\Controllers\TelegramWebhookController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Decoupled Slot Holder Engine API Routes (Server-to-Server Communication)
Route::prefix('v1/engine')->group(function () {
    Route::post('/handshake', [\App\Http\Controllers\Api\SlotEngineApiController::class, 'handshake']);
    Route::post('/hold-slots', [\App\Http\Controllers\Api\SlotEngineApiController::class, 'executeRemoteHold']);
    Route::post('/scan-slots', [\App\Http\Controllers\Api\SlotEngineApiController::class, 'executeRemoteScan']);
});
