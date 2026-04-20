<?php

namespace App\Modules\SessionManager;

use App\Constants\Status;
use App\Models\Tenant;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\TenantWebhookService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SessionService
{
    /**
     * Create a new secure session token for a tenant's external user.
     * 
     * @param Tenant $tenant
     * @param string $playerId
     * @param string $playerName
     * @param string $gameId
     * @param string $currency
     * @param string $ipAddress
     * @return TenantSession
     */
    public function createSession(
        Tenant $tenant,
        string $playerId,
        string $playerName,
        string $gameId,
        string $currency,
        string $ipAddress = null
    ): TenantSession {
        $user = $this->findOrCreateInternalUser($tenant, $playerId, $playerName);

        TenantSession::where('tenant_id', $tenant->id)
            ->where('player_id', $playerId)
            ->where('game_id', $gameId)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $token = hash('sha256', Str::random(40) . time() . $tenant->id . $playerId);

        $ttl = $tenant->session_ttl_minutes ?? 60; // 1 hour default

        $session = TenantSession::create([
            'tenant_id' => $tenant->id,
            'session_token' => $token,
            'player_id' => $playerId,
            'player_name' => $playerName,
            'game_id' => $gameId,
            'currency' => $currency,
            'internal_user_id' => $user->id,
            'lang' => 'en',
            'user_agent' => request()?->userAgent(),
            'balance_cache' => 0.00, // Balance will be fetched from external wallet later
            'status' => 'active',
            'ip_address' => $ipAddress,
            'expires_at' => Carbon::now()->addMinutes($ttl),
            'last_activity_at' => Carbon::now(),
        ]);

        $this->hydrateInitialBalance($tenant, $session, $user);

        return $session;
    }

    private function hydrateInitialBalance(Tenant $tenant, TenantSession $session, User $user): void
    {
        try {
            $result = (new TenantWebhookService($tenant))->balance($session);
            if (!($result['ok'] ?? false)) {
                return;
            }

            $balance = round((float) ($result['balance'] ?? 0), 2);

            $session->forceFill([
                'balance_cache' => $balance,
            ])->save();

            $user->forceFill([
                'balance' => $balance,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Session balance hydration failed: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'session_id' => $session->id,
                'player_id' => $session->player_id,
            ]);
        }
    }

    private function findOrCreateInternalUser(Tenant $tenant, string $playerId, string $playerName): User
    {
        $username = 'tn' . $tenant->id . '_' . Str::slug($playerId, '_');
        $email = 'tn' . $tenant->id . '_' . md5($playerId) . '@tenant.internal';

        $user = User::where('username', $username)->first();

        if (!$user) {
            $user = new User();
            $user->firstname = $playerName;
            $user->lastname = '';
            $user->email = $email;
            $user->username = $username;
            $user->password = Hash::make(Str::random(40));
            $user->ref_by = 0;
            $user->status = Status::USER_ACTIVE;
            $user->ev = Status::VERIFIED;
            $user->sv = Status::VERIFIED;
            $user->tv = Status::VERIFIED;
            $user->ts = Status::DISABLE;
            $user->kv = Status::KYC_VERIFIED;
            $user->profile_complete = Status::YES;
            $user->demo_balance = 0;
            $user->balance = 0;
            $user->save();
        } else {
            $user->firstname = $playerName;
            $user->save();
        }

        return $user;
    }
}
