<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeenPattiRoundHistory extends Model
{
    protected $table    = 'teen_patti_round_history';
    protected $fillable = [
        'tenant_id',
        'round_number','winner',
        'silver_total','gold_total','diamond_total','total_pool',
        'silver_cards','gold_cards','diamond_cards',
        'silver_rank','gold_rank','diamond_rank',
        'player_count','is_demo','resolved_at',
    ];

    protected $casts = [
        'silver_cards'  => 'array',
        'gold_cards'    => 'array',
        'diamond_cards' => 'array',
        'silver_total'  => 'float',
        'gold_total'    => 'float',
        'diamond_total' => 'float',
        'total_pool'    => 'float',
        'tenant_id'     => 'integer',
        'is_demo'       => 'boolean',
        'resolved_at'   => 'datetime',
    ];

    public function scopeLive($q)   { return $q->where('is_demo', false); }
    public function scopeDemo($q)   { return $q->where('is_demo', true); }
}
