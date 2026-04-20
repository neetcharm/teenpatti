<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Modules\SessionManager\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = view()->shared('authTenant');
        $apiSigningSecret = $tenant ? $tenant->getApiSigningSecret() : '';

        return view('tenant.settings', compact('apiSigningSecret'));
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = view()->shared('authTenant');

        $data = $request->validate([
            'commission_percent' => 'required|numeric|min:0|max:95',
            'min_bet'            => 'required|numeric|min:0',
            'max_bet'            => 'required|numeric|gt:min_bet',
            'session_ttl_minutes'=> 'required|integer|min:5|max:1440',
        ]);

        $tenant->commission_percent = (float) $data['commission_percent'];
        $tenant->min_bet = (float) $data['min_bet'];
        $tenant->max_bet = (float) $data['max_bet'];
        $tenant->session_ttl_minutes = (int) $data['session_ttl_minutes'];
        $tenant->save();

        return back()->with('success', 'Settings updated successfully.');
    }

    public function launchTeenPatti(Request $request, SessionService $sessionService): RedirectResponse
    {
        $tenant = view()->shared('authTenant');

        if (!$tenant->hasGame('teen_patti')) {
            return back()->withErrors(['launch' => 'Teen Patti is not enabled for your tenant account.']);
        }

        $playerId   = 'tenant_' . $tenant->id . '_demo_' . time();
        $playerName = trim((string) $tenant->name) . ' Demo Player';
        $currency   = strtoupper((string) ($tenant->currency ?: 'INR'));

        $session = $sessionService->createSession(
            $tenant,
            $playerId,
            $playerName,
            'teen_patti',
            $currency,
            (string) $request->ip()
        );

        return redirect()->to(url('/play?token=' . $session->session_token));
    }
}
