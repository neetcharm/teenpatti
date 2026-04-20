<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->fixCompositeUnique('teen_patti_round_history', 'tp_round_demo_unique');
    }

    public function down(): void
    {
        $this->revertToSingleUnique('teen_patti_round_history', 'tp_round_demo_unique');
    }

    private function fixCompositeUnique(string $tableName, string $compositeName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'round_number') || !Schema::hasColumn($tableName, 'is_demo')) {
            return;
        }

        // Drop old single-column unique if present.
        try {
            $legacyIndexName = $tableName . '_round_number_unique';
            Schema::table($tableName, function (Blueprint $table) use ($legacyIndexName): void {
                $table->dropUnique($legacyIndexName);
            });
        } catch (\Throwable $e) {
            try {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique(['round_number']);
                });
            } catch (\Throwable $ignored) {
                // Already dropped or never existed.
            }
        }

        // Add composite unique for live+demo coexistence.
        try {
            Schema::table($tableName, function (Blueprint $table) use ($compositeName): void {
                $table->unique(['round_number', 'is_demo'], $compositeName);
            });
        } catch (\Throwable $ignored) {
            // Already exists.
        }
    }

    private function revertToSingleUnique(string $tableName, string $compositeName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'round_number')) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($compositeName): void {
                $table->dropUnique($compositeName);
            });
        } catch (\Throwable $ignored) {
        }

        try {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unique('round_number');
            });
        } catch (\Throwable $ignored) {
        }
    }
};
