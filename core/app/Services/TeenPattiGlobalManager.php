<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Game as GameModel;
use App\Models\TenantSession;
use App\Models\User;
use App\Models\Transaction;

/**
 * TeenPattiGlobalManager
 *
 * Manages the global betting round with 20s betting and 15s result/hold phase.
 * Winner logic: lowest total bet placeholder wins (transparent pool rule).
 *
 * DESIGN RULES (bulletproof for shared hosting):
 *  - NO Cache::lock() — file driver locks are unreliable on shared hosts.
 *  - ALL DB writes wrapped in try/catch — never crash the game flow.
 *  - Result is computed once and cached; re-reads from cache on subsequent calls.
 *  - History persistence is best-effort (non-blocking).
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
    private bool $roundBetTableReady = false;
    private static ?bool $roundBetTableAvailable = null;

    public function __construct(bool $demoMode = false)
    {
        $this->demoMode = $demoMode;
        $this->roundBetTableReady = self::ensureRoundBetTable();
    }

    /* ================================================================
     *  SYNC — single entry point, returns everything the frontend needs
     * ================================================================ */
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

        // History — best-effort, never crash
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

    /* ================================================================
     *  RESOLVE ROUND — compute winner, deal cards, process payouts
     *  No locks, no DB dependency. Pure cache-based.
     * ================================================================ */
    public function resolveRound(int $round): ?array
    {
        $resultKey = $this->resultKey($round);

        // 1. Already resolved? Return cached result.
        $existing = Cache::get($resultKey);
        if ($existing && is_array($existing) && isset($existing['winner'])) {
            return $existing;
        }

        // 2. Compute the result (no locks needed — idempotent operation)
        try {
            $bets = $this->getRoundBetSnapshot($round, true);

            $totals = [
                'silver'  => (float) ($bets['silver'] ?? 0),
                'gold'    => (float) ($bets['gold'] ?? 0),
                'diamond' => (float) ($bets['diamond'] ?? 0),
            ];

            // Winner by pool rule: the deck with lowest total pool wins.
            asort($totals);
            $winner = array_key_first($totals);

            // Deal hands (rigged so winner gets best hand)
            $hands = $this->dealRiggedHands($winner);

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
                'totals' => [
                    'silver'  => round((float) $totals['silver'], 2),
                    'gold'    => round((float) $totals['gold'], 2),
                    'diamond' => round((float) $totals['diamond'], 2),
                ],
                'reason' => sprintf(
                    'Pool rule: %s had the lowest total bets (%.2f), so it wins this round.',
                    ucfirst($winner),
                    (float) $totals[$winner]
                ),
                'user_payouts' => $this->processPayoutsSafe($round, $winner, $bets),
            ];

            // 3. Cache the result (15 minutes — way longer than round duration)
            Cache::put($resultKey, $result, now()->addMinutes(15));

            Log::info("[TP] Round #{$round} resolved. Winner: {$winner}");

            // 4. Best-effort: persist to DB history (non-blocking)
            $this->persistHistorySafe($round, $winner, $totals, $hands, $result, $bets);
            $this->pushHistoryCache($result, $totals, count($bets['users'] ?? []));

            return $result;

        } catch (\Throwable $e) {
            Log::error("[TP] resolveRound failed for round #{$round}: " . $e->getMessage());

            // Even on failure, try to return whatever is in cache
            return Cache::get($resultKey);
        }
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

    /* ================================================================
     *  HISTORY — safe retrieval from DB (never crashes)
     * ================================================================ */
    private function getHistorySafe(int $limit = 20): array
    {
        try {
            // Check if the model class and table exist
            if (!class_exists(\App\Models\TeenPattiRoundHistory::class)) {
                return $this->getHistoryFromCache($limit);
            }

            $rows = \App\Models\TeenPattiRoundHistory::where('is_demo', $this->demoMode)
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

    /* ================================================================
     *  PAYOUTS — safe processing (never crashes resolveRound)
     * ================================================================ */
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
                    try {
                        $tenantSession = \App\Models\TenantSession::findActiveByUserId((int) $uid);
                        if ($tenantSession && $tenantSession->tenant) {
                            $commissionPercent = (float) ($tenantSession->tenant->commission_percent ?? $commissionPercent);
                        }
                    } catch (\Throwable $e) {
                        // Tenant session lookup can fail safely
                    }

                    $commissionPercent = max(0.0, min(95.0, $commissionPercent));
                    $netMultiplier = $grossMultiplier * ((100.0 - $commissionPercent) / 100.0);
                    $payout = round($userWinBet * $netMultiplier, 2);

                    $payouts[$uid] = [
                        'bet'    => $userWinBet,
                        'payout' => $payout,
                        'profit' => round($payout - $userWinBet, 2),
                        'multiplier' => round($netMultiplier, 4),
                        'commission_percent' => round($commissionPercent, 2),
                    ];

                    try {
                        $user = User::find($uid);
                        if (!$user) continue;

                        if (!$this->demoMode && $tenantSession) {
                            try {
                                $wallet = app(\App\Modules\WalletBridge\WalletBridgeService::class);
                                $creditTxnId = 'cr_' . now()->format('Ymd') . '_' . uniqid();
                                $wallet->credit(
                                    $tenantSession,
                                    $payout,
                                    'teen_patti_round_' . $round,
                                    $creditTxnId,
                                    "TeenPatti Win - Round {$round}",
                                    async: true
                                );
                            } catch (\Throwable $e) {
                                Log::warning("[TP] Tenant credit failed for user {$uid}: " . $e->getMessage());
                            }
                            continue;
                        }

                        // ── Normal (non-tenant) player ───────────────────────────
                        if ($this->demoMode) {
                            $user->demo_balance += $payout;
                            $user->save();
                        } else {
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

    /* ================================================================
     *  HISTORY PERSISTENCE — best-effort, non-blocking
     * ================================================================ */
    private function persistHistorySafe(int $round, string $winner, array $totals, array $hands, array $result, array $bets): void
    {
        try {
            if (!class_exists(\App\Models\TeenPattiRoundHistory::class)) {
                return;
            }

            $playerCount = count($bets['users'] ?? []);
            \App\Models\TeenPattiRoundHistory::updateOrCreate(
                ['round_number' => $round, 'is_demo' => $this->demoMode],
                [
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
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("[TP] persistHistory failed for round #{$round}: " . $e->getMessage());
            // Non-blocking — game continues even if history table doesn't exist
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
                    $table->boolean('is_demo')->default(false);
                    $table->unsignedBigInteger('user_id');
                    $table->string('side', 20);
                    $table->decimal('amount', 15, 2)->default(0);
                    $table->timestamps();

                    $table->index(['round_number', 'is_demo'], 'tp_bets_round_demo_idx');
                    $table->index(['round_number', 'is_demo', 'side'], 'tp_bets_round_side_idx');
                    $table->index(['round_number', 'is_demo', 'user_id'], 'tp_bets_round_user_idx');
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
        return self::SNAPSHOT_PREFIX . ($this->demoMode ? 'd_' : 'l_') . $round;
    }

    private function historyCacheKey(): string
    {
        return self::HISTORY_PREFIX . ($this->demoMode ? 'd' : 'l');
    }

    private function recordRoundBet(int $round, int $userId, string $side, float $amount): void
    {
        if ($this->roundBetTableReady) {
            DB::table('teen_patti_round_bets')->insert([
                'round_number' => $round,
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

    protected function betsKey(int $round): string   { return self::BETS_PREFIX . ($this->demoMode ? 'd_' : '') . $round; }
    protected function resultKey(int $round): string  { return self::RESULT_PREFIX . ($this->demoMode ? 'd_' : '') . $round; }

    /* ================================================================
     *  CARD DEALING
     * ================================================================ */
    private function dealRiggedHands(string $winner): array
    {
        $deck = $this->fullDeck();
        shuffle($deck);
        $hands = ['silver' => [], 'gold' => [], 'diamond' => []];

        // Give winner 3 random cards first
        $hands[$winner] = [array_pop($deck), array_pop($deck), array_pop($deck)];

        // Give losers 3 random cards each
        foreach (['silver', 'gold', 'diamond'] as $ph) {
            if ($ph !== $winner) {
                $hands[$ph] = [array_pop($deck), array_pop($deck), array_pop($deck)];
            }
        }
        return $hands;
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
            3 => 'Flush',
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
