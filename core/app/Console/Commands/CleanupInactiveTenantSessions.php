<?php

namespace App\Console\Commands;

use App\Models\TenantSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CleanupInactiveTenantSessions extends Command
{
    protected $signature = 'tenant:sessions:cleanup';
    protected $description = 'Close tenant sessions that are inactive for too long';

    public function handle(): int
    {
        if (!Schema::hasTable('tenant_sessions')) {
            $this->line('tenant_sessions table not found, skipping cleanup.');
            return self::SUCCESS;
        }

        $idleMinutes = max(1, (int) config('game.tenant_session_idle_timeout_minutes', 5));
        $cutoff = now()->subMinutes($idleMinutes);

        $closedInactive = TenantSession::where('status', 'active')
            ->where(function ($query) use ($cutoff) {
                $query->whereNotNull('last_activity_at')
                    ->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff) {
                        $nested->whereNull('last_activity_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->update([
                'status' => 'closed',
                'expires_at' => now(),
                'last_activity_at' => now(),
            ]);

        $expired = TenantSession::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->line("Closed inactive sessions: {$closedInactive}");
        $this->line("Expired sessions marked: {$expired}");

        return self::SUCCESS;
    }
}
