<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('session_id')->constrained('tenant_sessions')->onDelete('cascade');
            $table->string('action', 20);                      // balance | debit | credit | rollback
            $table->string('player_id', 100);                  // Tenant's user ID
            $table->string('round_id', 60)->nullable();        // e.g. tp_r123456
            $table->string('game_id', 50)->default('teen_patti');
            $table->string('our_txn_id', 80)->unique();        // Our generated transaction ID
            $table->string('tenant_txn_id', 120)->nullable();  // ID returned by tenant webhook
            $table->string('ref_txn_id', 80)->nullable();      // For rollback: original debit txn ID
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->json('request_payload')->nullable();        // What we sent to tenant
            $table->json('response_payload')->nullable();       // What tenant replied
            $table->string('status', 20)->default('ok');       // ok | failed | pending
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'round_id']);
            $table->index(['session_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_transactions');
    }
};
