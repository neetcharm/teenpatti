<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Optional separate database credentials (all nullable = use main DB)
            $table->boolean('use_separate_db')->default(false)->after('balance_mode');
            $table->string('db_host', 100)->nullable()->after('use_separate_db');
            $table->unsignedSmallInteger('db_port')->nullable()->after('db_host');
            $table->string('db_name', 100)->nullable()->after('db_port');
            $table->string('db_username', 100)->nullable()->after('db_name');
            $table->text('db_password_enc')->nullable()->after('db_username'); // Crypt::encrypt
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['use_separate_db','db_host','db_port','db_name','db_username','db_password_enc']);
        });
    }
};
