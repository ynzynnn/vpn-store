<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocatedPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'vpn_account_id',
        'public_port',
        'target_port',
        'protocol',
        'label',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'public_port' => 'integer',
            'target_port' => 'integer',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function vpnAccount(): BelongsTo
    {
        return $this->belongsTo(VpnAccount::class);
    }
}
