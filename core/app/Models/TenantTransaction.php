<?php

namespace App\Models;

use App\Services\TenantConnectionManager;
use Illuminate\Database\Eloquent\Model;

class TenantTransaction extends Model
{
    public function getConnectionName(): string
    {
        if ($this->relationLoaded('tenant') && $this->tenant) {
            return TenantConnectionManager::for($this->tenant);
        }
        return config('database.default');
    }

    /** Create a new instance already bound to the tenant's connection. */
    public static function onTenant(Tenant $tenant): static
    {
        $conn = TenantConnectionManager::for($tenant);
        return (new static)->setConnection($conn);
    }


    protected $fillable = [
        'tenant_id', 'session_id', 'action', 'player_id', 'round_id', 'game_id',
        'our_txn_id', 'tenant_txn_id', 'ref_txn_id', 'amount',
        'balance_before', 'balance_after', 'request_payload', 'response_payload',
        'status', 'error_message',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'amount'           => 'float',
        'balance_before'   => 'float',
        'balance_after'    => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session()
    {
        return $this->belongsTo(TenantSession::class, 'session_id');
    }
}
