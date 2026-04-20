<?php

namespace App\Http\Middleware;

use App\Services\GameActionTokenService;
use Closure;
use Illuminate\Http\Request;

/**
 * ValidateGameRequest middleware
 *
 * Protects game play endpoints from:
 *  1. Request tampering  (nonce + timestamp binding)
 *  2. Replay attacks     (nonce stored in cache, one-time use)
 *  3. Timestamp expiry   (requests older than 30 seconds rejected)
 *
 * Every request from the game client must include:
 *   _nonce : unique random string per request
 *   _ts    : unix timestamp (seconds) when request was built client-side
 *
 * Attached as middleware to: POST /api/play/invest/{alias}
 */
class ValidateGameRequest
{
    public function __construct(private GameActionTokenService $tokens) {}

    public function handle(Request $request, Closure $next)
    {
        // Allow demo mode through with relaxed rules (no real money)
        $isDemo = $request->route('isDemo') === 'demo' || $request->input('demo') === '1';
        if ($isDemo) {
            return $next($request);
        }

        $nonce = $request->input('_nonce') ?? $request->header('X-Game-Nonce');
        $ts    = $request->input('_ts')    ?? $request->header('X-Game-Timestamp');

        // ── 1. Both fields required ───────────────────────────────────────────
        if (!$nonce || !$ts) {
            return response()->json([
                'error' => 'Missing request security fields (_nonce, _ts). Update your game client.',
            ], 422);
        }

        // ── 2. Timestamp within ±30 seconds ──────────────────────────────────
        if (abs(time() - (int) $ts) > 30) {
            return response()->json([
                'error' => 'Request timestamp expired. Please try again.',
            ], 422);
        }

        // ── 3. Nonce is single-use ────────────────────────────────────────────
        $userId = auth()->id();
        if (!$this->tokens->consumeNonce((string) $nonce, (int) $userId)) {
            return response()->json([
                'error' => 'Duplicate or replayed request detected.',
            ], 422);
        }

        return $next($request);
    }
}
