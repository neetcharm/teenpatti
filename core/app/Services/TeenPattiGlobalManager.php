<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Game as GameModel;
use App\Models\Tenant;
use App\Models\TenantSession;
use App\Models\User;
use App\Models\Transaction;

/**
 * TeenPattiGlobalManager
 *
 * Manages the global betting round with 20s betting and 15s result/hold phase.
 * Winner logic: fair random selection by default, with optional tenant manual override.
 *
 * Design notes:
 *  - Avoid Cache::lock() because file driver locks may be unreliable on shared hosts.
 *  - Keep DB writes inside try/catch so one failure does not stop the round flow.
 *  - Result is computed once and cached; re-reads from cache on subsequent calls.
 *  - History persistence is non-blocking.
 */
class TeenPattiGlobalManager
{
    private const BET_WINDOW      = 20;   // seconds for betting
    private const HOLD_WINDOW     = 15;   // seconds to show result (must be > animation time on frontend)
    private const ROUND_DURATION  = self::BET_WINDOW + self::HOLD_WINDOW;
    private const BETS_PREFIX     = 'tp_bets_';
    private const RESULT_PREFIX   = 'tp_result_';
    private const SNAPSHOT_PREFIX = 'tp_snapshot_';
    private const HISTORY_PREFIX  = 'tp_history_';

    private bool $demoMode;
    private int $tenantId;
    private ?Tenant $tenantConfig = null;
    private bool $roundBetTableReady = false;
    private static ?bool $roundBetTableAvailable = null;

    public function __construct(bool $demoMode = false, ?int $tenantId = null)
    {
        $this->demoMode = $demoMode;
        $this->tenantId = max(0, (int) ($tenantId ?? 0));
        $this->roundBetTableReady = self::ensureRoundBetTable();
    }

    /* Sync: returns the full state the frontend needs. */
    public function getSync(int $userId): array
    {
        $round  = $this->currentRound();
        $phase  = $this->currentPhase();
        $remain = $this->secondsRemaining();

        $snapshot = $this->getRoundBetSnapshot($round);
        $bets     = [
            'silver'  => (float) ($snapshot['silver'] ?? 0),
            'gold'    => (float) ($snapshot['gold'] ?? 0),
            'diamond' => (float) ($snapshot['diamond'] ?? 0),
        ];
        $userBets = $this->getUserBets($round, $userId);

        $result = null;
        if ($phase === 'hold') {
            // Try to get cached result first
            $result = $this->getCachedResult($round);

            // Auto-resolve if no result exists yet (Sync-Triggered Resolution)
            if (!$result) {
                $result = $this->resolveRound($round);
            }
        }

        // History fetch is non-blocking.
        $history = $this->getHistorySafe(20);

        return [
            'round'     => $round,
            'phase'     => $phase,
            'remaining' => $remain,
            'bets'      => $bets,
            'my_bets'   => $userBets,
            'result'    => $result,
            'history'   => $history,
        ];
    }

    /* Resolve round: compute winner, deal cards, and process payouts. */
    public function resolveRound(int $round): ?array
    {
        $resultKey = $this->resultKey($round);

        // 1. Already resolved? Return cached result.
        $existing = Cache::get($resultKey);
        if ($existing && is_array($existing) && isset($existing['winner'])) {
            return $existing;
        }

        // 2. Compute the result (idempotent operation).
        try {
            $bets = $this->getRoundBetSnapshot($round, true);

            $totals = [
                'silver'  => (float) ($bets['silver'] ?? 0),
                'gold'    => (float) ($bets['gold'] ?? 0),
                'diamond' => (float) ($bets['diamond'] ?? 0),
            ];

            $manualSide = $this->manualOverrideWinnerSide();
            $hands = $manualSide !== null
                ? $this->dealHandsForWinner($manualSide)
                : $this->dealRankedHands();
            $winner = $this->determineWinnerFromHands($hands);
            $scores = $this->handScores($hands);

            $result = [
                'round'    => $round,
                'winner'   => $winner,
                'hands'    => $hands,
                'ranks'    => [
                    'silver'  => $this->rankLabel($hands['silver']),
                    'gold'    => $this->rankLabel($hands['gold']),
                    'diamond' => $this->rankLabel($hands['diamond']),
                ],
                'high_cards' => [
                    'silver'  => $this->highCard($hands['silver']),
                    'gold'    => $this->highCard($hands['gold']),
                    'diamond' => $this->highCard($hands['diamond']),
                ],
                'scores' => $scores,
                'totals' => [
                    'silver'  => round((float) $totals['silver'], 2),
                    'gold'    => round((float) $totals['gold'], 2),
                    'diamond' => round((float) $totals['diamond'], 2),
                ],
                'reason' => $this->winnerReason($winner, $totals, $hands),
                'user_payouts' => $this->processPayoutsSafe($round, $winner, $bets),
            ];

            // 3. Cache the result for 15 minutes (longer than round duration).
            Cache::put($resultKey, $result, now()->addMinutes(15));

            Log::info("[TP] Round #{$round} resolved. Winner: {$winner}");

            // 4. Persist to DB history without blocking gameplay.
            $this->persistHistorySafe($round, $winner, $totals, $hands, $result, $bets);
            $this->pushHistoryCache($result, $totals, count($bets['users'] ?? []));

            return $result;

        } catch (\Throwable $e) {
            Log::error("[TP] resolveRound failed for round #{$round}: " . $e->getMessage());

            // Even on failure, try to return whatever is in cache
            return Cache::get($resultKey);
        }
    }

