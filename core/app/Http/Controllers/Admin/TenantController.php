<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Tenant;
use App\Models\TenantGame;
use App\Models\TenantSession;
use App\Models\TenantTransaction;
use App\Services\TenantConnectionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    //
    // List all tenants
    //
    public function index()
    {
        $pageTitle = 'Game Provider Tenants';
        $tenants   = Tenant::withCount(['sessions', 'transactions'])->latest()->paginate(20);
        return view('admin.tenants.index', compact('pageTitle', 'tenants'));
    }

    //
    // Create form
    //
    public function create()
    {
        $pageTitle = 'Add New Tenant';
        return view('admin.tenants.create', compact('pageTitle'));
    }

    //
    // Store new tenant
    //
    public function store(Request $request)
    {
        $balanceMode = $request->input('balance_mode', 'internal');

        $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email|unique:tenants,email',
            'balance_mode'        => 'required|in:internal,webhook',
            'webhook_url'         => $balanceMode === 'webhook' ? 'required|url' : 'nullable|url',
            'callback_url'        => 'nullable|url',
            'wallet_topup_url'    => 'nullable|url',
            'currency'            => 'required|string|max:10',
            'commission_percent'  => 'required|numeric|min:0|max:100',
            'min_bet'             => 'required|numeric|min:1',
            'max_bet'             => 'required|numeric|gt:min_bet',
            'session_ttl_minutes' => 'required|integer|min:10|max:1440',
        ]);

        // Auto-generate API credentials
        $apiKey        = 'tp_' . Str::random(24);
        $plainSecret   = Str::random(40);
        $webhookSecret = Crypt::encrypt($plainSecret);

        // Auto-generate panel login password
        $panelPassword = Str::random(16);

        $useSeparateDb = (bool) $request->input('use_separate_db', false);

        Tenant::create([
            'name'                => $request->name,
            'email'               => strtolower($request->email),
            'password'            => Hash::make($panelPassword),
            'balance_mode'        => $balanceMode,
            'api_key'             => $apiKey,
            'api_secret'          => Hash::make($plainSecret),
            'webhook_secret'      => $webhookSecret,
            'webhook_url'         => $request->webhook_url,
            'callback_url'        => $request->callback_url,
            'wallet_topup_url'    => $request->wallet_topup_url,
            'currency'            => strtoupper($request->currency),
            'commission_percent'  => $request->commission_percent,
            'min_bet'             => $request->min_bet,
            'max_bet'             => $request->max_bet,
            'session_ttl_minutes' => $request->session_ttl_minutes,
            'allowed_ips'         => $request->allowed_ips,
            'status'              => 1,
            // Separate DB
            'use_separate_db'     => $useSeparateDb,
            'db_host'             => $useSeparateDb ? $request->db_host    : null,
            'db_port'             => $useSeparateDb ? $request->db_port    : null,
            'db_name'             => $useSeparateDb ? $request->db_name    : null,
            'db_username'         => $useSeparateDb ? $request->db_username : null,
            'db_password_enc'     => ($useSeparateDb && $request->filled('db_password'))
                                        ? Crypt::encrypt($request->db_password) : null,
        ]);

        // Flash all credentials once — never shown again
        session()->flash('new_api_key', $apiKey);
        session()->flash('new_webhook_secret', $plainSecret);
        session()->flash('new_panel_email', strtolower($request->email));
        session()->flash('new_panel_password', $panelPassword);

        $notify[] = ['success', 'Tenant created. Copy ALL credentials shown below — they will not be shown again.'];
        return redirect()->route('admin.tenants.index')->withNotify($notify);
    }

    //
    // Edit form
    //
    public function edit(int $id)
    {
        $pageTitle = 'Edit Tenant';
        $tenant    = Tenant::findOrFail($id);
        return view('admin.tenants.edit', compact('pageTitle', 'tenant'));
    }

    //
    // Update tenant
    //
    public function update(Request $request, int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $balanceMode = $request->input('balance_mode', $tenant->balance_mode);

        $request->validate([
            'name'                => 'required|string|max:100',
            'email'               => 'required|email|unique:tenants,email,' . $tenant->id,
            'balance_mode'        => 'required|in:internal,webhook',
            'webhook_url'         => $balanceMode === 'webhook' ? 'required|url' : 'nullable|url',
            'callback_url'        => 'nullable|url',
            'wallet_topup_url'    => 'nullable|url',
            'currency'            => 'required|string|max:10',
            'commission_percent'  => 'required|numeric|min:0|max:100',
            'min_bet'             => 'required|numeric|min:1',
            'max_bet'             => 'required|numeric|gt:min_bet',
            'session_ttl_minutes' => 'required|integer|min:10|max:1440',
        ]);

        $useSeparateDb = (bool) $request->input('use_separate_db', false);

        $data = [
            'name'                => $request->name,
            'email'               => strtolower($request->email),
            'balance_mode'        => $balanceMode,
            'webhook_url'         => $request->webhook_url,
            'callback_url'        => $request->callback_url,
            'wallet_topup_url'    => $request->wallet_topup_url,
            'currency'            => strtoupper($request->currency),
            'commission_percent'  => $request->commission_percent,
            'min_bet'             => $request->min_bet,
            'max_bet'             => $request->max_bet,
            'session_ttl_minutes' => $request->session_ttl_minutes,
            'allowed_ips'         => $request->allowed_ips,
            // Separate DB
            'use_separate_db'     => $useSeparateDb,
            'db_host'             => $useSeparateDb ? $request->db_host     : null,
            'db_port'             => $useSeparateDb ? $request->db_port     : null,
            'db_name'             => $useSeparateDb ? $request->db_name     : null,
            'db_username'         => $useSeparateDb ? $request->db_username : null,
        ];

        if ($request->filled('new_panel_password')) {
            $data['password'] = Hash::make($request->new_panel_password);
        }
        if ($useSeparateDb && $request->filled('db_password')) {
            $data['db_password_enc'] = Crypt::encrypt($request->db_password);
        }
        if (!$useSeparateDb) {
            TenantConnectionManager::purge($tenant); // clear cached connection
        }

        $tenant->update($data);

        $notify[] = ['success', 'Tenant updated successfully.'];
        return back()->withNotify($notify);
    }

    //
    // Toggle status
    //
    public function toggleStatus(int $id)
    {
        $tenant         = Tenant::findOrFail($id);
        $tenant->status = $tenant->status ? 0 : 1;
        $tenant->save();

        $msg    = $tenant->status ? 'Tenant activated.' : 'Tenant deactivated.';
        $notify[] = ['success', $msg];
        return back()->withNotify($notify);
    }

    //
    // Regenerate API key + webhook secret
    //
    public function regenerateKeys(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $newApiKey      = 'tp_' . Str::random(24);
        $newPlainSecret = Str::random(40);

        $tenant->update([
            'api_key'        => $newApiKey,
            'api_secret'     => Hash::make($newPlainSecret),
            'webhook_secret' => Crypt::encrypt($newPlainSecret),
        ]);

        // Expire all active sessions for this tenant (old keys are invalid)
        TenantSession::where('tenant_id', $id)->where('status', 'active')
            ->update(['status' => 'expired']);

        session()->flash('new_api_key', $newApiKey);
        session()->flash('new_webhook_secret', $newPlainSecret);

        $notify[] = ['success', 'Keys regenerated. Copy the new credentials below.'];
        return redirect()->route('admin.tenants.index')->withNotify($notify);
    }

    //
    // Active sessions for a tenant
    //
    public function sessions(int $id)
    {
        $tenant    = Tenant::findOrFail($id);
        $pageTitle = 'Sessions — ' . $tenant->name;
        $sessions  = TenantSession::where('tenant_id', $id)
            ->latest()
            ->paginate(30);
        return view('admin.tenants.sessions', compact('pageTitle', 'tenant', 'sessions'));
    }

    //
    // Webhook transaction log for a tenant
    //
    public function transactions(int $id)
    {
        $tenant       = Tenant::findOrFail($id);
        $pageTitle    = 'Webhook Transactions — ' . $tenant->name;
        $transactions = TenantTransaction::where('tenant_id', $id)
            ->latest()
            ->paginate(30);
        return view('admin.tenants.transactions', compact('pageTitle', 'tenant', 'transactions'));
    }

    //
    // Test separate DB connection (AJAX)
    //
    public function testDb(int $id)
    {
        $tenant = Tenant::findOrFail($id);
        $result = TenantConnectionManager::test($tenant);
        return response()->json($result);
    }

    //
    // Game assignment — show
    //
    public function games(int $id)
    {
        $tenant    = Tenant::with('games')->findOrFail($id);
        $pageTitle = 'Game Access — ' . $tenant->name;

        // All active games from the platform
        $allGames  = Game::active()->whereIn('alias', liveGameAliases())->orderBy('name')->get();

        // Build a quick lookup: alias => enabled bool
        $assigned  = $tenant->games->keyBy('game_alias');

        return view('admin.tenants.games', compact('pageTitle', 'tenant', 'allGames', 'assigned'));
    }

    //
    // Game assignment — save
    //
    public function updateGames(Request $request, int $id)
    {
        $tenant   = Tenant::findOrFail($id);
        $allGames = Game::active()->whereIn('alias', liveGameAliases())->pluck('alias')->toArray();
        $enabled  = (array) $request->input('enabled', []); // array of aliases that are ON

        foreach ($allGames as $alias) {
            TenantGame::updateOrCreate(
                ['tenant_id' => $tenant->id, 'game_alias' => $alias],
                ['enabled'   => in_array($alias, $enabled)]
            );
        }

        $notify[] = ['success', 'Game access updated for ' . $tenant->name . '.'];
        return back()->withNotify($notify);
    }
}
