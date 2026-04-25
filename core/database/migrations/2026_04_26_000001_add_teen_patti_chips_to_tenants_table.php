<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'teen_patti_chips')) {
                $table->json('teen_patti_chips')->nullable()->after('diamond_profit_x');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'teen_patti_chips')) {
                $table->dropColumn('teen_patti_chips');
            }
        });
    }
};
