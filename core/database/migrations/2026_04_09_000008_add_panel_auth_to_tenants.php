<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Panel login credentials for the tenant's own dashboard
            $table->string('email')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('email');

            // 'internal' = balances stored in our DB (tenant tops up via panel)
            // 'webhook'  = balances managed by tenant's own server (outgoing HTTP webhooks)
            $table->enum('balance_mode', ['internal', 'webhook'])->default('internal')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['email', 'password', 'balance_mode']);
        });
    }
};
