<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantRuntimeSchema;
use Closure;
use Illuminate\Http\Request;

class TenantAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = session('tenant_panel_id');

        if (!$tenantId) {
            return redirect()->route('tenant.login');
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant || !$tenant->status) {
            session()->forget('tenant_panel_id');
            return redirect()->route('tenant.login')->withErrors(['session' => 'Session expired. Please log in again.']);
        }

        TenantRuntimeSchema::ensureBaseTables();

        // Share with all views in this request
        view()->share('authTenant', $tenant);

        return $next($request);
    }
}