    private function winnerReason(string $winner, array $totals, array $hands = []): string
    {
        $manualSide = $this->manualOverrideWinnerSide();
        if ($manualSide !== null) {
            return sprintf(
                'Manual override selected %s and the dealt cards were generated so %s wins by Teen Patti hand ranking.',
                ucfirst($winner),
                ucfirst($winner)
            );
        }

        return sprintf(
            '%s won by Teen Patti hand ranking: %s.',
            ucfirst($winner),
            $this->rankLabel($hands[$winner] ?? [])
        );
    }

    public function getManualOverrideInsights(?int $round = null): array
    {
        $round = $round && $round > 0 ? $round : $this->currentRound();
        $snapshot = $this->getRoundBetSnapshot($round, true);

        $totals = [
            'silver'  => round((float) ($snapshot['silver'] ?? 0), 2),
            'gold'    => round((float) ($snapshot['gold'] ?? 0), 2),
            'diamond' => round((float) ($snapshot['diamond'] ?? 0), 2),
        ];

        $totalPool = round((float) array_sum($totals), 2);
        $tenant = $this->scopedTenantConfig();
        $commissionPercent = max(0.0, min(95.0, (float) ($tenant?->commission_percent ?? 10.0)));

        $projection = [];
        foreach (['silver', 'gold', 'diamond'] as $side) {
            $sidePool = (float) ($totals[$side] ?? 0);
            $fixedPayoutMultiplierX = $this->tenantFixedPayoutMultiplierX($tenant, $side);

            if ($fixedPayoutMultiplierX !== null) {
                $fixedPayoutMultiplierX = max(0.0, $fixedPayoutMultiplierX);
                $netMultiplier = $fixedPayoutMultiplierX * ((100.0 - $commissionPercent) / 100.0);
                $mode = 'fixed_payout_x';
            } else {
                $netMultiplier = $sidePool > 0 ? (($totalPool / $sidePool) * ((100.0 - $commissionPercent) / 100.0)) : 0.0;
                $mode = 'dynamic_pool';
            }

            $payoutIfWinner = $this->floorPayoutAmount($sidePool * $netMultiplier);
            $companyNet = round($totalPool - $payoutIfWinner, 2);

            $projection[$side] = [
                'winner_pool' => round($sidePool, 2),
                'projected_payout' => $payoutIfWinner,
                'projected_company_net' => $companyNet,
                'net_multiplier' => round($netMultiplier, 4),
                'payout_multiplier_x' => $fixedPayoutMultiplierX !== null ? round($fixedPayoutMultiplierX, 4) : null,
                'mode' => $mode,
            ];
        }

        $mode = $tenant ? strtolower((string) ($tenant->result_mode ?? 'random')) : 'random';
        if (!in_array($mode, ['random', 'manual'], true)) {
            $mode = 'random';
        }

        $manualSide = $tenant ? strtolower((string) ($tenant->manual_result_side ?? '')) : '';
        if (!in_array($manualSide, ['silver', 'gold', 'diamond'], true)) {
            $manualSide = null;
        }

        return [
            'round' => $round,
            'phase' => $this->currentPhase(),
            'remaining' => $this->secondsRemaining(),
            'mode' => $mode,
            'manual_result_side' => $manualSide,
            'totals' => $totals,
            'total_pool' => $totalPool,
            'commission_percent' => round($commissionPercent, 2),
            'active_players' => count($snapshot['users'] ?? []),
            'projection' => $projection,
        ];
    }

    /* ================================================================
     *  PLACE BET
     * ================================================================ */
    public function placeBet(int $userId, string $placeholder, float $amount): array
    {
        if ($this->currentPhase() !== 'betting') {
            return ['error' => 'Betting is closed!'];
        }

        $placeholder = strtolower($placeholder);
        if (!in_array($placeholder, ['silver', 'gold', 'diamond'], true)) {
            return ['error' => 'Invalid bet option'];
        }

        if ($amount <= 0) {
            return ['error' => 'Invalid bet amount'];
        }

        $game = GameModel::active()->where('alias', 'teen_patti')->first();
        if ($game) {
            if ($amount < (float) $game->min_limit) {
                return ['error' => 'Please follow the minimum limit of invest'];
            }
            if ($amount > (float) $game->max_limit) {
                return ['error' => 'Please follow the maximum limit of invest'];
            }
        }

        $round = $this->currentRound();

        if (!$this->demoMode) {
            try {
                $tenantSession = TenantSession::with('tenant')
                    ->where('internal_user_id', $userId)
                    ->where('game_id', 'teen_patti')
                    ->when(
                        $this->tenantScopeId() > 0,
                        fn($query) => $query->where('tenant_id', $this->tenantScopeId())
                    )
                    ->active()
                    ->latest('id')
                    ->first();

                if ($tenantSession) {
                    return $this->placeTenantBet($tenantSession, $userId, $placeholder, $amount, $round);
                }
            } catch (\Throwable $e) {
                Log::warning("[TP] tenant session lookup failed for user {$userId}: " . $e->getMessage());
            }
        }

        // Use DB transaction for balance safety (no cache locks needed)
        try {
            return DB::transaction(function () use ($userId, $placeholder, $amount, $round) {
                // Double-check phase inside transaction
                if ($this->currentPhase() !== 'betting') {
                    return ['error' => 'Betting is closed!'];
                }

                $user = User::where('id', $userId)->lockForUpdate()->first();
                if (!$user) {
                    return ['error' => 'User not found'];
                }

                $balanceField = $this->demoMode ? 'demo_balance' : 'balance';
                $currentBalance = (float) $user->{$balanceField};
                if ($amount > $currentBalance) {
                    return ['error' => 'Oops! You have no sufficient balance'];
                }

                $user->{$balanceField} = $currentBalance - $amount;
                $user->save();

                if (!$this->demoMode && $balanceField === 'balance') {
                    // Keep tenant session cache aligned with immediate debits so
                    // the WebView balance does not snap back on the next sync.
                    TenantSession::where('internal_user_id', $userId)
                        ->where('game_id', 'teen_patti')
                        ->when(
                            $this->tenantScopeId() > 0,
                            fn($query) => $query->where('tenant_id', $this->tenantScopeId())
                        )
                        ->where('status', 'active')
                        ->where('expires_at', '>', now())
                        ->update(['balance_cache' => (float) $user->balance]);
                }

                if (!$this->demoMode) {
                    $this->recordBetTransaction($userId, (float) $user->balance, $amount, $placeholder);
                }

                // Record round bet (DB-backed for concurrency safety; cache fallback)
                $this->recordRoundBet($round, $userId, $placeholder, $amount);

                return $this->buildBetResponse($round, $userId, (float) $user->{$balanceField});
            });
        } catch (\Throwable $e) {
            Log::error("[TP] placeBet failed: " . $e->getMessage());
            return ['error' => 'System busy, try again.'];
        }
    }

