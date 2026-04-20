<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Encrypted (Crypt::encrypt) plain-text secret used for HMAC signing.
            // api_secret (bcrypt) remains for any password-like UI check.
            $table->text('webhook_secret')->nullable()->after('api_secret');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
