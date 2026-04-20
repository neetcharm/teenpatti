<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TenantRuntimeSchema
{
    public static function ensureBaseTables(): void
    {
        self::ensureTenantSessionsTable();
        self::ensureTenantGamesTable();
        self::ensureTenantTransactionsTable();
    }

    private static function ensureTenantSessionsTable(): void
    {
        if (Schema::hasTable('tenant_sessions')) {
            return;
        }

        Schema::create('tenant_sessions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('session_token', 128)->unique();
            $table->string('player_id', 100);
            $table->string('player_name', 100);
            $table->string('game_id', 50)->default('teen_patti');
            $table->string('currency', 10)->default('INR');
            $table->foreignId('internal_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('balance_cache', 15, 2)->default(0);
            $table->string('lang', 10)->default('en');
            $table->string('status', 20)->default('active');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'player_id']);
            $table->index(['internal_user_id', 'status']);
        });
    }

    private static function ensureTenantGamesTable(): void
    {
        if (Schema::hasTable('tenant_games')) {
            return;
        }

        Schema::create('tenant_games', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('game_alias', 80);
            $table->boolean('enabled')->default(true);
            $table->decimal('min_bet_override', 15, 2)->nullable();
            $table->decimal('max_bet_override', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'game_alias'], 'tenant_game_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    private static function ensureTenantTransactionsTable(): void
    {
        if (Schema::hasTable('tenant_transactions')) {
            return;
        }

        Schema::create('tenant_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('session_id')->constrained('tenant_sessions')->onDelete('cascade');
            $table->string('action', 20);
            $table->string('player_id', 100);
            $table->string('round_id', 60)->nullable();
            $table->string('game_id', 50)->default('teen_patti');
            $table->string('our_txn_id', 80)->unique();
            $table->string('tenant_txn_id', 120)->nullable();
            $table->string('ref_txn_id', 80)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 20)->default('ok');
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'round_id']);
            $table->index(['session_id', 'action']);
        });
    }
}
