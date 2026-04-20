<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantSession;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class SessionController extends Controller
{
    public function index()
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_sessions')) {
            $sessions = $this->emptyPaginator();
            return view('tenant.sessions.index', compact('sessions'));
        }

        $sessions = TenantSession::where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(30);

        return view('tenant.sessions.index', compact('sessions'));
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        $page = (int) request()->query('page', 1);
        return new LengthAwarePaginator([], 0, 30, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }
}
