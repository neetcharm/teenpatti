<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class TransactionController extends Controller
{
    public function index()
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_transactions')) {
            $transactions = $this->emptyPaginator();
            return view('tenant.transactions.index', compact('transactions'));
        }

        $transactions = TenantTransaction::where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(30);

        return view('tenant.transactions.index', compact('transactions'));
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
