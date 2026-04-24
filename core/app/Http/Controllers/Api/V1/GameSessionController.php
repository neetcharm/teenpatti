<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSession;
use App\Models\User;
use App\Support\TenantRuntimeSchema;
use App\Services\TenantWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * GameSessionController  –  API V1
 *
 * Tenant server calls this to create a game session for one of their players.
 * The response contains a `game_url` that the tenant opens in an Android WebView.
 *
 * 
 *  POST /api/v1/game/session
 * 
 *  Request (JSON):
 *    api_key        string   Tenant's public key
 *    timestamp      int      Unix timestamp (must be within ±300s)
 *    signature      string   HMAC-SHA256(api_secret, api_key|player_id|game_id|timestamp)
 *    player_id      string   Tenant's unique user ID
 *    player_name    string   Display name
 *    game_id        string   e.g. teen_patti (default)
 *    currency       string   e.g. INR
 *    lang           string   e.g. en (optional)
 *
 *  Response (JSON):
 *    success        bool
 *    session_token  string
 *    game_url       string   Open this in Android WebView
 *    player_balance float    Current balance (from tenant webhook)
 *    expires_at     string   ISO-8601 expiry time
 * 
 */
class GameSessionController extends Controller
{
    public function create(Request $request)
    {
        $this->ensureTenantSchema();

        $request->validate([
            'api_key'     => 'required|string',
            'timestamp'   => 'required|integer',
            'signature'   => 'required|string',
            'player_id'   => 'required|string|max:100',
            'player_name' => 'required|string|max:100',
            'game_id'     => 'nullable|string|in:teen_patti',
            'currency'    => 'nullable|string|max:10',
            'lang'        => 'nullable|string|max:10',
        ]);

        // 1. Verify tenant
        $tenant = Tenant::where('api_key', $request->api_key)->where('status', 1)->first();
        if (!$tenant) {
            return $this->error('Invalid API key.', 401);
        }

        // 2. Replay-attack guard (±5 minutes)
        if (abs(time() - (int) $request->timestamp) > 300) {
            return $this->error('Request timestamp expired.', 401);
        }

        // 3. Verify HMAC signature
        $expectedSig = $this->computeSignature(
            $tenant,
            $request->api_key,
            $request->player_id,
            $request->game_id ?? 'teen_patti',
            (int) $request->timestamp
        );
        if (!hash_equals($expectedSig, $request->signature)) {
            return $this->error('Invalid signature.', 401);
        }

        // 4. Verify game is enabled for this tenant
        $gameId   = $request->game_id ?? 'teen_patti';
        $currency = $request->currency ?? $tenant->currency;

        // Load tenant games relation for hasGame check
        $tenant->load('games');
        if (!$tenant->hasGame($gameId)) {
            return $this->error("Game '{$gameId}' is not enabled for your account.", 403);
        }
        $user     = $this->findOrCreateInternalUser($tenant, $request->player_id, $request->player_name);

        // 5. Expire any old active sessions for same player+game
        TenantSession::where('tenant_id', $tenant->id)
            ->where('player_id', $request->player_id)
            ->where('game_id', $gameId)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // 6. Create new session
        $sessionToken = Str::random(64);
        $expiresAt    = now()->addMinutes($tenant->session_ttl_minutes);

        $session = TenantSession::create([
            'tenant_id'        => $tenant->id,
            'session_token'    => $sessionToken,
            'player_id'        => $request->player_id,
            'player_name'      => $request->player_name,
            'game_id'          => $gameId,
            'currency'         => $currency,
            'internal_user_id' => $user->id,
            'balance_cache'    => 0,
            'lang'             => $request->lang ?? 'en',
            'status'           => 'active',
            'ip_address'       => $request->ip(),
            'expires_at'       => $expiresAt,
        ]);

        // 7. Fetch initial balance from tenant
        $webhookService = new TenantWebhookService($tenant);
        $balanceResult  = $webhookService->balance($session);
        $initialBalance = $balanceResult['ok'] ? $balanceResult['balance'] : 0;

        // Update caches
        $session->balance_cache = $initialBalance;
        $session->save();
        $user->balance = $initialBalance;
        $user->save();

        // 8. Return game URL
        $gameUrl = url('/launch/' . $sessionToken);

        return response()->json([
            'success'        => true,
            'message'        => 'Session created.',
            'session_token'  => $sessionToken,
            'game_url'       => $gameUrl,
            'player_balance' => $initialBalance,
            'currency'       => $currency,
            'expires_at'     => $expiresAt->toIso8601String(),
        ]);
    }

    //
    // POST /api/v1/session/close
    // Body: { api_key, session_token, timestamp, signature }
    //
    public function close(Request $request)
    {
        $this->ensureTenantSchema();

        $request->validate([
            'api_key'       => 'required|string',
            'session_token' => 'required|string',
            'timestamp'     => 'required|integer',
            'signature'     => 'required|string',
        ]);

        $tenant = Tenant::where('api_key', $request->api_key)->where('status', 1)->first();
        if (!$tenant) return $this->error('Invalid API key.', 401);

        if (abs(time() - (int) $request->timestamp) > 300) {
            return $this->error('Request timestamp expired.', 401);
        }

        // Signature covers: api_key|session_token|timestamp
        $secret   = $this->decryptWebhookSecret($tenant);
        $expected = hash_hmac('sha256', implode('|', [$request->api_key, $request->session_token, (int) $request->timestamp]), $secret);
        if (!hash_equals($expected, $request->signature)) {
            return $this->error('Invalid signature.', 401);
        }

        $session = TenantSession::where('tenant_id', $tenant->id)
            ->where('session_token', $request->session_token)
            ->first();

        if (!$session) return $this->error('Session not found.', 404);

        if ($session->status === 'active') {
            $session->status = 'closed';
            $session->save();
        }

        return response()->json(['success' => true, 'message' => 'Session closed.', 'status' => $session->status]);
    }

