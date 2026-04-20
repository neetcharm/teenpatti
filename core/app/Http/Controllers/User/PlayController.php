<?php

namespace App\Http\Controllers\User;

use App\Games\GamePlayer;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\TenantSession;
use App\Services\TeenPattiGlobalManager;
use App\Services\TenantWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlayController extends Controller {
    public function playGame($alias, $isDemo = null) {
        abort_if($alias !== 'teen_patti', 404);

        $game      = Game::active()->where('alias', $alias)->firstOrFail();
        $pageTitle = "Play " . $game->name;
        $viewAlias = $this->viewAlias($alias);

        $user = auth()->user();
        if ($isDemo) {
            abort_if($isDemo !== 'demo', 404);
        }
        $balance = ($isDemo === 'demo') ? @$user->demo_balance : @$user->balance;
        return view('Template::user.games.' . $viewAlias, compact('game', 'pageTitle', 'isDemo', 'balance'));
    }

    public function investGame(Request $request, $alias, $isDemo = null) {
        abort_if($alias !== 'teen_patti', 404);

        $request->validate([
            'choose' => 'required|string|in:silver,gold,diamond',
            'invest' => 'required|numeric|gt:0',
        ]);

        $demoMode = ($isDemo === 'demo');
        $manager  = new TeenPattiGlobalManager($demoMode);
        $invest   = (float) $request->invest;

        $betResult = $manager->placeBet((int) auth()->id(), $request->choose, $invest);

        if (isset($betResult['error'])) {
            return response()->json(['error' => $betResult['error']], 422);
        }

        return response()->json([
            'balance' => showAmount((float) ($betResult['balance'] ?? 0), currencyFormat: false),
            'my_bets' => $betResult['my_bets'] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0],
            'totals'  => $betResult['totals'] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0],
        ]);
    }

    public function gameEnd(Request $request, $alias, $isDemo = null) {
        abort_if($alias !== 'teen_patti', 404);

        $gamePlayer = new GamePlayer($alias, $isDemo);
        return $gamePlayer->completeGame();
    }

    /**
     * Global sync endpoint for Teen Patti.
     * Returns current round, phase, timer, bets, and result.
     */
    public function teenPattiGlobalSync(Request $request, $isDemo = null) {
        if ($isDemo) {
            abort_if($isDemo !== 'demo', 404);
        }

        $demoMode = ($isDemo === 'demo');
        $manager  = new TeenPattiGlobalManager($demoMode);

        // getSync() handles everything internally: phase detection,
        // auto-resolution, history, payouts — all crash-safe.
        $sync = $manager->getSync((int) auth()->id());
        $sync['balance'] = $this->resolveGameBalance(auth()->user(), $demoMode, refreshTenant: true);

        return response()->json($sync);
    }

    public function tenantWalletRefresh(Request $request)
    {
        $tenantSession = $this->activeTenantSession(auth()->user());
        if (!$tenantSession) {
            return response()->json([
                'success' => true,
                'balance' => showAmount((float) (auth()->user()->balance ?? 0), currencyFormat: false),
                'currency' => null,
            ]);
        }

        $balance = $this->resolveTenantWalletBalance($tenantSession, forceRefresh: true);

        return response()->json([
            'success' => true,
            'balance' => showAmount($balance, currencyFormat: false),
            'currency' => $tenantSession->currency,
        ]);
    }

    /**
     * Teen Patti history endpoint used by frontend panel.
     */
    public function teenPattiHistory(Request $request, $isDemo = null) {
        if ($isDemo) {
            abort_if($isDemo !== 'demo', 404);
        }

        try {
            $demoMode = ($isDemo === 'demo');
            $manager  = new TeenPattiGlobalManager($demoMode);
            return response()->json(['history' => $manager->getHistory(50)]);
        } catch (\Throwable $e) {
            Log::error('TeenPatti history failed: ' . $e->getMessage());
            return response()->json(['history' => []]);
        }
    }

    private function viewAlias(string $alias): string
    {
        return $alias;
    }

    private function resolvePlayerName($user): string
    {
        $name = trim((string) ($user->username ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) (($user->firstname ?? '') . ' ' . ($user->lastname ?? '')));
        if ($name !== '') {
            return $name;
        }

        return 'Player ' . $user->id;
    }

    private function resolveGameBalance($user, bool $demoMode, bool $refreshTenant = false): string
    {
        if ($demoMode) {
            return showAmount((float) ($user->demo_balance ?? 0), currencyFormat: false);
        }

        $tenantSession = $this->activeTenantSession($user);
        if ($tenantSession) {
            $balance = $refreshTenant
                ? $this->resolveTenantWalletBalance($tenantSession, forceRefresh: false)
                : (float) $tenantSession->balance_cache;

            return showAmount($balance, currencyFormat: false);
        }

        return showAmount((float) ($user->balance ?? 0), currencyFormat: false);
    }

    private function activeTenantSession($user): ?TenantSession
    {
        $tenantSessionId = (int) session('tenant_session_id');
        if ($tenantSessionId <= 0) {
            return null;
        }

        return TenantSession::with('tenant')
            ->where('id', $tenantSessionId)
            ->where('internal_user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function resolveTenantWalletBalance(TenantSession $tenantSession, bool $forceRefresh = false): float
    {
        $tenant = $tenantSession->tenant;
        if (!$tenant) {
            return (float) $tenantSession->balance_cache;
        }

        if (($tenant->balance_mode ?? 'internal') !== 'webhook') {
            return (float) $tenantSession->balance_cache;
        }

        $cacheKey = 'tenant_wallet_refresh_' . $tenantSession->id;
        if (!$forceRefresh && !Cache::add($cacheKey, 1, now()->addSeconds(10))) {
            return (float) $tenantSession->balance_cache;
        }

        try {
            $result = (new TenantWebhookService($tenant))->balance($tenantSession);
            if ($result['ok'] ?? false) {
                return (float) $result['balance'];
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant wallet refresh failed: ' . $e->getMessage(), [
                'tenant_session_id' => $tenantSession->id,
            ]);
        }

        return (float) $tenantSession->balance_cache;
    }
}
