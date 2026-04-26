<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'result_mode')) {
                $table->string('result_mode', 20)->default('random')->after('diamond_profit_x');
            }

            if (!Schema::hasColumn('tenants', 'manual_result_side')) {
                $table->string('manual_result_side', 20)->nullable()->after('result_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'manual_result_side')) {
                $table->dropColumn('manual_result_side');
            }

            if (Schema::hasColumn('tenants', 'result_mode')) {
                $table->dropColumn('result_mode');
            }
        });
    }
};
