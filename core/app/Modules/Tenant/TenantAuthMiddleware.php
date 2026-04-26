<?php

namespace App\Modules\Tenant;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Support\TenantRuntimeSchema;
use Illuminate\Support\Facades\Cache;

class TenantAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        TenantRuntimeSchema::ensureBaseTables();

        $apiKey = $request->header('X-API-Key');
        $signature = $request->header('X-Signature'); // HMAC SHA256 of request payload
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');

        if (!$apiKey || !$signature) {
            return response()->json(['error' => 'Missing authentication headers'], 401);
        }

        $tenant = Tenant::active()->where('api_key', $apiKey)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Invalid API Key or Inactive Tenant'], 401);
        }

        $payload = $request->getContent();
        $signingSecret = $tenant->getApiSigningSecret();
        $legacyExpected = hash_hmac('sha256', $payload, $signingSecret);
        $signatureValid = hash_equals($legacyExpected, $signature);
        $usesLegacySignature = $signatureValid;

        // Docs-compatible canonical signing support.
        if (!$signatureValid && $timestamp && $nonce) {
            if (!ctype_digit((string) $timestamp) || abs(time() - (int) $timestamp) > 300) {
                return response()->json(['error' => 'Expired or invalid timestamp'], 401);
            }

            $method = strtoupper($request->method());
            $path = '/' . ltrim($request->path(), '/');
            $bodyHash = hash('sha256', $payload);
            $canonical = implode('|', [$method, $path, $apiKey, $timestamp, $nonce, $bodyHash]);
            $canonicalExpected = hash_hmac('sha256', $canonical, $signingSecret);
            $signatureValid = hash_equals($canonicalExpected, $signature);
            $usesLegacySignature = false;

            if ($signatureValid) {
                $nonceCacheKey = 'tenant_nonce:' . $tenant->id . ':' . $nonce;
                if (!Cache::add($nonceCacheKey, 1, now()->addMinutes(5))) {
                    return response()->json(['error' => 'Replay request detected'], 401);
                }
            }
        }

        if (!$signatureValid) {
            return response()->json(['error' => 'Invalid Signature'], 401);
        }

        if ($usesLegacySignature && !config('game.tenant_allow_legacy_signatures', false)) {
            return response()->json(['error' => 'Timestamp and nonce are required'], 401);
        }

        // Inject tenant into request for later use
        $request->merge(['_tenant' => $tenant]);

        return $next($request);
    }
}
