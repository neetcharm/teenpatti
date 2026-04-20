<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantSession;
use App\Models\TenantTransaction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlayerController extends Controller
{
    /**
     * List all unique players for this tenant with their token balances.
     */
    public function index()
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_sessions')) {
            $players = $this->emptyPaginator();
            return view('tenant.players.index', compact('players'));
        }

        // One row per player (by internal_user_id), with live balance from users table
        $players = DB::table('tenant_sessions as ts')
            ->join('users as u', 'u.id', '=', 'ts.internal_user_id')
            ->where('ts.tenant_id', $tenant->id)
            ->select(
                'ts.player_id',
                'ts.player_name',
                'ts.internal_user_id',
                'ts.currency',
                DB::raw('u.balance as token_balance'),
                DB::raw('MAX(ts.created_at) as last_seen'),
                DB::raw('COUNT(ts.id) as session_count')
            )
            ->groupBy('ts.internal_user_id', 'ts.player_id', 'ts.player_name', 'ts.currency', 'u.balance')
            ->orderByDesc(DB::raw('MAX(ts.created_at)'))
            ->paginate(30);

        return view('tenant.players.index', compact('players'));
    }

    /**
     * Add tokens to a player's account (internal mode only).
     */
    public function topup(Request $request, int $userId)
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_sessions')) {
            return back()->withErrors(['topup' => 'Session tables are not initialized yet. Please retry in a moment.']);
        }

        if ($tenant->balance_mode !== 'internal') {
            return back()->withErrors(['topup' => 'Token top-up is only available in Internal balance mode.']);
        }

        // Verify user belongs to this tenant
        $belongs = TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)
            ->exists();

        if (!$belongs) abort(403);

        $request->validate([
            'amount'  => 'required|numeric|min:1|max:1000000',
            'note'    => 'nullable|string|max:200',
        ]);

        $user = User::findOrFail($userId);
        $before = (float) $user->balance;
        $user->balance = round($before + $request->amount, 2);
        $user->save();

        // Sync active session cache
        TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)
            ->where('status', 'active')
            ->update(['balance_cache' => $user->balance]);

        // Log the top-up as a credit transaction
        $activeSession = TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)
            ->latest()->first();

        if ($activeSession && Schema::hasTable('tenant_transactions')) {
            TenantTransaction::create([
                'tenant_id'        => $tenant->id,
                'session_id'       => $activeSession->id,
                'action'           => 'credit',
                'player_id'        => $activeSession->player_id,
                'round_id'         => null,
                'game_id'          => $activeSession->game_id,
                'our_txn_id'       => 'topup_' . now()->format('Ymd') . '_' . uniqid(),
                'tenant_txn_id'    => 'panel_topup',
                'amount'           => $request->amount,
                'balance_before'   => $before,
                'balance_after'    => $user->balance,
                'request_payload'  => ['source' => 'tenant_panel', 'note' => $request->note],
                'response_payload' => ['status' => 'ok', 'balance' => $user->balance],
                'status'           => 'ok',
            ]);
        }

        return back()->with('topup_success', "Added {$request->amount} tokens to {$user->firstname}. New balance: {$user->balance}");
    }

    /**
     * Deduct tokens from a player's account (internal mode only).
     */
    public function deduct(Request $request, int $userId)
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_sessions')) {
            return back()->withErrors(['deduct' => 'Session tables are not initialized yet. Please retry in a moment.']);
        }

        if ($tenant->balance_mode !== 'internal') {
            return back()->withErrors(['deduct' => 'Token adjustment is only available in Internal balance mode.']);
        }

        $belongs = TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)->exists();

        if (!$belongs) abort(403);

        $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
        ]);

        $user   = User::findOrFail($userId);
        $before = (float) $user->balance;
        $newBal = max(0, round($before - $request->amount, 2));
        $user->balance = $newBal;
        $user->save();

        TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)
            ->where('status', 'active')
            ->update(['balance_cache' => $newBal]);

        return back()->with('topup_success', "Deducted {$request->amount} tokens. New balance: {$newBal}");
    }

    /**
     * Player game history.
     */
    public function history(int $userId)
    {
        $tenant = view()->shared('authTenant');

        if (!Schema::hasTable('tenant_sessions')) {
            abort(404);
        }

        $belongs = TenantSession::where('tenant_id', $tenant->id)
            ->where('internal_user_id', $userId)->exists();

        if (!$belongs) abort(403);

        $user         = User::findOrFail($userId);
        if (!Schema::hasTable('tenant_transactions')) {
            $transactions = $this->emptyPaginator();
            return view('tenant.players.history', compact('user', 'transactions'));
        }

        $transactions = TenantTransaction::where('tenant_id', $tenant->id)
            ->whereHas('session', fn($q) => $q->where('internal_user_id', $userId))
            ->latest()
            ->paginate(30);

        return view('tenant.players.history', compact('user', 'transactions'));
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
