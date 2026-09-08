<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HoldSlotController;
use App\Http\Controllers\SlotVaultController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AutoLoginCheckerController;
use App\Http\Controllers\IpManagerController;

// Authentication Routes (Public with Brute-Force Rate Limiting)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Application Protected Routes (Auth Required)
Route::middleware(['auth'])->group(function () {
    
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Hold Slot Routes
    Route::get('/hold', [HoldSlotController::class, 'index'])->name('hold');
    Route::get('/hold/available-dates', [HoldSlotController::class, 'getAvailableDates'])->name('hold.available_dates');
    Route::post('/hold/scan', [HoldSlotController::class, 'scanSlots'])->name('hold.scan');
    Route::post('/hold/lock-slots', [HoldSlotController::class, 'lockAllSlots'])->name('hold.lock_slots');
    Route::get('/hold/token-status', [HoldSlotController::class, 'getTokenStatus'])->name('hold.token_status');
    Route::post('/hold/auto-login', [HoldSlotController::class, 'autoLoginPoolCandidate'])->name('hold.auto_login');
    Route::get('/hold/auto-login-logs', [HoldSlotController::class, 'getAutoLoginLogs'])->name('hold.auto_login_logs');

    // Vault Routes
    Route::get('/vault', [SlotVaultController::class, 'index'])->name('vault');
    Route::get('/vault/data', [SlotVaultController::class, 'getVaultData'])->name('vault.data');
    Route::post('/vault/release-single', [SlotVaultController::class, 'releaseSingleSlot'])->name('vault.release_single');
    Route::post('/vault/release-group', [SlotVaultController::class, 'releaseGroupSlots'])->name('vault.release_group');

    // Auto Login Checker (Zero Captcha Payload Engine)
    Route::get('/auto-login', [AutoLoginCheckerController::class, 'index'])->name('auto_login');
    Route::post('/auto-login/execute', [AutoLoginCheckerController::class, 'executeLogin'])->name('auto_login.execute');
    Route::post('/auto-login/check-token', [AutoLoginCheckerController::class, 'checkToken'])->name('auto_login.check_token');
    Route::post('/auto-login/set-primary', [AutoLoginCheckerController::class, 'setPrimary'])->name('auto_login.set_primary');
    Route::post('/auto-login/update-password', [AutoLoginCheckerController::class, 'updatePassword'])->name('auto_login.update_password');

    // IP Manager Routes (Decodo Residential Proxy Engine)
    Route::get('/ip-manager', [IpManagerController::class, 'index'])->name('ip_manager');
    Route::post('/ip-manager/update', [IpManagerController::class, 'update'])->name('ip_manager.update');
    Route::post('/ip-manager/test', [IpManagerController::class, 'testConnection'])->name('ip_manager.test');

    // Settings Routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/pool/add', [SettingsController::class, 'addPoolAccount'])->name('settings.pool.add');
    Route::post('/settings/pool/remove', [SettingsController::class, 'removePoolAccount'])->name('settings.pool.remove');
    Route::post('/settings/update', [SettingsController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/admin-credentials', [SettingsController::class, 'updateAdminCredentials'])->name('settings.admin_credentials');
});
