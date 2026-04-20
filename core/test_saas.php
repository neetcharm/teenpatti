<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Modules\Tenant\TenantAuthMiddleware;
use App\Modules\SessionManager\SessionService;

echo "--- SAAS TRANSFORMATION TEST ---\n\n";

try {
    // 1. Create a Dummy Tenant
    $tenant = Tenant::updateOrCreate(
        ['name' => 'Demo SaaS Casino'],
        [
            'api_key' => 'test_api_key_123',
            'api_secret' => 'super_secret_secret',
            'callback_url' => 'http://localhost/dummy_webhook',
            'status' => 1
        ]
    );
    echo "[✓] Tenant prepared: " . $tenant->name . "\n";

    // 2. Test HMAC Middleware Validation
    $payload = json_encode([
        'player_id' => 'p_999',
        'player_name' => 'Test Player',
        'game_id' => 'teen_patti',
        'currency' => 'USD'
    ]);

    $signature = hash_hmac('sha256', $payload, $tenant->api_secret);

    $request = Request::create('/api/v1/session/create', 'POST', [], [], [], [
        'HTTP_X-API-Key' => $tenant->api_key,
        'HTTP_X-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json'
    ], $payload);

    $middleware = new TenantAuthMiddleware();
    
    // Test if middleware passes and injects `_tenant`
    $response = $middleware->handle($request, function ($req) {
        return "PASS_" . $req->_tenant->name;
    });

    if ($response === "PASS_" . $tenant->name) {
        echo "[✓] TenantAuthMiddleware validated HMAC successfully.\n";
    } else {
        echo "[X] TenantAuthMiddleware failed validation.\n";
    }

    // 3. Test Session Manager
    $sessionService = app(SessionService::class);
    $session = $sessionService->createSession(
        $tenant,
        'p_999',
        'Test Player',
        'teen_patti',
        'USD',
        '127.0.0.1'
    );

    if ($session && $session->session_token) {
        echo "[✓] SessionManager created short-lived session token: " . $session->session_token . "\n";
    } else {
        echo "[X] SessionManager failed.\n";
    }

    // 4. Test missing signature
    $badRequest = Request::create('/api/v1/session/create', 'POST', [], [], [], [
        'HTTP_X-API-Key' => $tenant->api_key,
        'HTTP_X-Signature' => 'invalid_signature_hash',
        'CONTENT_TYPE' => 'application/json'
    ], $payload);

    $badResponse = $middleware->handle($badRequest, function ($req) { return "PASS"; });
    if ($badResponse->getStatusCode() === 401) {
        echo "[✓] TenantAuthMiddleware correctly blocked invalid signature (401 Unauthorized).\n";
    } else {
        echo "[X] TenantAuthMiddleware failed to block invalid signature.\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}

echo "\n--- TEST COMPLETE ---\n";
