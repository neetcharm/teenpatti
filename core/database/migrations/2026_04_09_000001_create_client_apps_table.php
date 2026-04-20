<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_key', 64)->unique()->comment('Public identifier sent with every request');
            $table->string('client_secret', 255)->comment('Bcrypt-hashed secret');
            $table->string('allowed_origins')->nullable()->comment('Comma-separated allowed origins (optional CORS check)');
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_apps');
    }
};
