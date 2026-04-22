<?php

namespace App\Modules\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\TenantSession;
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
            $existingToken = TenantSession::where('tenant_id', $tenant->id)
                ->where('player_id', $request->player_id)
                ->where('game_id', $request->game_id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->value('session_token');

            $session = $this->sessionService->createSession(
                $tenant,
                $request->player_id,
                $request->player_name,
                $request->game_id,
                strtoupper($request->currency),
                $request->ip()
            );

            $resumed = is_string($existingToken) && hash_equals($existingToken, (string) $session->session_token);

            // Construct Game WebView URL
            $gameUrl = url("/play?token=" . $session->session_token);

            return response()->json([
                'success' => true,
                'data' => [
                    'resumed' => $resumed,
                    'session_token' => $session->session_token,
                    'game_url' => $gameUrl,
                    'player_balance' => (float) $session->balance_cache,
                    'currency' => strtoupper((string) $session->currency),
                    'expires_at' => $session->expires_at->toIso8601String(),
                ]
            ], $resumed ? 200 : 201);

        } catch (\Exception $e) {
            \Log::error("Session creation failed: " . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * End/close an active session from tenant server webhook.
     *
     * POST /api/v1/session/end
     */
    public function end(Request $request)
    {
        TenantRuntimeSchema::ensureBaseTables();

        $request->validate([
            'session_token' => 'required|string|max:128',
            'reason' => 'nullable|string|max:150',
        ]);

        $tenant = $request->_tenant;

        $session = TenantSession::where('tenant_id', $tenant->id)
            ->where('session_token', $request->session_token)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $previousStatus = $session->status;

        if ($session->status === 'active') {
            $session->forceFill([
                'status' => 'closed',
                'expires_at' => now(),
                'last_activity_at' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_token' => $session->session_token,
                'previous_status' => $previousStatus,
                'status' => $session->status,
                'ended_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
