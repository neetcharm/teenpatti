<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                           // e.g. "Rummy Circle"
            $table->string('api_key', 64)->unique();          // Public key
            $table->string('api_secret', 255);                // Bcrypt-hashed secret
            $table->string('webhook_url');                    // Where we POST debit/credit events
            $table->string('callback_url')->nullable();       // Where we redirect after game ends
            $table->string('currency', 10)->default('INR');   // Tenant's currency code
            $table->decimal('commission_percent', 5, 2)->default(10.00); // Game commission
            $table->decimal('min_bet', 10, 2)->default(10.00);
            $table->decimal('max_bet', 10, 2)->default(50000.00);
            $table->integer('session_ttl_minutes')->default(120); // Session expiry
            $table->string('allowed_ips')->nullable();        // Comma-separated IP whitelist (optional)
            $table->tinyInteger('status')->default(1);        // 1=active, 0=inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
