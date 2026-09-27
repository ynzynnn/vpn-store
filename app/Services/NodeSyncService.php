<?php

namespace App\Services;

use App\Models\Server;

class NodeSyncService
{
    /**
     * Menghitung desired state sync untuk dikirimkan ke Go Agent di VPS Node
     */
    public function getDesiredStateForServer(Server $server): array
    {
        // 1. Peer WireGuard yang harus aktif
        $activeAccounts = $server->vpnAccounts()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $peersToAdd = $activeAccounts->map(function ($acc) {
            return [
                'public_key' => $acc->public_key,
                'preshared_key' => $acc->preshared_key,
                'allowed_ip' => $acc->allocated_ip.'/32',
                'username' => $acc->username,
            ];
        })->values()->all();

        // 2. Peer yang harus dicabut/direvoke
        $revokedPublicKeys = $server->vpnAccounts()
            ->where(function ($query) {
                $query->whereIn('status', ['expired', 'suspended'])
                    ->orWhere(function ($q) {
                        $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
                    });
            })
            ->whereNotNull('public_key')
            ->pluck('public_key')
            ->values()
            ->all();

        // 3. Port Forwarding yang harus aktif di iptables
        $activePorts = $server->allocatedPorts()
            ->where('status', 'active')
            ->whereHas('vpnAccount', function ($query) {
                $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->with('vpnAccount')
            ->get();

        $portsToForward = $activePorts->map(function ($port) {
            return [
                'public_port' => $port->public_port,
                'target_ip' => $port->vpnAccount->allocated_ip,
                'target_port' => $port->target_port,
                'protocol' => $port->protocol, // 'tcp', 'udp', 'both'
            ];
        })->values()->all();

        // 4. Port Forwarding yang harus dicopot dari iptables
        $revokedPorts = $server->allocatedPorts()
            ->where(function ($query) {
                $query->where('status', 'inactive')
                    ->orWhereHas('vpnAccount', function ($q) {
                        $q->whereIn('status', ['expired', 'suspended'])
                            ->orWhere(function ($sub) {
                                $sub->whereNotNull('expires_at')->where('expires_at', '<=', now());
                            });
                    });
            })
            ->with('vpnAccount')
            ->get()
            ->map(function ($port) {
                return [
                    'public_port' => $port->public_port,
                    'target_ip' => $port->vpnAccount?->allocated_ip,
                    'target_port' => $port->target_port,
                    'protocol' => $port->protocol,
                ];
            })
            ->values()
            ->all();

        return [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'vpn_subnet' => $server->vpn_subnet,
            'vpn_server_ip' => $server->vpn_server_ip,
            'wireguard_port' => $server->wireguard_port,
            'peers_to_add' => $peersToAdd,
            'peers_to_remove' => $revokedPublicKeys,
            'ports_to_forward' => $portsToForward,
            'ports_to_remove' => $revokedPorts,
        ];
    }
}
