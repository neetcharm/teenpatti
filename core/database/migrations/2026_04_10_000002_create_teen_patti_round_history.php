<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teen_patti_round_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('round_number')->unique(); // time-based round ID
            $table->string('winner', 20);                        // silver | gold | diamond
            $table->decimal('silver_total', 15, 2)->default(0);
            $table->decimal('gold_total',   15, 2)->default(0);
            $table->decimal('diamond_total',15, 2)->default(0);
            $table->decimal('total_pool',   15, 2)->default(0);
            $table->json('silver_cards')->nullable();
            $table->json('gold_cards')->nullable();
            $table->json('diamond_cards')->nullable();
            $table->string('silver_rank', 30)->nullable();
            $table->string('gold_rank',   30)->nullable();
            $table->string('diamond_rank',30)->nullable();
            $table->unsignedSmallInteger('player_count')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['is_demo', 'created_at']);
            $table->index('winner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teen_patti_round_history');
    }
};