    //
    // POST /api/v1/player/balance
    // Body: { api_key, session_token, timestamp, signature }
    // Fetches fresh balance from tenant webhook and returns it.
    //
    public function refreshBalance(Request $request)
    {
        $this->ensureTenantSchema();

        $request->validate([
            'api_key'       => 'required|string',
            'session_token' => 'required|string',
            'timestamp'     => 'required|integer',
            'signature'     => 'required|string',
        ]);

        $tenant = Tenant::where('api_key', $request->api_key)->where('status', 1)->first();
        if (!$tenant) return $this->error('Invalid API key.', 401);

        if (abs(time() - (int) $request->timestamp) > 300) {
            return $this->error('Request timestamp expired.', 401);
        }

        $secret   = $this->decryptWebhookSecret($tenant);
        $expected = hash_hmac('sha256', implode('|', [$request->api_key, $request->session_token, (int) $request->timestamp]), $secret);
        if (!hash_equals($expected, $request->signature)) {
            return $this->error('Invalid signature.', 401);
        }

        $session = TenantSession::where('tenant_id', $tenant->id)
            ->where('session_token', $request->session_token)
            ->where('status', 'active')
            ->first();

        if (!$session || !$session->isActive()) {
            return $this->error('Session not found or expired.', 404);
        }

        $webhookService = new \App\Services\TenantWebhookService($tenant);
        $result = $webhookService->balance($session);

        if ($result['ok']) {
            $session->balance_cache = $result['balance'];
            $session->save();
        }

        return response()->json([
            'success'  => $result['ok'],
            'balance'  => $result['balance'],
            'currency' => $session->currency,
        ]);
    }

    //
    // GET /api/v1/session/status?api_key=...&session_token=...
    //
    public function status(Request $request)
    {
        $this->ensureTenantSchema();

        $request->validate([
            'api_key'       => 'required|string',
            'session_token' => 'required|string',
        ]);

        $tenant = Tenant::where('api_key', $request->api_key)->where('status', 1)->first();
        if (!$tenant) return $this->error('Invalid API key.', 401);

        $session = TenantSession::where('tenant_id', $tenant->id)
            ->where('session_token', $request->session_token)
            ->first();

        if (!$session) return $this->error('Session not found.', 404);

        return response()->json([
            'success'       => true,
            'status'        => $session->status,
            'active'        => $session->isActive(),
            'player_id'     => $session->player_id,
            'game_id'       => $session->game_id,
            'currency'      => $session->currency,
            'balance_cache' => $session->balance_cache,
            'expires_at'    => $session->expires_at?->toIso8601String(),
        ]);
    }

    //
    // Internal helpers
    //
    private function findOrCreateInternalUser(Tenant $tenant, string $playerId, string $playerName): User
    {
        $username = 'tn' . $tenant->id . '_' . Str::slug($playerId, '_');
        $email    = 'tn' . $tenant->id . '_' . md5($playerId) . '@tenant.internal';

        $user = User::where('username', $username)->first();

        if (!$user) {
            $user                   = new User();
            $user->firstname        = $playerName;
            $user->lastname         = '';
            $user->email            = $email;
            $user->username         = $username;
            $user->password         = Hash::make(Str::random(40));
            $user->ref_by           = 0;
            $user->status           = Status::USER_ACTIVE;
            $user->ev               = Status::VERIFIED;
            $user->sv               = Status::VERIFIED;
            $user->tv               = Status::VERIFIED;
            $user->ts               = Status::DISABLE;
            $user->kv               = Status::KYC_VERIFIED;
            $user->profile_complete = Status::YES;
            $user->demo_balance     = 0;
            $user->balance          = 0;
            $user->save();
        } else {
            $user->firstname = $playerName;
            $user->save();
        }

        return $user;
    }

    /**
     * Compute the expected HMAC-SHA256 signature.
     *
     * Sign string: "{api_key}|{player_id}|{game_id}|{timestamp}"
     * Key: decrypted webhook_secret (Crypt::decrypt)
     *
     * The tenant must sign the same string with the same plain webhook_secret.
     */
    private function computeSignature(Tenant $tenant, string $apiKey, string $playerId, string $gameId, int $timestamp): string
    {
        $secret = $this->decryptWebhookSecret($tenant);
        $data   = implode('|', [$apiKey, $playerId, $gameId, $timestamp]);
        return hash_hmac('sha256', $data, $secret);
    }

    private function decryptWebhookSecret(Tenant $tenant): string
    {
        return $tenant->getApiSigningSecret();
    }

    private function error(string $message, int $status = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function ensureTenantSchema(): void
    {
        TenantRuntimeSchema::ensureBaseTables();
    }
}
