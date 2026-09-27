<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\TrafficDailyLog;
use App\Models\VpnAccount;
use App\Services\NodeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentHeartbeatController extends Controller
{
    public function __construct(
        protected NodeSyncService $syncService
    ) {}

    /**
     * Menerima Heartbeat 15-detik dari Go Agent di VPS Node
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $token = $request->header('X-Agent-Token') ?: $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthorized: Token missing'], 401);
        }

        $server = Server::where('agent_token', $token)->first();

        if (! $server) {
            return response()->json(['error' => 'Unauthorized: Invalid Agent Token'], 401);
        }

        $validated = $request->validate([
            'cpu_usage' => 'nullable|numeric',
            'memory_usage' => 'nullable|numeric',
            'disk_usage' => 'nullable|numeric',
            'active_peers' => 'nullable|integer',
            'traffic' => 'nullable|array',
            'traffic.*.public_key' => 'required|string',
            'traffic.*.bytes_rx' => 'required|integer',
            'traffic.*.bytes_tx' => 'required|integer',
        ]);

        // 1. Update Telemetri Server
        $server->update([
            'cpu_usage' => $validated['cpu_usage'] ?? $server->cpu_usage,
            'memory_usage' => $validated['memory_usage'] ?? $server->memory_usage,
            'disk_usage' => $validated['disk_usage'] ?? $server->disk_usage,
            'active_peers' => $validated['active_peers'] ?? $server->active_peers,
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);

        // 2. Proses Traffic Deltas
        if (! empty($validated['traffic'])) {
            $today = now()->toDateString();

            DB::transaction(function () use ($validated, $server, $today) {
                foreach ($validated['traffic'] as $trafficItem) {
                    $account = VpnAccount::where('server_id', $server->id)
                        ->where('public_key', $trafficItem['public_key'])
                        ->first();

                    if (! $account) {
                        continue;
                    }

                    $deltaTotal = $trafficItem['bytes_rx'] + $trafficItem['bytes_tx'];
                    $account->increment('data_used_bytes', $deltaTotal);
                    $account->update(['last_connected_at' => now()]);

                    // Upsert Traffic Daily Log
                    $log = TrafficDailyLog::firstOrNew([
                        'vpn_account_id' => $account->id,
                        'server_id' => $server->id,
                        'log_date' => $today,
                    ]);

                    $log->bytes_rx += $trafficItem['bytes_rx'];
                    $log->bytes_tx += $trafficItem['bytes_tx'];
                    $log->save();
                }
            });
        }

        // 3. Ambil Desired State diff untuk diterapkan oleh Agent
        $desiredState = $this->syncService->getDesiredStateForServer($server);

        return response()->json([
            'status' => 'success',
            'message' => 'Heartbeat received successfully',
            'sync' => $desiredState,
        ]);
    }
}