    private function placeTenantBet(
        TenantSession $tenantSession,
        int $userId,
        string $placeholder,
        float $amount,
        int $round
    ): array {
        $tenant = $tenantSession->tenant;
        if (!$tenant) {
            return ['error' => 'Wallet service unavailable'];
        }

        if ($this->currentPhase() !== 'betting' || $this->currentRound() !== $round) {
            return ['error' => 'Betting is closed!'];
        }

        $allowedChips = $tenant->teenPattiChipValues();
        if (!in_array((int) round($amount), $allowedChips, true)) {
            return ['error' => 'Invalid chip amount for this tenant'];
        }

        $walletRoundId = 'teen_patti_round_' . $round;
        $txnId = 'tp_db_' . now()->format('Ymd') . '_' . uniqid();
        $wallet = new TenantWebhookService($tenant);
        $debit = $wallet->debit($tenantSession, $amount, $walletRoundId, $txnId);

        if (!($debit['ok'] ?? false)) {
            return ['error' => $debit['message'] ?? 'Oops! You have no sufficient balance'];
        }

        if ($this->currentPhase() !== 'betting' || $this->currentRound() !== $round) {
            try {
                $wallet->rollback($tenantSession, $txnId, $walletRoundId . '_rollback');
            } catch (\Throwable $rollbackError) {
                Log::warning("[TP] tenant rollback failed after late bet close for user {$userId}: " . $rollbackError->getMessage());
            }

            return ['error' => 'Betting just closed. Please try in the next round.'];
        }

        try {
            return DB::transaction(function () use ($tenantSession, $userId, $placeholder, $amount, $round) {
                $tenantSession->refresh();

                $this->recordBetTransaction($userId, (float) $tenantSession->balance_cache, $amount, $placeholder);
                $this->recordRoundBet($round, $userId, $placeholder, $amount);

                return $this->buildBetResponse($round, $userId, (float) $tenantSession->balance_cache);
            });
        } catch (\Throwable $e) {
            Log::error("[TP] tenant bet finalize failed for user {$userId}: " . $e->getMessage());

            try {
                $wallet->rollback($tenantSession, $txnId, $walletRoundId . '_rollback_finalize');
            } catch (\Throwable $rollbackError) {
                Log::warning("[TP] tenant rollback failed after finalize error for user {$userId}: " . $rollbackError->getMessage());
            }

            return ['error' => 'System busy, try again.'];
        }
    }

