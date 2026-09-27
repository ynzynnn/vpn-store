<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'domain',
        'country_code',
        'location',
        'status',
        'agent_token',
        'last_heartbeat_at',
        'port_range_start',
        'port_range_end',
        'vpn_subnet',
        'vpn_server_ip',
        'wireguard_port',
        'wireguard_public_key',
        'wireguard_private_key',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'active_peers',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'is_active' => 'boolean',
            'cpu_usage' => 'float',
            'memory_usage' => 'float',
            'disk_usage' => 'float',
            'port_range_start' => 'integer',
            'port_range_end' => 'integer',
            'wireguard_port' => 'integer',
            'active_peers' => 'integer',
        ];
    }

    public function vpnAccounts(): HasMany
    {
        return $this->hasMany(VpnAccount::class);
    }

    public function allocatedPorts(): HasMany
    {
        return $this->hasMany(AllocatedPort::class);
    }

    public function trafficDailyLogs(): HasMany
    {
        return $this->hasMany(TrafficDailyLog::class);
    }

    /**
     * Cari IP client VPN berikutnya yang tersedia di server ini (e.g. 10.8.0.2 - 10.8.0.254)
     */
    public function getNextAvailableVpnIp(): ?string
    {
        $usedIps = $this->vpnAccounts()->pluck('allocated_ip')->toArray();
        $base = '10.8.0.';

        for ($i = 2; $i <= 254; $i++) {
            $candidate = $base.$i;
            if (! in_array($candidate, $usedIps, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Cari port publik berikutnya yang tersedia di pool server ini
     */
    public function getNextAvailablePort(?int $preferredPort = null, string $protocol = 'both'): ?int
    {
        $usedPorts = $this->allocatedPorts()
            ->where(function ($query) use ($protocol) {
                if ($protocol === 'both') {
                    // Blokir semua
                } else {
                    $query->whereIn('protocol', [$protocol, 'both']);
                }
            })
            ->pluck('public_port')
            ->toArray();

        if ($preferredPort && $preferredPort >= $this->port_range_start && $preferredPort <= $this->port_range_end) {
            if (! in_array($preferredPort, $usedPorts, true)) {
                return $preferredPort;
            }
        }

        for ($port = $this->port_range_start; $port <= $this->port_range_end; $port++) {
            if (! in_array($port, $usedPorts, true)) {
                return $port;
            }
        }

        return null;
    }
}
