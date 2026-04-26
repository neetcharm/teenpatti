<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Game;
use App\Models\Tenant;
use App\Models\TenantGame;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionDemoAccess extends Command
{
    protected $signature = 'provision:demo-access
        {--tenant-name=Client Demo Tenant}
        {--tenant-email=demo.tenant@ezycry.com}
        {--tenant-password=}
        {--admin-name=Demo Admin}
        {--admin-email=demo.admin@ezycry.com}
        {--admin-username=demoadmin}
        {--admin-password=}
        {--currency=INR}';

    protected $description = 'Create or update a demonstration tenant and admin account with active access.';

    public function handle(): int
    {
        $tenantName = (string) $this->option('tenant-name');
        $tenantEmail = strtolower((string) $this->option('tenant-email'));
        $tenantPassword = (string) ($this->option('tenant-password') ?: Str::random(14));

        $adminName = (string) $this->option('admin-name');
        $adminEmail = strtolower((string) $this->option('admin-email'));
        $adminUsername = strtolower((string) $this->option('admin-username'));
        $adminPassword = (string) ($this->option('admin-password') ?: Str::random(14));

        try {
            $tenant = Tenant::where('email', $tenantEmail)->first();
            $plainSecret = Str::random(40);

            if (!$tenant) {
                $tenant = new Tenant();
                $tenant->api_key = 'tp_' . Str::random(24);
            }

            $tenant->name = $tenantName;
            $tenant->email = $tenantEmail;
            $tenant->password = Hash::make($tenantPassword);
            $tenant->api_secret = Hash::make($plainSecret);
            $tenant->webhook_secret = Crypt::encrypt($plainSecret);
            $tenant->webhook_url = $tenant->webhook_url ?: 'https://client-demo.invalid/webhook';
            $tenant->callback_url = $tenant->callback_url ?: 'https://client-demo.invalid/callback';
            $tenant->currency = strtoupper((string) $this->option('currency'));
            $tenant->commission_percent = $tenant->commission_percent ?: 5;
            $tenant->min_bet = $tenant->min_bet ?: 10;
            $tenant->max_bet = $tenant->max_bet ?: 50000;
            $tenant->session_ttl_minutes = $tenant->session_ttl_minutes ?: 120;
            $tenant->status = 1;
            $tenant->balance_mode = 'internal';
            $tenant->use_separate_db = false;
            $tenant->db_host = null;
            $tenant->db_port = null;
            $tenant->db_name = null;
            $tenant->db_username = null;
            $tenant->db_password_enc = null;
            $tenant->save();

            $activeAliases = Game::active()->whereIn('alias', liveGameAliases())->pluck('alias')->toArray();
            foreach ($activeAliases as $alias) {
                TenantGame::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'game_alias' => $alias],
                    ['enabled' => true]
                );
            }

            $admin = Admin::where('username', $adminUsername)->first();
            if (!$admin) {
                $admin = new Admin();
            }

            $admin->name = $adminName;
            $admin->email = $adminEmail;
            $admin->username = $adminUsername;
            $admin->password = Hash::make($adminPassword);
            $admin->email_verified_at = $admin->email_verified_at ?: Carbon::now();
            $admin->save();

            $this->info('DEMO_ACCESS_READY');
            $this->line('TENANT_ID=' . $tenant->id);
            $this->line('TENANT_LOGIN_URL=/tenant/login');
            $this->line('TENANT_EMAIL=' . $tenantEmail);
            $this->line('TENANT_PASSWORD=' . $tenantPassword);
            $this->line('TENANT_API_KEY=' . $tenant->api_key);
            $this->line('ADMIN_ID=' . $admin->id);
            $this->line('ADMIN_LOGIN_URL=/admin');
            $this->line('ADMIN_USERNAME=' . $adminUsername);
            $this->line('ADMIN_PASSWORD=' . $adminPassword);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('DEMO_ACCESS_FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
