<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantGame extends Model
{
    protected $fillable = ['tenant_id', 'game_alias', 'enabled', 'min_bet_override', 'max_bet_override'];

    protected $casts = [
        'enabled'          => 'boolean',
        'min_bet_override' => 'float',
        'max_bet_override' => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
