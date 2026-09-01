<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoldSlotController;
use App\Http\Controllers\SlotVaultController;
use App\Http\Controllers\SettingsController;

// Clean Navigation Routes
Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Hold Slot Routes
Route::get('/hold', [HoldSlotController::class, 'index'])->name('hold');
Route::get('/hold/available-dates', [HoldSlotController::class, 'getAvailableDates'])->name('hold.available_dates');
Route::post('/hold/scan', [HoldSlotController::class, 'scanSlots'])->name('hold.scan');
Route::get('/hold/token-status', [HoldSlotController::class, 'getTokenStatus'])->name('hold.token_status');
Route::post('/hold/auto-login', [HoldSlotController::class, 'autoLoginPoolCandidate'])->name('hold.auto_login');
Route::get('/hold/auto-login-logs', [HoldSlotController::class, 'getAutoLoginLogs'])->name('hold.auto_login_logs');

// Vault Routes
Route::get('/vault', [SlotVaultController::class, 'index'])->name('vault');

// Settings Routes
Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
Route::post('/settings/pool/add', [SettingsController::class, 'addPoolAccount'])->name('settings.pool.add');
Route::post('/settings/pool/remove', [SettingsController::class, 'removePoolAccount'])->name('settings.pool.remove');
Route::post('/settings/update', [SettingsController::class, 'updateSettings'])->name('settings.update');
