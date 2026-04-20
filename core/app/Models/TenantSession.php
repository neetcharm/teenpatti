<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSession extends Model
{
    /*
     * Sessions always live in the main DB — they are short-lived routing records
     * needed by GameLaunchController before any tenant context is available.
     * Only TenantTransaction is routed to the tenant's separate DB.
     */


    protected $fillable = [
        'tenant_id', 'session_token', 'player_id', 'player_name', 'game_id',
        'currency', 'internal_user_id', 'balance_cache', 'lang',
        'status', 'ip_address', 'user_agent', 'expires_at', 'last_activity_at',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'last_activity_at' => 'datetime',
        'balance_cache'    => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function internalUser()
    {
        return $this->belongsTo(User::class, 'internal_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(TenantTransaction::class, 'session_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    /**
     * Find an active tenant session by internal user ID.
     */
    public static function findActiveByUserId(int $userId): ?self
    {
        return static::where('internal_user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }
}
