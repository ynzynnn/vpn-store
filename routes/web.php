<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Auth Protected Routes
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('dashboard')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/servers', [DashboardController::class, 'storeServer'])->name('servers.store');
    Route::delete('/servers/{server}', [DashboardController::class, 'destroyServer'])->name('servers.destroy');
    Route::post('/accounts', [DashboardController::class, 'storeAccount'])->name('accounts.store');
    Route::delete('/accounts/{vpnAccount}', [DashboardController::class, 'destroyAccount'])->name('accounts.destroy');
    Route::get('/accounts/{vpnAccount}/download', [DashboardController::class, 'downloadConfig'])->name('accounts.download');
});