    private function buildBetResponse(int $round, int $userId, float $balance): array
    {
        $bets = $this->getRoundBetSnapshot($round, true);
        $uid  = (string) $userId;

        return [
            'success' => true,
            'balance' => $balance,
            'my_bets' => $bets['users'][$uid] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0],
            'totals'  => [
                'silver'  => (float) ($bets['silver'] ?? 0),
                'gold'    => (float) ($bets['gold'] ?? 0),
                'diamond' => (float) ($bets['diamond'] ?? 0),
            ],
        ];
    }

    private function recordBetTransaction(int $userId, float $postBalance, float $amount, string $placeholder): void
    {
        $transaction               = new Transaction();
        $transaction->user_id      = $userId;
        $transaction->amount       = $amount;
        $transaction->post_balance = $postBalance;
        $transaction->charge       = 0;
        $transaction->trx_type     = '-';
        $transaction->details      = "TeenPatti Global Bet - {$placeholder}";
        $transaction->remark       = 'invest';
        $transaction->trx          = getTrx();
        $transaction->save();
    }

    /* History retrieval from DB with safe fallback behavior. */
    private function getHistorySafe(int $limit = 20): array
    {
        try {
            // Check if the model class and table exist
            if (!class_exists(\App\Models\TeenPattiRoundHistory::class)) {
                return $this->getHistoryFromCache($limit);
            }

            $historyQuery = \App\Models\TeenPattiRoundHistory::where('is_demo', $this->demoMode);
            $hasTenantColumn = Schema::hasColumn('teen_patti_round_history', 'tenant_id');

            if ($hasTenantColumn) {
                $historyQuery->where('tenant_id', $this->tenantScopeId());
            } elseif ($this->tenantScopeId() > 0) {
                // Avoid cross-tenant leakage before tenant_id migration is applied.
                return $this->getHistoryFromCache($limit);
            }

            $currentRound = $this->currentRound();
            $rows = $historyQuery
                ->where('round_number', '<=', $currentRound)
                ->orderByDesc('round_number')
                ->limit($limit)
                ->get(['round_number', 'winner', 'silver_total', 'gold_total', 'diamond_total',
                       'total_pool', 'silver_rank', 'gold_rank', 'diamond_rank', 'player_count', 'resolved_at'])
                ->map(fn($r) => [
                    'round'   => $r->round_number,
                    'winner'  => $r->winner,
                    'totals'  => ['silver' => $r->silver_total, 'gold' => $r->gold_total, 'diamond' => $r->diamond_total],
                    'pool'    => $r->total_pool,
                    'ranks'   => ['silver' => $r->silver_rank, 'gold' => $r->gold_rank, 'diamond' => $r->diamond_rank],
                    'players' => $r->player_count,
                    'time'    => $r->resolved_at?->diffForHumans(),
                ])
                ->toArray();

            if (!empty($rows)) {
                return $rows;
            }

            return $this->getHistoryFromCache($limit);
        } catch (\Throwable $e) {
            Log::warning("[TP] getHistory failed (table may not exist): " . $e->getMessage());
            return $this->getHistoryFromCache($limit);
        }
    }

    public function getHistory(int $limit = 20): array
    {
        return $this->getHistorySafe($limit);
    }

    /* Payout processing with guarded error handling. */
    private function processPayoutsSafe(int $round, string $winner, array $bets): array
    {
        try {
            return $this->processPayouts($round, $winner, $bets);
        } catch (\Throwable $e) {
            Log::error("[TP] processPayouts failed for round #{$round}: " . $e->getMessage());
            return [];
        }
    }

    protected function processPayouts(int $round, string $winner, array $bets): array
    {
        $totalPool  = ($bets['silver'] ?? 0) + ($bets['gold'] ?? 0) + ($bets['diamond'] ?? 0);
        $winnerBet  = (float) ($bets[$winner] ?? 0);

        $payouts = [];
        if ($winnerBet > 0) {
            $grossMultiplier = $totalPool / $winnerBet;
            foreach (($bets['users'] ?? []) as $uid => $ubets) {
                $userWinBet = (float) ($ubets[$winner] ?? 0);
                if ($userWinBet > 0) {
                    $commissionPercent = 10.0; // default platform commission
                    $tenantSession = null;
                    $tenant = null;
                    $fixedPayoutMultiplierX = null;
                    $payoutMode = 'dynamic_pool';
                    try {
                        $tenantSessionQuery = \App\Models\TenantSession::where('internal_user_id', (int) $uid)
                            ->where('status', 'active')
                            ->where('expires_at', '>', now())
                            ->latest('id');

                        if ($this->tenantScopeId() > 0) {
                            $tenantSessionQuery->where('tenant_id', $this->tenantScopeId());
                        }

                        $tenantSession = $tenantSessionQuery->first();

                        if ($tenantSession && $tenantSession->tenant) {
                            $tenant = $tenantSession->tenant;
                            $commissionPercent = (float) ($tenant->commission_percent ?? $commissionPercent);
                            $fixedPayoutMultiplierX = $this->tenantFixedPayoutMultiplierX($tenant, $winner);
                        }
                    } catch (\Throwable $e) {
                        // Tenant session lookup can fail safely
                    }

                    $commissionPercent = max(0.0, min(95.0, $commissionPercent));
                    if ($fixedPayoutMultiplierX !== null) {
                        $fixedPayoutMultiplierX = max(0.0, $fixedPayoutMultiplierX);
                        $netMultiplier = $fixedPayoutMultiplierX * ((100.0 - $commissionPercent) / 100.0);
                        $payoutMode = 'fixed_payout_x';
                    } else {
                        $netMultiplier = $grossMultiplier * ((100.0 - $commissionPercent) / 100.0);
                    }
                    $payout = $this->floorPayoutAmount($userWinBet * $netMultiplier);

                    $payouts[$uid] = [
                        'bet'    => $userWinBet,
                        'payout' => $payout,
                        'profit' => round($payout - $userWinBet, 2),
                        'multiplier' => round($netMultiplier, 4),
                        'payout_multiplier_x' => $fixedPayoutMultiplierX !== null ? round($fixedPayoutMultiplierX, 4) : null,
                        'profit_multiplier_x' => $fixedPayoutMultiplierX !== null ? round(max(0.0, $fixedPayoutMultiplierX - 1.0), 4) : null,
                        'commission_percent' => round($commissionPercent, 2),
                        'mode' => $payoutMode,
                    ];

                    try {
                        $user = User::find($uid);
                        if (!$user) continue;

                        if (!$this->demoMode && $tenantSession) {
                            try {
                                $wallet = app(\App\Modules\WalletBridge\WalletBridgeService::class);
                                $creditTxnId = $this->winCreditTxnId($tenantSession, $round, (int) $uid);
                                $creditAsyncEnabled = (bool) config('game.wallet_win_credit_async', false);
                                $creditResult = $wallet->credit(
                                    $tenantSession,
                                    $payout,
                                    'teen_patti_round_' . $round,
                                    $creditTxnId,
                                    "TeenPatti Win - Round {$round}",
                                    async: $creditAsyncEnabled
                                );

                                if ($creditResult === false) {
                                    Log::error("[TP] Tenant credit returned failure for user {$uid}", [
                                        'round' => $round,
                                        'tenant_session_id' => $tenantSession->id,
                                        'txn_id' => $creditTxnId,
                                        'amount' => $payout,
                                        'async' => $creditAsyncEnabled,
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("[TP] Tenant credit failed for user {$uid}: " . $e->getMessage());
                            }
                            continue;
                        }

                        // Normal (non-tenant) player
                        if ($this->demoMode) {
                            if ($this->hasLocalWinPayout($uid, $round)) {
                                continue;
                            }

                            $user->demo_balance += $payout;
                            $user->save();
                        } else {
                            if ($this->hasLocalWinPayout($uid, $round)) {
                                continue;
                            }

                            $user->balance += $payout;
                            $user->save();

                            $transaction               = new Transaction();
                            $transaction->user_id      = $uid;
                            $transaction->amount       = $payout;
                            $transaction->post_balance = $user->balance;
                            $transaction->charge       = 0;
                            $transaction->trx_type     = '+';
                            $transaction->details      = "TeenPatti Global Win - Round {$round}";
                            $transaction->trx          = getTrx();
                            $transaction->save();
                        }
                    } catch (\Throwable $e) {
                        Log::error("[TP] Payout for user {$uid} failed: " . $e->getMessage());
                    }
                }
            }
        }

        return $payouts;
    }

    private function winCreditTxnId(TenantSession $tenantSession, int $round, int $userId): string
    {
        return 'cr_tp_t' . $tenantSession->tenant_id . '_r' . $round . '_u' . $userId;
    }

    private function hasLocalWinPayout(int|string $userId, int $round): bool
    {
        if ($this->demoMode) {
            return false;
        }

        return Transaction::where('user_id', (int) $userId)
            ->where('trx_type', '+')
            ->where('details', "TeenPatti Global Win - Round {$round}")
            ->exists();
    }

    private function floorPayoutAmount(float $amount): float
    {
        return (float) max(0, (int) floor($amount));
    }

    private function nextFairRandomWinner(): string
    {
        $bagKey = 'tp_fair_winner_bag_' . ($this->demoMode ? 'd_' : 'l_') . $this->tenantScopeId();
        $bag = Cache::get($bagKey);

        if (!is_array($bag) || empty($bag)) {
            $bag = ['silver', 'gold', 'diamond'];
            for ($i = count($bag) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$bag[$i], $bag[$j]] = [$bag[$j], $bag[$i]];
            }
        }

        $winner = array_shift($bag);
        if (!in_array($winner, ['silver', 'gold', 'diamond'], true)) {
            $winner = 'gold';
            $bag = [];
        }

        Cache::put($bagKey, array_values($bag), now()->addHours(6));
        return $winner;
    }

    private function scopedTenantConfig(): ?Tenant
    {
        if ($this->demoMode || $this->tenantScopeId() <= 0) {
            return null;
        }

        if ($this->tenantConfig && $this->tenantConfig->id === $this->tenantScopeId()) {
            return $this->tenantConfig;
        }

        $this->tenantConfig = Tenant::find($this->tenantScopeId());
        return $this->tenantConfig;
    }

    private function manualOverrideWinnerSide(): ?string
    {
        $tenant = $this->scopedTenantConfig();
        if (!$tenant) {
            return null;
        }

        $mode = strtolower((string) ($tenant->result_mode ?? 'random'));
        if ($mode !== 'manual') {
            return null;
        }

        $manualSide = strtolower((string) ($tenant->manual_result_side ?? ''));
        return in_array($manualSide, ['silver', 'gold', 'diamond'], true) ? $manualSide : null;
    }

    private function tenantFixedPayoutMultiplierX(?Tenant $tenant, string $winner): ?float
    {
        if (!$tenant) {
            return null;
        }

        $column = match ($winner) {
            'silver' => 'silver_profit_x',
            'gold' => 'gold_profit_x',
            'diamond' => 'diamond_profit_x',
            default => null,
        };

        if (!$column) {
            return null;
        }

        $value = $tenant->{$column};
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /* History persistence with non-blocking error handling. */
    private function persistHistorySafe(int $round, string $winner, array $totals, array $hands, array $result, array $bets): void
    {
        try {
            if (!class_exists(\App\Models\TeenPattiRoundHistory::class)) {
                return;
            }

            $hasTenantColumn = Schema::hasColumn('teen_patti_round_history', 'tenant_id');
            if (!$hasTenantColumn && $this->tenantScopeId() > 0) {
                // Avoid storing tenant-scoped history in shared rows before migration.
                return;
            }

            $playerCount = count($bets['users'] ?? []);
            $lookup = ['round_number' => $round, 'is_demo' => $this->demoMode];
            $values = [
                'winner'        => $winner,
                'silver_total'  => round((float) ($totals['silver'] ?? 0), 2),
                'gold_total'    => round((float) ($totals['gold'] ?? 0), 2),
                'diamond_total' => round((float) ($totals['diamond'] ?? 0), 2),
                'total_pool'    => round(array_sum($totals), 2),
                'silver_cards'  => $hands['silver'] ?? [],
                'gold_cards'    => $hands['gold']   ?? [],
                'diamond_cards' => $hands['diamond'] ?? [],
                'silver_rank'   => $result['ranks']['silver'] ?? null,
                'gold_rank'     => $result['ranks']['gold']   ?? null,
                'diamond_rank'  => $result['ranks']['diamond'] ?? null,
                'player_count'  => $playerCount,
                'is_demo'       => $this->demoMode,
                'resolved_at'   => now(),
            ];

            if ($hasTenantColumn) {
                $lookup['tenant_id'] = $this->tenantScopeId();
                $values['tenant_id'] = $this->tenantScopeId();
            }

            \App\Models\TeenPattiRoundHistory::updateOrCreate($lookup, $values);
        } catch (\Throwable $e) {
            Log::warning("[TP] persistHistory failed for round #{$round}: " . $e->getMessage());
            // Non-blocking; game continues even if the history table is missing.
        }
    }

    /* ================================================================
     *  INTERNAL HELPERS
     * ================================================================ */
    private static function ensureRoundBetTable(): bool
    {
        if (self::$roundBetTableAvailable !== null) {
            return self::$roundBetTableAvailable;
        }

        try {
            if (!Schema::hasTable('teen_patti_round_bets')) {
                Schema::create('teen_patti_round_bets', static function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('round_number');
                    $table->unsignedBigInteger('tenant_id')->default(0);
                    $table->boolean('is_demo')->default(false);
                    $table->unsignedBigInteger('user_id');
                    $table->string('side', 20);
                    $table->decimal('amount', 15, 2)->default(0);
                    $table->timestamps();

                    $table->index(['round_number', 'tenant_id', 'is_demo'], 'tp_bets_round_tenant_demo_idx');
                    $table->index(['round_number', 'tenant_id', 'is_demo', 'side'], 'tp_bets_round_tenant_side_idx');
                    $table->index(['round_number', 'tenant_id', 'is_demo', 'user_id'], 'tp_bets_round_tenant_user_idx');
                });
            }

            if (!Schema::hasColumn('teen_patti_round_bets', 'tenant_id')) {
                Schema::table('teen_patti_round_bets', static function (Blueprint $table): void {
                    $table->unsignedBigInteger('tenant_id')->default(0)->after('round_number');
                });
            }

            self::$roundBetTableAvailable = Schema::hasTable('teen_patti_round_bets');
        } catch (\Throwable $e) {
            Log::warning('[TP] teen_patti_round_bets ensure failed: ' . $e->getMessage());
            self::$roundBetTableAvailable = false;
        }

        return self::$roundBetTableAvailable;
    }

    private function snapshotKey(int $round): string
    {
        return self::SNAPSHOT_PREFIX . ($this->demoMode ? 'd_' : 'l_') . $this->tenantScopeId() . '_' . $round;
    }

    private function historyCacheKey(): string
    {
        return self::HISTORY_PREFIX . ($this->demoMode ? 'd' : 'l') . '_' . $this->tenantScopeId();
    }

    private function recordRoundBet(int $round, int $userId, string $side, float $amount): void
    {
        if ($this->roundBetTableReady) {
            DB::table('teen_patti_round_bets')->insert([
                'round_number' => $round,
                'tenant_id'    => $this->tenantScopeId(),
                'is_demo'      => $this->demoMode ? 1 : 0,
                'user_id'      => $userId,
                'side'         => $side,
                'amount'       => $amount,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Cache::forget($this->snapshotKey($round));
            return;
        }

        // Fallback mode (legacy cache-only tracking)
        $betsKey = $this->betsKey($round);
        $bets    = Cache::get($betsKey, ['silver' => 0, 'gold' => 0, 'diamond' => 0, 'users' => []]);
        $bets[$side] = ($bets[$side] ?? 0) + $amount;
        $uid = (string) $userId;
        if (!isset($bets['users'][$uid])) {
            $bets['users'][$uid] = ['silver' => 0, 'gold' => 0, 'diamond' => 0];
        }
        $bets['users'][$uid][$side] += $amount;
        Cache::put($betsKey, $bets, now()->addMinutes(10));
    }

    private function getRoundBetSnapshot(int $round, bool $fresh = false): array
    {
        $cacheKey = $this->snapshotKey($round);
        if (!$fresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['silver'], $cached['gold'], $cached['diamond'], $cached['users'])) {
                return $cached;
            }
        }

        if ($this->roundBetTableReady) {
            $rows = DB::table('teen_patti_round_bets')
                ->select('user_id', 'side', DB::raw('SUM(amount) as total_amount'))
                ->where('round_number', $round)
                ->where('tenant_id', $this->tenantScopeId())
                ->where('is_demo', $this->demoMode ? 1 : 0)
                ->groupBy('user_id', 'side')
                ->get();

            $snapshot = ['silver' => 0.0, 'gold' => 0.0, 'diamond' => 0.0, 'users' => []];

            foreach ($rows as $row) {
                $side = strtolower((string) $row->side);
                if (!in_array($side, ['silver', 'gold', 'diamond'], true)) {
                    continue;
                }

                $uid = (string) $row->user_id;
                $val = round((float) $row->total_amount, 2);
                $snapshot[$side] += $val;
                if (!isset($snapshot['users'][$uid])) {
                    $snapshot['users'][$uid] = ['silver' => 0.0, 'gold' => 0.0, 'diamond' => 0.0];
                }
                $snapshot['users'][$uid][$side] = $val;
            }

            foreach (['silver', 'gold', 'diamond'] as $side) {
                $snapshot[$side] = round((float) $snapshot[$side], 2);
            }

            Cache::put($cacheKey, $snapshot, now()->addSeconds(2));
            return $snapshot;
        }

        $fallback = Cache::get($this->betsKey($round), ['silver' => 0, 'gold' => 0, 'diamond' => 0, 'users' => []]);
        return [
            'silver'  => (float) ($fallback['silver'] ?? 0),
            'gold'    => (float) ($fallback['gold'] ?? 0),
            'diamond' => (float) ($fallback['diamond'] ?? 0),
            'users'   => is_array($fallback['users'] ?? null) ? $fallback['users'] : [],
        ];
    }

    private function pushHistoryCache(array $result, array $totals, int $playerCount): void
    {
        $item = [
            'round'   => (int) ($result['round'] ?? 0),
            'winner'  => strtolower((string) ($result['winner'] ?? '')),
            'totals'  => [
                'silver'  => round((float) ($totals['silver'] ?? 0), 2),
                'gold'    => round((float) ($totals['gold'] ?? 0), 2),
                'diamond' => round((float) ($totals['diamond'] ?? 0), 2),
            ],
            'pool'    => round((float) array_sum($totals), 2),
            'ranks'   => $result['ranks'] ?? [],
            'players' => max(0, $playerCount),
            'time'    => now()->diffForHumans(),
        ];

        $history = Cache::get($this->historyCacheKey(), []);
        array_unshift($history, $item);
        Cache::put($this->historyCacheKey(), array_slice($history, 0, 100), now()->addDays(1));
    }

    private function getHistoryFromCache(int $limit = 20): array
    {
        $limit = max(1, $limit);
        $rows = [];

        $history = Cache::get($this->historyCacheKey(), []);
        if (is_array($history)) {
            foreach ($history as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $normalized = $this->normalizeHistoryItem($item);
                if ($normalized === null) {
                    continue;
                }

                $rows[(int) $normalized['round']] = $normalized;
            }
        }

        // Extra fallback: rebuild from per-round result cache when history list is empty/stale.
        $currentRound = $this->currentRound();
        $windowFloor = max(0, $currentRound - ($limit + 40));
        for ($round = $currentRound; $round >= $windowFloor; $round--) {
            if (isset($rows[$round])) {
                continue;
            }

            $result = Cache::get($this->resultKey($round));
            if (!is_array($result) || empty($result['winner'])) {
                continue;
            }

            $rows[$round] = $this->historyItemFromResultCache($result, $round);
            if (count($rows) >= ($limit + 10)) {
                break;
            }
        }

        if (empty($rows)) {
            return [];
        }

        $list = array_values($rows);
        usort($list, static fn(array $a, array $b): int => (int) $b['round'] <=> (int) $a['round']);
        return array_slice($list, 0, $limit);
    }

    private function normalizeHistoryItem(array $item): ?array
    {
        $round = (int) ($item['round'] ?? 0);
        if ($round <= 0) {
            return null;
        }

        $winner = strtolower((string) ($item['winner'] ?? ''));
        if (!in_array($winner, ['silver', 'gold', 'diamond'], true)) {
            return null;
        }

        $totalsRaw = is_array($item['totals'] ?? null) ? $item['totals'] : [];
        $totals = [
            'silver' => round((float) ($totalsRaw['silver'] ?? 0), 2),
            'gold' => round((float) ($totalsRaw['gold'] ?? 0), 2),
            'diamond' => round((float) ($totalsRaw['diamond'] ?? 0), 2),
        ];

        return [
            'round' => $round,
            'winner' => $winner,
            'totals' => $totals,
            'pool' => round((float) ($item['pool'] ?? array_sum($totals)), 2),
            'ranks' => is_array($item['ranks'] ?? null) ? $item['ranks'] : [],
            'players' => max(0, (int) ($item['players'] ?? 0)),
            'time' => (string) ($item['time'] ?? $this->roundTimeLabel($round)),
        ];
    }

    private function historyItemFromResultCache(array $result, int $round): array
    {
        $totalsRaw = is_array($result['totals'] ?? null) ? $result['totals'] : [];
        $totals = [
            'silver' => round((float) ($totalsRaw['silver'] ?? 0), 2),
            'gold' => round((float) ($totalsRaw['gold'] ?? 0), 2),
            'diamond' => round((float) ($totalsRaw['diamond'] ?? 0), 2),
        ];
        $players = 0;
        try {
            $snapshot = $this->getRoundBetSnapshot($round);
            $players = max(0, is_array($snapshot['users'] ?? null) ? count($snapshot['users']) : 0);
        } catch (\Throwable $e) {
            $players = 0;
        }
        if ($players === 0) {
            $players = max(0, is_array($result['user_payouts'] ?? null) ? count($result['user_payouts']) : 0);
        }

        return [
            'round' => $round,
            'winner' => strtolower((string) ($result['winner'] ?? '')),
            'totals' => $totals,
            'pool' => round((float) array_sum($totals), 2),
            'ranks' => is_array($result['ranks'] ?? null) ? $result['ranks'] : [],
            'players' => $players,
            'time' => $this->roundTimeLabel($round),
        ];
    }

    private function roundTimeLabel(int $round): string
    {
        $resolvedAt = ($round * self::ROUND_DURATION) + self::BET_WINDOW;
        $delta = max(0, time() - $resolvedAt);
        return now()->subSeconds($delta)->diffForHumans();
    }

    private function getRoundBets(int $round): array
    {
        $bets = $this->getRoundBetSnapshot($round);
        return [
            'silver'  => (float) ($bets['silver']  ?? 0),
            'gold'    => (float) ($bets['gold']    ?? 0),
            'diamond' => (float) ($bets['diamond'] ?? 0),
        ];
    }

    private function getUserBets(int $round, int $userId): array
    {
        $bets = $this->getRoundBetSnapshot($round);
        $uid  = (string) $userId;
        return $bets['users'][$uid] ?? ['silver' => 0, 'gold' => 0, 'diamond' => 0];
    }

    private function getCachedResult(int $round): ?array
    {
        $result = Cache::get($this->resultKey($round));
        // Only return if it's a valid result with a winner
        if (is_array($result) && isset($result['winner'])) {
            return $result;
        }
        return null;
    }

    public function currentRound(): int    { return (int) floor(time() / self::ROUND_DURATION); }
    public function currentPhase(): string { return (time() % self::ROUND_DURATION) < self::BET_WINDOW ? 'betting' : 'hold'; }
    public function secondsRemaining(): int {
        $elapsed = time() % self::ROUND_DURATION;
        return $elapsed < self::BET_WINDOW ? self::BET_WINDOW - $elapsed : self::ROUND_DURATION - $elapsed;
    }

    protected function betsKey(int $round): string
    {
        return self::BETS_PREFIX . ($this->demoMode ? 'd_' : 'l_') . $this->tenantScopeId() . '_' . $round;
    }

    protected function resultKey(int $round): string
    {
        return self::RESULT_PREFIX . ($this->demoMode ? 'd_' : 'l_') . $this->tenantScopeId() . '_' . $round;
    }

    private function tenantScopeId(): int
    {
        // Demo rounds stay globally scoped under 0.
        if ($this->demoMode) {
            return 0;
        }

        return max(0, $this->tenantId);
    }

    /* ================================================================
     *  CARD DEALING
     * ================================================================ */
    private function dealRankedHands(): array
    {
        $deck = $this->fullDeck();
        shuffle($deck);
        $hands = ['silver' => [], 'gold' => [], 'diamond' => []];

        foreach (['silver', 'gold', 'diamond'] as $side) {
            $hands[$side] = [array_pop($deck), array_pop($deck), array_pop($deck)];
        }

        return $hands;
    }

    private function dealHandsForWinner(string $winner): array
    {
        for ($attempt = 0; $attempt < 250; $attempt++) {
            $hands = $this->dealRankedHands();
            if ($this->determineWinnerFromHands($hands) === $winner) {
                return $hands;
            }
        }

        return $this->forcedWinnerHands($winner);
    }

    private function forcedWinnerHands(string $winner): array
    {
        $winner = in_array($winner, ['silver', 'gold', 'diamond'], true) ? $winner : 'gold';
        $hands = [
            'silver' => ['K-H', '9-D', '4-S'],
            'gold' => ['Q-C', '8-H', '3-D'],
            'diamond' => ['J-S', '7-C', '2-H'],
        ];

        $hands[$winner] = ['A-S', 'A-H', 'A-D'];

        return $hands;
    }

    private function handScores(array $hands): array
    {
        return [
            'silver' => $this->rankHand($hands['silver'] ?? []),
            'gold' => $this->rankHand($hands['gold'] ?? []),
            'diamond' => $this->rankHand($hands['diamond'] ?? []),
        ];
    }

    private function determineWinnerFromHands(array $hands): string
    {
        $scores = $this->handScores($hands);
        $maxScore = max($scores);
        $candidates = array_keys(array_filter($scores, static fn ($score) => $score === $maxScore));

        if (!$candidates) {
            return 'gold';
        }

        return $candidates[array_rand($candidates)];
    }

    private function fullDeck(): array
    {
        $suits = ['H', 'D', 'C', 'S'];
        $ranks = array_merge(range(2, 10), ['J', 'Q', 'K', 'A']);
        $deck = [];
        foreach ($suits as $s) {
            foreach ($ranks as $r) {
                $deck[] = "{$r}-{$s}";
            }
        }
        return $deck;
    }

    private function cardValue(string $rank): int
    {
        $values = [
            '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7,
            '8' => 8, '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14,
        ];
        return $values[$rank] ?? 0;
    }

    private function rankHand(array $cards): int
    {
        $ranks = [];
        $suits = [];

        foreach ($cards as $card) {
            $parts = explode('-', (string) $card);
            if (count($parts) !== 2) continue;
            $ranks[] = $this->cardValue($parts[0]);
            $suits[] = $parts[1];
        }

        if (count($ranks) < 3) return 0;

        sort($ranks);

        $isFlush = count(array_unique($suits)) === 1;
        $isStraight = false;
        $highCard = $ranks[2];

        if (($ranks[2] - $ranks[1] === 1) && ($ranks[1] - $ranks[0] === 1)) {
            $isStraight = true;
        }

        // A-2-3 special case
        if ($ranks[0] === 2 && $ranks[1] === 3 && $ranks[2] === 14) {
            $isStraight = true;
            $highCard = 3;
        }

        if ($ranks[0] === $ranks[1] && $ranks[1] === $ranks[2]) {
            return 6 * 10000 + $ranks[2]; // Trail
        }
        if ($isStraight && $isFlush) {
            return 5 * 10000 + $highCard; // Pure Sequence
        }
        if ($isStraight) {
            return 4 * 10000 + $highCard; // Sequence
        }
        if ($isFlush) {
            return 3 * 10000 + $ranks[2] * 100 + $ranks[1] * 10 + $ranks[0]; // Flush
        }
        if ($ranks[0] === $ranks[1] || $ranks[1] === $ranks[2]) {
            $pairValue = $ranks[1];
            $kicker = ($ranks[0] === $ranks[1]) ? $ranks[2] : $ranks[0];
            return 2 * 10000 + $pairValue * 100 + $kicker; // Pair
        }

        return 1 * 10000 + $ranks[2] * 100 + $ranks[1] * 10 + $ranks[0]; // High Card
    }

    private function rankLabel(array $cards): string
    {
        $score = $this->rankHand($cards);
        $type = (int) floor($score / 10000);

        return match ($type) {
            6 => 'Trail',
            5 => 'Pure Sequence',
            4 => 'Sequence',
            3 => 'Color',
            2 => 'Pair',
            default => 'High Card',
        };
    }

    private function highCard(array $cards): string
    {
        $best = '';
        $bestValue = -1;

        foreach ($cards as $card) {
            $parts = explode('-', (string) $card);
            if (count($parts) !== 2) continue;

            $value = $this->cardValue($parts[0]);
            if ($value > $bestValue) {
                $bestValue = $value;
                $best = strtoupper($parts[0]) . '-' . strtoupper($parts[1]);
            }
        }

        return $best;
    }
}
