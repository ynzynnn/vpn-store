<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficDailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vpn_account_id',
        'server_id',
        'log_date',
        'bytes_rx',
        'bytes_tx',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'bytes_rx' => 'integer',
            'bytes_tx' => 'integer',
        ];
    }

    public function vpnAccount(): BelongsTo
    {
        return $this->belongsTo(VpnAccount::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
