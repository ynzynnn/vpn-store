<?php

use App\Http\Controllers\Api\AgentHeartbeatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Agent VPS Heartbeat & Desired State Sync (Dipanggil setiap 15 detik oleh Go Agent)
Route::post('/v1/agent/heartbeat', [AgentHeartbeatController::class, 'heartbeat'])->name('api.agent.heartbeat');
