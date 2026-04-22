<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateRoundBetsTable();
        $this->updateRoundHistoryTable();
    }

    public function down(): void
    {
        if (Schema::hasTable('teen_patti_round_bets')) {
            try {
                Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                    $table->dropIndex('tp_bets_round_tenant_demo_idx');
                });
            } catch (\Throwable $e) {
            }

            try {
                Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                    $table->dropIndex('tp_bets_round_tenant_side_idx');
                });
            } catch (\Throwable $e) {
            }

            try {
                Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                    $table->dropIndex('tp_bets_round_tenant_user_idx');
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('teen_patti_round_history')) {
            try {
                Schema::table('teen_patti_round_history', function (Blueprint $table): void {
                    $table->dropUnique('tp_round_demo_tenant_unique');
                });
            } catch (\Throwable $e) {
            }

            try {
                Schema::table('teen_patti_round_history', function (Blueprint $table): void {
                    $table->dropIndex('tp_hist_tenant_demo_round_idx');
                });
            } catch (\Throwable $e) {
            }
        }
    }

    private function updateRoundBetsTable(): void
    {
        if (!Schema::hasTable('teen_patti_round_bets')) {
            return;
        }

        if (!Schema::hasColumn('teen_patti_round_bets', 'tenant_id')) {
            Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->default(0)->after('round_number');
            });
        }

        // Drop old indexes when present.
        foreach (['tp_bets_round_demo_idx', 'tp_bets_round_side_idx', 'tp_bets_round_user_idx'] as $oldIndex) {
            try {
                Schema::table('teen_patti_round_bets', function (Blueprint $table) use ($oldIndex): void {
                    $table->dropIndex($oldIndex);
                });
            } catch (\Throwable $e) {
            }
        }

        // Add tenant-scoped indexes.
        try {
            Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                $table->index(['round_number', 'tenant_id', 'is_demo'], 'tp_bets_round_tenant_demo_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                $table->index(['round_number', 'tenant_id', 'is_demo', 'side'], 'tp_bets_round_tenant_side_idx');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('teen_patti_round_bets', function (Blueprint $table): void {
                $table->index(['round_number', 'tenant_id', 'is_demo', 'user_id'], 'tp_bets_round_tenant_user_idx');
            });
        } catch (\Throwable $e) {
        }
    }

    private function updateRoundHistoryTable(): void
    {
        if (!Schema::hasTable('teen_patti_round_history')) {
            return;
        }

        if (!Schema::hasColumn('teen_patti_round_history', 'tenant_id')) {
            Schema::table('teen_patti_round_history', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->default(0)->after('round_number');
            });
        }

        // Remove legacy unique variants.
        foreach (['tp_round_demo_unique', 'teen_patti_round_history_round_number_unique'] as $uniqueName) {
            try {
                Schema::table('teen_patti_round_history', function (Blueprint $table) use ($uniqueName): void {
                    $table->dropUnique($uniqueName);
                });
            } catch (\Throwable $e) {
            }
        }

        try {
            Schema::table('teen_patti_round_history', function (Blueprint $table): void {
                $table->unique(['round_number', 'is_demo', 'tenant_id'], 'tp_round_demo_tenant_unique');
            });
        } catch (\Throwable $e) {
        }

        try {
            Schema::table('teen_patti_round_history', function (Blueprint $table): void {
                $table->index(['tenant_id', 'is_demo', 'round_number'], 'tp_hist_tenant_demo_round_idx');
            });
        } catch (\Throwable $e) {
        }
    }
};

