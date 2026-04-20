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
        $session = $this->getSaaSSession();
        
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
        $session = $this->getSaaSSession();
        
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
    private function getSaaSSession(): TenantSession
    {
        if (!Schema::hasTable('tenant_sessions')) {
            abort(503, 'Tenant session table is not initialized.');
        }

        $sessionId = session('tenant_session_id');
        
        if (!$sessionId) {
            abort(403, 'Unauthorized SaaS session.');
        }

        $session = TenantSession::with('tenant')->find($sessionId);

        if (!$session || $session->status !== 'active') {
            abort(403, 'SaaS session is no longer active.');
        }

        return $session;
    }
}
