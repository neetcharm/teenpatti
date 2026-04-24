<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'silver_profit_x')) {
                $table->decimal('silver_profit_x', 8, 2)->nullable()->after('max_bet');
            }

            if (!Schema::hasColumn('tenants', 'gold_profit_x')) {
                $table->decimal('gold_profit_x', 8, 2)->nullable()->after('silver_profit_x');
            }

            if (!Schema::hasColumn('tenants', 'diamond_profit_x')) {
                $table->decimal('diamond_profit_x', 8, 2)->nullable()->after('gold_profit_x');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $drop = [];

            if (Schema::hasColumn('tenants', 'silver_profit_x')) {
                $drop[] = 'silver_profit_x';
            }

            if (Schema::hasColumn('tenants', 'gold_profit_x')) {
                $drop[] = 'gold_profit_x';
            }

            if (Schema::hasColumn('tenants', 'diamond_profit_x')) {
                $drop[] = 'diamond_profit_x';
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
