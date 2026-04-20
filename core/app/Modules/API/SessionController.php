<?php

namespace App\Modules\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\SessionManager\SessionService;
use App\Support\TenantRuntimeSchema;

class SessionController extends Controller
{
    protected SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Create a new secure game session for an external player.
     * 
     * POST /api/v1/session/create
     */
    public function create(Request $request)
    {
        TenantRuntimeSchema::ensureBaseTables();

        $request->validate([
            'player_id'   => 'required|string|max:100',
            'player_name' => 'required|string|max:100',
            'game_id'     => 'required|string|in:teen_patti',
            'currency'    => 'required|string|size:3',
        ]);

        $tenant = $request->_tenant; // Injected by TenantAuthMiddleware

        if (!$tenant->hasGame($request->game_id)) {
            return response()->json(['error' => 'Game not authorized for this tenant'], 403);
        }

        try {
            $session = $this->sessionService->createSession(
                $tenant,
                $request->player_id,
                $request->player_name,
                $request->game_id,
                strtoupper($request->currency),
                $request->ip()
            );

            // Construct Game WebView URL
            $gameUrl = url("/play?token=" . $session->session_token);

            return response()->json([
                'success' => true,
                'data' => [
                    'session_token' => $session->session_token,
                    'game_url' => $gameUrl,
                    'player_balance' => (float) $session->balance_cache,
                    'currency' => strtoupper((string) $session->currency),
                    'expires_at' => $session->expires_at->toIso8601String(),
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error("Session creation failed: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
