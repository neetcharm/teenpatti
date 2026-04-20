<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('session_token', 128)->unique();   // Sent in WebView URL
            $table->string('player_id', 100);                 // Tenant's user ID
            $table->string('player_name', 100);               // Display name
            $table->string('game_id', 50)->default('teen_patti'); // Which game
            $table->string('currency', 10)->default('INR');
            $table->foreignId('internal_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('balance_cache', 15, 2)->default(0); // Last known balance from tenant
            $table->string('lang', 10)->default('en');
            $table->string('status', 20)->default('active');  // active | expired | closed
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'player_id']);
            $table->index(['internal_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sessions');
    }
};
