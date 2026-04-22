<?php

namespace App\Modules\API;

use App\Http\Controllers\Controller;
use App\Models\TenantSession;
use App\Modules\GameEngine\GameResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EngineController extends Controller
{
    /**
     * Entry point for game start (fetch initial state/balance).
     */
    public function start(Request $request, $alias)
    {
        $session = $this->getSaaSSession($request);
        
        try {
            $engine = GameResolver::resolve($alias);
            $result = $engine->start($session, $request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * Entry point for game play (place bet).
     */
    public function play(Request $request, $alias)
    {
        $session = $this->getSaaSSession($request);
        
        try {
            $engine = GameResolver::resolve($alias);
            $result = $engine->play($session, $request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Unified session resolver.
     */
    private function getSaaSSession(Request $request): TenantSession
    {
        if (!Schema::hasTable('tenant_sessions')) {
            abort(503, 'Tenant session table is not initialized.');
        }

        $tenant = $request->_tenant ?? null;
        $sessionId = (int) session('tenant_session_id');

        $session = null;
        if ($sessionId > 0) {
            $session = TenantSession::with('tenant')->find($sessionId);
        }

        if (!$session) {
            $token = (string) (
                $request->input('session_token')
                ?? $request->query('session_token')
                ?? $request->header('X-Session-Token', '')
            );

            if ($token === '') {
                abort(403, 'Unauthorized SaaS session. Pass session_token to resume.');
            }

            $session = TenantSession::with('tenant')
                ->where('session_token', $token)
                ->first();

            if ($session) {
                session(['tenant_session_id' => $session->id]);
            }
        }

        if (!$session || $session->status !== 'active' || $session->expires_at->lte(now())) {
            abort(403, 'SaaS session is no longer active.');
        }

        if ($tenant && (int) $session->tenant_id !== (int) $tenant->id) {
            abort(403, 'Session does not belong to this tenant.');
        }

        if ($this->isSessionIdle($session)) {
            $session->forceFill([
                'status' => 'closed',
                'expires_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            abort(403, 'Session closed due to inactivity. Please start a new session.');
        }

        $session->forceFill(['last_activity_at' => now()])->save();

        return $session;
    }

    private function isSessionIdle(TenantSession $session): bool
    {
        $idleMinutes = max(1, (int) config('game.tenant_session_idle_timeout_minutes', 5));
        $lastActivity = $session->last_activity_at ?? $session->updated_at ?? $session->created_at;

        if (!$lastActivity) {
            return false;
        }

        return $lastActivity->lt(now()->subMinutes($idleMinutes));
    }
}
