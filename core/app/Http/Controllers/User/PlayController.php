<?php

namespace App\Http\Controllers\User;

use App\Games\GamePlayer;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Tenant;
use App\Models\TenantSession;
use App\Services\TeenPattiGlobalManager;
use App\Services\TenantWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlayController extends Controller {
    public function playGame($alias, $isDemo = null) {
        abort_if(!in_array($alias, liveGameAliases(), true), 404);

        $game      = Game::active()->where('alias', $alias)->firstOrFail();
        $pageTitle = "Play " . $game->name;
        $viewAlias = $this->viewAlias($alias);

        $user = auth()->user();
        if ($isDemo) {
            abort_if($isDemo !== 'demo', 404);
        }
        $balance = ($isDemo === 'demo') ? @$user->demo_balance : @$user->balance;
        $tenantSession = ($isDemo === 'demo') ? null : $this->activeTenantSession($user);
        $teenPattiChipValues = $tenantSession?->tenant?->teenPattiChipValues() ?? Tenant::DEFAULT_TEEN_PATTI_CHIPS;

        return view('Template::user.games.' . $viewAlias, compact('game', 'pageTitle', 'isDemo', 'balance', 'teenPattiChipValues'));
    }

    public function investGame(Request $request, $alias, $isDemo = null) {
        abort_if(!in_array($alias, liveGameAliases(), true), 404);

        $request->validate([
            'choose' => 'required|string|in:silver,gold,diamond',
            'invest' => 'required|numeric|gt:0',
        ]);

        $demoMode = ($isDemo === 'demo');
        $tenantSession = $demoMode ? null : $this->activeTenantSession(auth()->user());
        if (!$demoMode && $this->hasTenantSessionContext() && !$tenantSession) {
            return response()->json(['error' => 'Session closed due to inactivity. Please relaunch the game.'], 403);
        }

        $manager  = new TeenPattiGlobalManager($demoMode, $tenantSession?->tenant_id);
        $invest   = (float) $request->invest;

        $betResult = $manager->placeBet((int) auth()->id(), $request->choose, $invest);

        if (isset($betResult['error'])) {
            return response()->json(['error' => $betResult['error']], 422);
        }

        $this->touchTenantSessionActivity($tenantSession);

        return response()->json([
            'balance' => showAmount((float) ($betResult['balance'] ?? 0), currencyFormat: false),
            'my_bets' => $betResult['my_bets'] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0],
            'totals'  => $betResult['totals'] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0],
        ]);
    }

    public function gameEnd(Request $request, $alias, $isDemo = null) {
        abort_if(!in_array($alias, liveGameAliases(), true), 404);

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
        $tenantSession = $demoMode ? null : $this->activeTenantSession(auth()->user());
        if (!$demoMode && $this->hasTenantSessionContext() && !$tenantSession) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session closed due to inactivity. Please relaunch the game.',
            ], 403);
        }

        $manager  = new TeenPattiGlobalManager($demoMode, $tenantSession?->tenant_id);
        $this->touchTenantSessionActivity($tenantSession);

        // getSync() handles everything internally: phase detection,
        // auto-resolution, history, payouts — all crash-safe.
        $sync = $manager->getSync((int) auth()->id());

        // Webhook balance fetch now runs once per completed round (not every sync).
        $refreshTenantBalance = $tenantSession
            ? $this->shouldRefreshTenantBalanceForCompletedRound($tenantSession, $sync)
            : false;

        $sync['balance'] = $this->resolveGameBalance(
            auth()->user(),
            $demoMode,
            refreshTenant: $refreshTenantBalance,
            tenantSession: $tenantSession
        );

        return response()->json($sync);
    }

    public function tenantWalletRefresh(Request $request)
    {
        $tenantSession = $this->activeTenantSession(auth()->user());
        if ($this->hasTenantSessionContext() && !$tenantSession) {
            return response()->json([
                'success' => false,
                'message' => 'Session closed due to inactivity. Please relaunch the game.',
            ], 403);
        }

        if (!$tenantSession) {
            return response()->json([
                'success' => true,
                'balance' => showAmount((float) (auth()->user()->balance ?? 0), currencyFormat: false),
                'currency' => null,
            ]);
        }

        $this->touchTenantSessionActivity($tenantSession);

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
            $tenantSession = $demoMode ? null : $this->activeTenantSession(auth()->user());
            if (!$demoMode && $this->hasTenantSessionContext() && !$tenantSession) {
                return response()->json(['history' => []], 403);
            }

            $manager  = new TeenPattiGlobalManager($demoMode, $tenantSession?->tenant_id);
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

    private function resolveGameBalance($user, bool $demoMode, bool $refreshTenant = false, ?TenantSession $tenantSession = null): string
    {
        if ($demoMode) {
            return showAmount((float) ($user->demo_balance ?? 0), currencyFormat: false);
        }

        $tenantSession = $tenantSession ?: $this->activeTenantSession($user);
        if ($tenantSession) {
            $balance = $refreshTenant
                ? $this->resolveTenantWalletBalance($tenantSession, forceRefresh: true)
                : (float) $tenantSession->balance_cache;

            return showAmount($balance, currencyFormat: false);
        }

        return showAmount((float) ($user->balance ?? 0), currencyFormat: false);
    }

    private function shouldRefreshTenantBalanceForCompletedRound(TenantSession $tenantSession, array $sync): bool
    {
        $tenant = $tenantSession->tenant;
        if (!$tenant || ($tenant->balance_mode ?? 'internal') !== 'webhook') {
            return false;
        }

        $phase = (string) ($sync['phase'] ?? '');
        $result = $sync['result'] ?? null;
        $round = (int) ($sync['round'] ?? 0);

        if ($phase !== 'hold' || !is_array($result) || empty($result['winner']) || $round <= 0) {
            return false;
        }

        $cacheKey = 'tenant_wallet_round_refresh:' . $tenantSession->id . ':' . $round;
        return Cache::add($cacheKey, 1, now()->addHours(3));
    }

    private function touchTenantSessionActivity(?TenantSession $tenantSession): void
    {
        if (!$tenantSession || $tenantSession->status !== 'active') {
            return;
        }

        $tenantSession->forceFill(['last_activity_at' => now()])->save();
    }

    private function activeTenantSession($user): ?TenantSession
    {
        $tenantSessionId = (int) session('tenant_session_id');
        if ($tenantSessionId <= 0) {
            return null;
        }

        $tenantSession = TenantSession::with('tenant')
            ->where('id', $tenantSessionId)
            ->where('internal_user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$tenantSession) {
            return null;
        }

        if ($this->isTenantSessionIdle($tenantSession)) {
            $tenantSession->forceFill([
                'status' => 'closed',
                'expires_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            return null;
        }

        return $tenantSession;
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

    private function isTenantSessionIdle(TenantSession $tenantSession): bool
    {
        $idleMinutes = max(1, (int) config('game.tenant_session_idle_timeout_minutes', 5));
        $lastActivity = $tenantSession->last_activity_at ?? $tenantSession->updated_at ?? $tenantSession->created_at;

        if (!$lastActivity) {
            return false;
        }

        return $lastActivity->lt(now()->subMinutes($idleMinutes));
    }

    private function hasTenantSessionContext(): bool
    {
        return (int) session('tenant_session_id') > 0;
    }
}
