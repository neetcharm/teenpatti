<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * TenantConnectionManager
 *
 * Dynamically registers a Laravel DB connection for a tenant that has
 * configured its own dedicated MySQL database.  Falls back to the default
 * connection when no separate DB is configured.
 *
 * Usage:
 *   $conn = TenantConnectionManager::for($tenant);
 *   TenantSession::on($conn)->where(...)
 */
class TenantConnectionManager
{
    /** Cache of already-registered connection names → avoid re-registering */
    private static array $registered = [];

    /**
     * Return the Laravel connection name to use for this tenant's data tables.
     *
     * - If the tenant has use_separate_db = true and valid credentials → registers
     *   a dynamic connection named "tenant_{id}" and returns it.
     * - Otherwise returns the application's default DB connection name.
     */
    public static function for(Tenant $tenant): string
    {
        if (!$tenant->use_separate_db) {
            return config('database.default');
        }

        $key = 'tenant_' . $tenant->id;

        if (!isset(self::$registered[$key])) {
            self::register($tenant, $key);
            self::$registered[$key] = true;
        }

        return $key;
    }

    /**
     * Test connectivity to the tenant's separate DB.
     * Returns ['ok' => true] or ['ok' => false, 'error' => '...']
     */
    public static function test(Tenant $tenant): array
    {
        if (!$tenant->use_separate_db) {
            return ['ok' => false, 'error' => 'Separate DB not configured.'];
        }

        $key = 'tenant_test_' . $tenant->id . '_' . time();
        self::register($tenant, $key);

        try {
            DB::connection($key)->getPdo();
            DB::purge($key);
            return ['ok' => true];
        } catch (\Throwable $e) {
            DB::purge($key);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Purge a tenant's cached connection (call after credential update).
     */
    public static function purge(Tenant $tenant): void
    {
        $key = 'tenant_' . $tenant->id;
        if (isset(self::$registered[$key])) {
            DB::purge($key);
            unset(self::$registered[$key]);
        }
    }

    //
    private static function register(Tenant $tenant, string $key): void
    {
        $password = '';
        if ($tenant->db_password_enc) {
            try {
                $password = Crypt::decrypt($tenant->db_password_enc);
            } catch (\Throwable) {
                $password = $tenant->db_password_enc; // plain fallback
            }
        }

        config([
            'database.connections.' . $key => [
                'driver'         => 'mysql',
                'host'           => $tenant->db_host     ?? '127.0.0.1',
                'port'           => $tenant->db_port     ?? 3306,
                'database'       => $tenant->db_name,
                'username'       => $tenant->db_username ?? '',
                'password'       => $password,
                'charset'        => 'utf8mb4',
                'collation'      => 'utf8mb4_unicode_ci',
                'prefix'         => '',
                'strict'         => false,
                'engine'         => null,
                'options'        => [
                    \PDO::ATTR_TIMEOUT => 5,
                ],
            ],
        ]);
    }
}
