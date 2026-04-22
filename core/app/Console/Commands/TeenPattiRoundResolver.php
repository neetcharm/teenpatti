<?php

namespace App\Console\Commands;

use App\Services\TeenPattiGlobalManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures Teen Patti rounds are resolved even when no players are online.
 *
 * Schedule: every minute.
 *
 * What it does:
 *  - If the current phase is 'hold' (result phase), calls resolveRound() on both
 *    live and demo managers. The resolve method is idempotent (skips if already done).
 *  - Cleans up Cache keys for rounds older than 2 hours (handled by Cache TTL).
 *
 * Register in bootstrap/app.php schedule() or in App\Console\Kernel.php.
 */
class TeenPattiRoundResolver extends Command
{
    protected $signature   = 'teen-patti:resolve {--demo : Only resolve demo rounds}';
    protected $description = 'Auto-resolve Teen Patti rounds so 24/7 history is maintained';

    public function handle(): int
    {
        $modes = $this->option('demo') ? [true] : [false, true];

        foreach ($modes as $isDemo) {
            $label   = $isDemo ? 'demo' : 'live';
            $manager = new TeenPattiGlobalManager($isDemo, 0);

            if ($manager->currentPhase() !== 'hold') {
                $this->line("[{$label}] Phase is 'betting' — nothing to resolve.");
                continue;
            }

            $round = $manager->currentRound();
            $tenantScopes = [0];

            if (!$isDemo && Schema::hasTable('teen_patti_round_bets')) {
                try {
                    $tenantScopes = DB::table('teen_patti_round_bets')
                        ->where('round_number', $round)
                        ->where('is_demo', 0)
                        ->distinct()
                        ->pluck('tenant_id')
                        ->map(fn($id) => max(0, (int) $id))
                        ->push(0)
                        ->unique()
                        ->values()
                        ->all();
                } catch (\Throwable $e) {
                    $tenantScopes = [0];
                }
            }

            foreach ($tenantScopes as $tenantScopeId) {
                $scopeLabel = $isDemo ? 'demo' : ($tenantScopeId > 0 ? 'tenant:' . $tenantScopeId : 'public');
                $this->line("[{$label}/{$scopeLabel}] Resolving round #{$round}...");

                try {
                    $scopeManager = new TeenPattiGlobalManager($isDemo, $tenantScopeId);
                    $result = $scopeManager->resolveRound($round);
                    $winner = $result['winner'] ?? 'unknown';
                    $this->info("[{$label}/{$scopeLabel}] Round #{$round} resolved. Winner: {$winner}");
                } catch (\Throwable $e) {
                    $this->error("[{$label}/{$scopeLabel}] Failed: " . $e->getMessage());
                }
            }
        }

        return 0;
    }
}
