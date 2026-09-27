<?php

namespace App\Services;

use App\Models\AllocatedPort;
use App\Models\VpnAccount;
use Exception;
use Illuminate\Support\Facades\DB;

class PortAllocationService
{
    /**
     * Sewa port publik baru untuk akun VPN
     */
    public function allocatePort(
        VpnAccount $vpnAccount,
        ?int $preferredPublicPort = null,
        ?int $targetPort = null,
        string $protocol = 'both',
        ?string $label = null
    ): AllocatedPort {
        $server = $vpnAccount->server;

        return DB::transaction(function () use ($server, $vpnAccount, $preferredPublicPort, $targetPort, $protocol, $label) {
            $publicPort = $server->getNextAvailablePort($preferredPublicPort, $protocol);

            if (! $publicPort) {
                throw new Exception("Tidak ada port publik yang tersedia di server {$server->name} untuk range {$server->port_range_start} - {$server->port_range_end}.");
            }

            // Jika target port tidak diisi, default sama dengan public port
            $actualTargetPort = $targetPort ?: $publicPort;

            return AllocatedPort::create([
                'server_id' => $server->id,
                'vpn_account_id' => $vpnAccount->id,
                'public_port' => $publicPort,
                'target_port' => $actualTargetPort,
                'protocol' => $protocol,
                'label' => $label ?: "Pterodactyl Port {$publicPort}",
                'status' => 'active',
            ]);
        });
    }

    /**
     * Lepas port yang disewa
     */
    public function releasePort(AllocatedPort $allocatedPort): bool
    {
        return $allocatedPort->delete();
    }
}
