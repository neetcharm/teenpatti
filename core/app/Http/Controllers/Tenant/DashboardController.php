<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantSession;
use App\Models\TenantTransaction;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $tenant = view()->shared('authTenant');

        $totalPlayers = 0;
        $activeSessions = 0;
        $totalDebited = 0;
        $totalCredited = 0;
        $recentSessions = collect();
        $recentTransactions = collect();

        if (Schema::hasTable('tenant_sessions')) {
            $totalPlayers = TenantSession::where('tenant_id', $tenant->id)
                ->distinct('internal_user_id')
                ->count('internal_user_id');

            $activeSessions = TenantSession::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->count();

            $recentSessions = TenantSession::where('tenant_id', $tenant->id)
                ->latest()->limit(10)->get();
        }

        if (Schema::hasTable('tenant_transactions')) {
            $totalDebited = TenantTransaction::where('tenant_id', $tenant->id)
                ->where('action', 'debit')->where('status', 'ok')->sum('amount');

            $totalCredited = TenantTransaction::where('tenant_id', $tenant->id)
                ->where('action', 'credit')->where('status', 'ok')->sum('amount');

            $recentTransactions = TenantTransaction::where('tenant_id', $tenant->id)
                ->latest()->limit(10)->get();
        }

        return view('tenant.dashboard', compact(
            'totalPlayers', 'activeSessions',
            'totalDebited', 'totalCredited',
            'recentSessions', 'recentTransactions'
        ));
    }
}
