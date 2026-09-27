<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'server_id',
        'protocol',
        'username',
        'password',
        'public_key',
        'private_key',
        'preshared_key',
        'allocated_ip',
        'status',
        'data_limit_bytes',
        'data_used_bytes',
        'expires_at',
        'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'data_limit_bytes' => 'integer',
            'data_used_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function allocatedPorts(): HasMany
    {
        return $this->hasMany(AllocatedPort::class);
    }

    public function trafficDailyLogs(): HasMany
    {
        return $this->hasMany(TrafficDailyLog::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->data_limit_bytes && $this->data_used_bytes >= $this->data_limit_bytes) {
            return false;
        }

        return true;
    }

    /**
     * Generate isi file client WireGuard .conf
     */
    public function generateWireguardConfig(): string
    {
        $server = $this->server;
        $endpoint = $server->domain ?: $server->ip_address;
        $serverPort = $server->wireguard_port ?: 51820;

        $conf = "[Interface]\n";
        $conf .= 'PrivateKey = '.$this->private_key."\n";
        $conf .= 'Address = '.$this->allocated_ip."/32\n";
        $conf .= "DNS = 1.1.1.1, 8.8.8.8\n\n";

        $conf .= "[Peer]\n";
        $conf .= 'PublicKey = '.$server->wireguard_public_key."\n";
        if ($this->preshared_key) {
            $conf .= 'PresharedKey = '.$this->preshared_key."\n";
        }
        $conf .= 'Endpoint = '.$endpoint.':'.$serverPort."\n";
        // Hanya rute subnet internal VPN agar koneksi internet VPS/Wings tidak terganggu
        $conf .= "AllowedIPs = 10.8.0.0/24\n";
        $conf .= "PersistentKeepalive = 25\n";

        return $conf;
    }
}
