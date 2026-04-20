<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('game_alias', 80);
            $table->boolean('enabled')->default(true);
            $table->decimal('min_bet_override', 15, 2)->nullable();
            $table->decimal('max_bet_override', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'game_alias']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_games');
    }
};
