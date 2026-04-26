<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\SessionManager\SessionService;
use App\Services\TeenPattiGlobalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $request->merge([
            'silver_profit_x'  => $this->normalizeMultiplierInput($request->input('silver_profit_x')),
            'gold_profit_x'    => $this->normalizeMultiplierInput($request->input('gold_profit_x')),
            'diamond_profit_x' => $this->normalizeMultiplierInput($request->input('diamond_profit_x')),
        ]);

        $data = $request->validate([
            'commission_percent' => 'required|numeric|min:0|max:95',
            'min_bet'            => 'required|numeric|min:0',
            'max_bet'            => 'required|numeric|gt:min_bet',
            'session_ttl_minutes'=> 'required|integer|min:5|max:1440',
            'silver_profit_x'    => 'nullable|numeric|min:0|max:100',
            'gold_profit_x'      => 'nullable|numeric|min:0|max:100',
            'diamond_profit_x'   => 'nullable|numeric|min:0|max:100',
            'result_mode'        => 'required|string|in:random,manual',
            'manual_result_side' => 'nullable|required_if:result_mode,manual|string|in:silver,gold,diamond',
            'teen_patti_chips'   => 'nullable|string|max:120',
        ]);

        $chipValues = $this->parseChipValues($data['teen_patti_chips'] ?? '');

        $tenant->commission_percent = (float) $data['commission_percent'];
        $tenant->min_bet = (float) $data['min_bet'];
        $tenant->max_bet = (float) $data['max_bet'];
        $tenant->session_ttl_minutes = (int) $data['session_ttl_minutes'];
        $tenant->silver_profit_x = $this->nullableFloat($data['silver_profit_x'] ?? null);
        $tenant->gold_profit_x = $this->nullableFloat($data['gold_profit_x'] ?? null);
        $tenant->diamond_profit_x = $this->nullableFloat($data['diamond_profit_x'] ?? null);
        $tenant->result_mode = $data['result_mode'];
        $tenant->manual_result_side = $data['result_mode'] === 'manual'
            ? ($data['manual_result_side'] ?? null)
            : null;
        $tenant->teen_patti_chips = $chipValues;
        $tenant->save();

        return back()->with('success', 'Settings updated successfully.');
    }

    private function normalizeMultiplierInput($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return rtrim(strtolower($value), "x \t\n\r\0\x0B");
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function parseChipValues(?string $value): array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return Tenant::DEFAULT_TEEN_PATTI_CHIPS;
        }

        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $values = [];
        foreach ($parts as $part) {
            $amount = $this->parseAmountToken($part);
            if ($amount !== null && $amount > 0) {
                $values[] = $amount;
            }
        }

        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);

        if (count($values) < 1 || count($values) > 8) {
            throw ValidationException::withMessages([
                'teen_patti_chips' => 'Teen Patti chips must contain 1 to 8 valid amounts.',
            ]);
        }

        return $values;
    }

    private function parseAmountToken(string $token): ?int
    {
        $token = strtolower(trim($token));
        if ($token === '') {
            return null;
        }

        $multiplier = 1;
        if (str_ends_with($token, 'k')) {
            $multiplier = 1000;
            $token = substr($token, 0, -1);
        }

        if (!is_numeric($token)) {
            return null;
        }

        return (int) round(((float) $token) * $multiplier);
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

    public function manualOverrideInsights(Request $request): JsonResponse
    {
        $tenant = view()->shared('authTenant');
        $manager = new TeenPattiGlobalManager(false, (int) $tenant->id);

        return response()->json($manager->getManualOverrideInsights());
    }
}
