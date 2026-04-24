<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan tenant:db-setup {tenant_id}
 *
 * Creates the required tables in a tenant's dedicated MySQL database.
 * Run this ONCE after configuring a tenant's separate DB credentials.
 *
 * Tables created in the tenant DB:
 *   - tenant_transactions  (full transaction log)
 *
 * Tables that always stay in main DB:
 *   - tenants, tenant_sessions, tenant_games (routing + auth data)
 */
class SetupTenantDatabase extends Command
{
    protected $signature   = 'tenant:db-setup {tenant_id : The ID of the tenant}
                                              {--test   : Only test the connection, do not migrate}
                                              {--force  : Drop and recreate tables if they exist}';

    protected $description = 'Create required tables in a tenant\'s dedicated database';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');
        $tenant   = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant #{$tenantId} not found.");
            return 1;
        }

        $this->info("Tenant: <fg=cyan>{$tenant->name}</> (ID: {$tenant->id})");

        if (!$tenant->use_separate_db) {
            $this->warn('This tenant does not have a separate DB configured (use_separate_db = false).');
            $this->warn('Enable it in the admin panel first, then re-run this command.');
            return 1;
        }

        // Test connectivity
        $this->line('  Testing connection to <fg=yellow>' . $tenant->db_host . ':' . ($tenant->db_port ?? 3306) . '/' . $tenant->db_name . '</>...');

        $test = TenantConnectionManager::test($tenant);
        if (!$test['ok']) {
            $this->error('  Connection failed: ' . $test['error']);
            return 1;
        }
        $this->line('  <fg=green>✓ Connection successful</>');

        if ($this->option('test')) {
            $this->info('Test passed. Run without --test to migrate.');
            return 0;
        }

        // Connect and migrate
        $conn = TenantConnectionManager::for($tenant);

        try {
            $this->migrateTransactions($conn);
        } catch (\Throwable $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('<fg=green>✓ Tenant database setup complete!</>');
        $this->line('  All new transactions for this tenant will now be stored in:');
        $this->line("  <fg=cyan>{$tenant->db_host}:{$tenant->db_port}/{$tenant->db_name}.tenant_transactions</>");

        return 0;
    }

    private function migrateTransactions(string $conn): void
    {
        $schema = Schema::connection($conn);

        if ($schema->hasTable('tenant_transactions')) {
            if ($this->option('force')) {
                $this->line('  Dropping existing tenant_transactions table...');
                $schema->drop('tenant_transactions');
            } else {
                $this->line('  <fg=yellow>tenant_transactions already exists — skipping (use --force to recreate)</>');
                return;
            }
        }

        $this->line('  Creating <fg=white>tenant_transactions</>...');

        $schema->create('tenant_transactions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('session_id');  // references main DB tenant_sessions.id
            $table->string('action', 20);              // balance|debit|credit|rollback
            $table->string('player_id', 100);
            $table->string('round_id', 100)->nullable();
            $table->string('game_id', 80)->nullable();
            $table->string('our_txn_id', 100)->unique();
            $table->string('tenant_txn_id', 100)->nullable();
            $table->string('ref_txn_id', 100)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 20)->default('pending');  // pending|ok|failed
            $table->string('error_message', 255)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('session_id');
            $table->index('player_id');
            $table->index(['action', 'status']);
            $table->index('created_at');
        });

        $this->line('  <fg=green>✓ tenant_transactions created</>');
    }
}
