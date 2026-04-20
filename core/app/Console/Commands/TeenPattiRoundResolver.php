<?php

namespace App\Console\Commands;

use App\Services\TeenPattiGlobalManager;
use Illuminate\Console\Command;

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
            $manager = new TeenPattiGlobalManager($isDemo);

            if ($manager->currentPhase() !== 'hold') {
                $this->line("[{$label}] Phase is 'betting' — nothing to resolve.");
                continue;
            }

            $round = $manager->currentRound();
            $this->line("[{$label}] Resolving round #{$round}...");

            try {
                $result = $manager->resolveRound($round);
                $winner = $result['winner'] ?? 'unknown';
                $this->info("[{$label}] Round #{$round} resolved. Winner: {$winner}");
            } catch (\Throwable $e) {
                $this->error("[{$label}] Failed: " . $e->getMessage());
            }
        }

        return 0;
    }
}
