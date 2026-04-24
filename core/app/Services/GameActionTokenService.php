<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * GameActionTokenService
 *
 * Generates and validates one-time, HMAC-signed action tokens for game bets.
 *
 * Why this exists:
 *   A player using Burp Suite / proxy tools can modify the `invest` (bet amount)
 *   field in a request from 100 → 1 before it reaches the server.
 *
 *   With action tokens, the server pre-authorises a specific amount in a signed
 *   token. The game client sends the token, not a raw amount. The server reads
 *   the amount from the verified token, ignoring whatever the client sends.
 *
 *   The signing key (APP_KEY) never leaves the server, so the token cannot be
 *   forged or tampered with.
 *
 * Token lifetime: 60 seconds, single use.
 */
class GameActionTokenService
{
    // Only these exact amounts are legal bets. Prevents arbitrary values.
    public const VALID_CHIPS = [5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000];

    //
    // Issue a signed action token
    //
    /**
     * @param  int    $userId   Authenticated user
     * @param  string $alias    Game alias (e.g. teen_patti_global)
     * @param  string $action   e.g. "bet"
     * @param  float  $amount   The pre-authorised bet amount
     * @param  string $extra    Optional extra payload (e.g. chosen side)
     * @return array  { token, nonce, expires_at }
     * @throws \InvalidArgumentException if amount is not in the valid chip list
     */
    public function issue(int $userId, string $alias, string $action, float $amount, string $extra = ''): array
    {
        if (!in_array((int) $amount, self::VALID_CHIPS, true)) {
            throw new \InvalidArgumentException("Invalid bet amount: {$amount}");
        }

        $nonce     = Str::random(32);
        $expiresAt = time() + 60; // 60 second window

        // Payload: everything that must not change between issue and use
        $payload = implode('|', [
            $userId,
            $alias,
            $action,
            (int) $amount,
            $extra,
            $nonce,
            $expiresAt,
        ]);

        $signature = hash_hmac('sha256', $payload, $this->signingKey());

        // Pack into a single opaque string: base64(payload.signature)
        $token = base64_encode($payload . '::' . $signature);

        // Store in cache so we can verify one-time use later
        Cache::put($this->cacheKey($token), true, 65);

        return [
            'token'      => $token,
            'nonce'      => $nonce,
            'expires_at' => $expiresAt,
        ];
    }

    //
    // Validate and consume a token
    //
    /**
     * @return array { ok, user_id, alias, action, amount, extra, error }
     */
    public function consume(string $token, int $userId, string $alias): array
    {
        $fail = fn(string $msg) => ['ok' => false, 'error' => $msg];

        // Decode
        $decoded = base64_decode($token, strict: true);
        if (!$decoded || !str_contains($decoded, '::')) {
            return $fail('Malformed action token.');
        }

        [$payload, $signature] = explode('::', $decoded, 2);

        // Verify HMAC
        $expected = hash_hmac('sha256', $payload, $this->signingKey());
        if (!hash_equals($expected, $signature)) {
            return $fail('Invalid action token signature — request may have been tampered with.');
        }

        // Unpack payload
        $parts = explode('|', $payload);
        if (count($parts) < 7) {
            return $fail('Corrupted action token payload.');
        }

        [$tUserId, $tAlias, $tAction, $tAmount, $tExtra, $tNonce, $tExpires] = $parts;

        // Expiry
        if ((int) $tExpires < time()) {
            return $fail('Action token expired. Please try again.');
        }

        // Binding checks: token must match current user + game
        if ((int) $tUserId !== $userId) {
            return $fail('Action token user mismatch.');
        }
        if ($tAlias !== $alias) {
            return $fail('Action token game mismatch.');
        }

        // One-time use: consume from cache
        $cacheKey = $this->cacheKey($token);
        if (!Cache::has($cacheKey)) {
            return $fail('Action token already used or expired. Do not replay requests.');
        }
        Cache::forget($cacheKey); // burn after reading

        return [
            'ok'     => true,
            'amount' => (float) $tAmount,
            'action' => $tAction,
            'alias'  => $tAlias,
            'extra'  => $tExtra,
        ];
    }

    //
    // Nonce validation (for all game requests, not just bets)
    //
    /**
     * Call this to register a nonce and prevent replay.
     * Returns true if nonce is fresh; false if already used.
     */
    public function consumeNonce(string $nonce, int $userId): bool
    {
        if (strlen($nonce) < 8 || strlen($nonce) > 128) return false;

        $key = 'gnonce_' . $userId . '_' . $nonce;
        if (Cache::has($key)) return false;

        Cache::put($key, 1, 60); // 60 second replay window
        return true;
    }

    //
    // Helpers
    //
    private function signingKey(): string
    {
        // Uses Laravel APP_KEY — never sent to client
        return config('app.key');
    }

    private function cacheKey(string $token): string
    {
        // Hash the token so cache key is a fixed length
        return 'gtoken_' . hash('sha256', $token);
    }
}
