<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    public const DEFAULT_TEEN_PATTI_CHIPS = [400, 2000, 4000, 20000, 40000];

    protected $fillable = [
        'name', 'email', 'password',
        'api_key', 'api_secret', 'webhook_secret', 'webhook_url', 'callback_url',
        'wallet_topup_url',
        'currency', 'commission_percent', 'min_bet', 'max_bet',
        'silver_profit_x', 'gold_profit_x', 'diamond_profit_x',
        'result_mode', 'manual_result_side',
        'teen_patti_chips',
        'session_ttl_minutes', 'allowed_ips', 'status', 'balance_mode',
        // Separate DB
        'use_separate_db', 'db_host', 'db_port', 'db_name', 'db_username', 'db_password_enc',
    ];

    protected $hidden = ['api_secret', 'password', 'webhook_secret', 'db_password_enc'];

    protected $casts = [
        'status'          => 'integer',
        'min_bet'         => 'float',
        'max_bet'         => 'float',
        'commission_percent' => 'float',
        'silver_profit_x' => 'float',
        'gold_profit_x'   => 'float',
        'diamond_profit_x'=> 'float',
        'result_mode'     => 'string',
        'manual_result_side' => 'string',
        'teen_patti_chips'=> 'array',
        'use_separate_db' => 'boolean',
        'db_port'         => 'integer',
    ];

    public function sessions()
    {
        return $this->hasMany(TenantSession::class);
    }

    public function transactions()
    {
        return $this->hasMany(TenantTransaction::class);
    }

    public function games()
    {
        return $this->hasMany(TenantGame::class);
    }

    public function enabledGames()
    {
        return $this->hasMany(TenantGame::class)->where('enabled', true);
    }

    /**
     * Check if a game alias is enabled for this tenant.
     * If no tenant_games rows exist yet, all games are allowed (open access).
     */
    public function hasGame(string $alias): bool
    {
        if ($alias !== 'teen_patti') {
            return false;
        }

        $assigned = $this->games()->count();
        if ($assigned === 0) return true; // no restrictions configured yet

        return $this->games()->where('game_alias', $alias)->where('enabled', true)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function teenPattiChipValues(): array
    {
        $values = is_array($this->teen_patti_chips ?? null) ? $this->teen_patti_chips : [];
        $values = array_values(array_filter(array_map(
            static fn($value): int => (int) round((float) $value),
            $values
        ), static fn(int $value): bool => $value > 0));

        return !empty($values) ? array_slice(array_values(array_unique($values)), 0, 8) : self::DEFAULT_TEEN_PATTI_CHIPS;
    }

    /**
     * Returns the plain secret used for API request signing.
     */
    public function getApiSigningSecret(): string
    {
        if ($this->webhook_secret) {
            try {
                return (string) Crypt::decrypt($this->webhook_secret);
            } catch (\Throwable $e) {
                // Support legacy rows where webhook_secret may already be plain text.
                return (string) $this->webhook_secret;
            }
        }

        // Legacy fallback: only use api_secret when it is not a password hash.
        $legacySecret = (string) $this->api_secret;
        if ($legacySecret !== '') {
            $hashInfo = password_get_info($legacySecret);
            if (($hashInfo['algo'] ?? 0) === 0) {
                return $legacySecret;
            }
        }

        // Final fallback for very old rows.
        return (string) $this->api_key;
    }
}
