<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teen_patti_round_bets')) {
            return;
        }

        Schema::create('teen_patti_round_bets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('round_number');
            $table->boolean('is_demo')->default(false);
            $table->unsignedBigInteger('user_id');
            $table->string('side', 20);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['round_number', 'is_demo'], 'tp_bets_round_demo_idx');
            $table->index(['round_number', 'is_demo', 'side'], 'tp_bets_round_side_idx');
            $table->index(['round_number', 'is_demo', 'user_id'], 'tp_bets_round_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teen_patti_round_bets');
    }
};

