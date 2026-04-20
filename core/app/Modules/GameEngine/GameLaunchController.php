<?php

namespace App\Modules\GameEngine;

use App\Models\TenantSession;
use App\Models\Game;
use App\Services\TenantWebhookService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

/**
 * GameLaunchController
 *
 * Entry point for tenant WebView integration.
 * The tenant's Android/Web app opens:  GET /play?token=...
 *
 * This controller:
 *   1. Validates the session token
 *   2. Logs the internal virtual user into a web session
 *   3. Stores tenant_session_id in the PHP session
 *   4. Redirects to the actual game view
 */
class GameLaunchController extends Controller
{
    public function serve(Request $request)
    {
        $sessionToken = $request->query('token');

        if (!$sessionToken) {
            abort(403, 'Session token missing.');
        }

        // ── 1. Find and validate session ─────────────────────────────────
        $tenantSession = TenantSession::with('tenant', 'internalUser')
            ->where('session_token', $sessionToken)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$tenantSession) {
            abort(403, 'Session expired or invalid. Please re-launch the game.');
        }

        $user = $tenantSession->internalUser;

        if (!$user || $user->status != 1) {
            abort(403, 'Player account is inactive.');
        }

        // ── 1b. Verify the requested game is still enabled for tenant ─────
        $tenant = $tenantSession->tenant;
        $tenant->load('games');
        if (!$tenant->hasGame($tenantSession->game_id)) {
            abort(403, 'This game is not available in your account. Please contact support.');
        }
        if ($tenantSession->game_id !== 'teen_patti') {
            abort(404, 'Only Teen Patti is available on this server.');
        }

        // ── 2. Resolve the game ──────────────────────────────────────────
        $game = Game::active()->where('alias', $tenantSession->game_id)->first();
        if (!$game) {
            abort(404, 'Game not found on server.');
        }

        // ── 3. Log internal user into web session ─────────────────────────
        Auth::login($user, remember: false);

        // ── 4. Tag the PHP session so PlayController knows it's tenant ────
        session(['tenant_session_id' => $tenantSession->id]);

        // ── 5. Touch last activity ────────────────────────────────────────
        $tenantSession->update(['last_activity_at' => now()]);

        // ── 6. Build game page config ─────────────────────────────────────
        $liveBalance = $this->refreshLaunchBalance($tenantSession);
        $balance = number_format($liveBalance, 2, '.', '');
        $walletTopupUrl = (string) ($tenant->wallet_topup_url ?? '');
        $walletRefreshUrl = route('user.play.tenant.wallet.refresh');
        $walletContext = [
            'tenantId' => (int) $tenant->id,
            'playerId' => (string) $tenantSession->player_id,
            'playerName' => (string) $tenantSession->player_name,
            'sessionToken' => (string) $tenantSession->session_token,
            'gameId' => (string) $tenantSession->game_id,
            'currency' => (string) $tenantSession->currency,
        ];

        // ── 6a. Teen Patti tenant launches use dedicated mobile-first WebView ─
        $normalizedAlias = str_replace('-', '_', strtolower((string) $game->alias));
        if ($normalizedAlias === 'teen_patti') {
            $syncUrl = route('user.play.teen_patti.global.sync');
            $investUrl = route('user.play.invest', ['teen_patti']);
            $gameEndUrl = route('user.play.end', ['teen_patti']);
            $historyUrl = route('user.play.teen_patti.history');

            return view('tp_webview', compact(
                'user',
                'balance',
                'syncUrl',
                'investUrl',
                'gameEndUrl',
                'historyUrl',
                'walletTopupUrl',
                'walletRefreshUrl',
                'walletContext'
            ));
        }

        // ── 6. Dynamically resolve WebView ───────────────────────────────
        $viewName = $game->alias . '_saas';
        if (!view()->exists($viewName)) {
            $viewName = 'layouts.master_saas'; // Fallback
        }

        return view($viewName, [
            'user'          => $user,
            'balance'       => $balance,
            'game'          => $game,
            'tenantSession' => $tenantSession,
            'tenant'        => $tenant,
            'walletTopupUrl' => $walletTopupUrl,
            'walletRefreshUrl' => $walletRefreshUrl,
            'walletContext' => $walletContext,
        ]);
    }

    private function refreshLaunchBalance(TenantSession $tenantSession): float
    {
        $tenant = $tenantSession->tenant;
        if (!$tenant || ($tenant->balance_mode ?? 'internal') !== 'webhook') {
            return (float) $tenantSession->balance_cache;
        }

        try {
            $result = (new TenantWebhookService($tenant))->balance($tenantSession);
            if ($result['ok'] ?? false) {
                $tenantSession->refresh();
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant launch balance refresh failed: ' . $e->getMessage(), [
                'tenant_session_id' => $tenantSession->id,
            ]);
        }

        return (float) $tenantSession->balance_cache;
    }
}
