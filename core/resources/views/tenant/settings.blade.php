@extends('tenant.layouts.app')
@section('title', 'Settings & API')
@section('page-title', 'Settings & API')

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">

    {{-- API Credentials --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="las la-key text-warning"></i> API Credentials
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">API Key</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="apiKey"
                               value="{{ $authTenant->api_key }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyField('apiKey')">
                            <i class="las la-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">Send this in every API request header as <code>X-API-Key</code></small>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">API Secret</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="apiSecret"
                               value="{{ $apiSigningSecret }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyField('apiSecret')">
                            <i class="las la-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        Use this only on your backend to generate <code>X-Signature</code>. Never place it in app/web client code.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Webhook URL (your endpoint)</label>
                    @if($authTenant->webhook_url)
                        <input type="text" class="form-control font-monospace"
                               value="{{ $authTenant->webhook_url }}" readonly>
                    @else
                        <div class="text-muted small">Not configured — contact admin.</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Wallet Top-up URL</label>
                    @if($authTenant->wallet_topup_url)
                        <input type="text" class="form-control font-monospace"
                               value="{{ $authTenant->wallet_topup_url }}" readonly>
                        <small class="text-muted">Used by the in-game Top Up button to open your main wallet flow.</small>
                    @else
                        <div class="text-muted small">Not configured — contact admin.</div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Balance Mode</label>
                    <div>
                        @if($authTenant->balance_mode === 'internal')
                            <span class="badge bg-success fs-6">Internal Tokens</span>
                            <p class="text-muted small mt-1">
                                Player balances are stored in our system. You can top up players from the Players page.
                                No external webhook calls are made for game events.
                            </p>
                        @else
                            <span class="badge bg-info fs-6">Webhook Mode</span>
                            <p class="text-muted small mt-1">
                                Your server manages player balances. We POST events (debit/credit/rollback) to your webhook URL.
                            </p>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="form-label text-muted small fw-bold text-uppercase">Game Launch URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace small" id="launchUrl"
                               value="{{ url('/play?token={session_token}') }}" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyField('launchUrl')">
                            <i class="las la-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">Replace <code>{session_token}</code> with the token from <code>POST /api/v1/session/create</code></small>
                </div>
            </div>
        </div>
    </div>

    {{-- Game Config --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="las la-sliders-h text-primary"></i> Game Configuration
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('tenant.settings.update') }}">
                    @csrf

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Commission %</label>
                            <input type="number" step="0.01" min="0" max="95" class="form-control"
                                   name="commission_percent"
                                   value="{{ old('commission_percent', number_format((float) $authTenant->commission_percent, 2, '.', '')) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Currency</label>
                            <input type="text" class="form-control" value="{{ strtoupper($authTenant->currency) }}" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Min Bet</label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                   name="min_bet"
                                   value="{{ old('min_bet', number_format((float) $authTenant->min_bet, 2, '.', '')) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Max Bet</label>
                            <input type="number" step="0.01" min="0.01" class="form-control"
                                   name="max_bet"
                                   value="{{ old('max_bet', number_format((float) $authTenant->max_bet, 2, '.', '')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Teen Patti Chip Amounts</label>
                            <input type="text" class="form-control font-monospace"
                                   name="teen_patti_chips"
                                   placeholder="400, 2K, 4K, 20K, 40K"
                                   value="{{ old('teen_patti_chips', implode(', ', array_map(fn($amount) => $amount >= 1000 && $amount % 1000 === 0 ? ((int) ($amount / 1000)) . 'K' : (string) $amount, $authTenant->teenPattiChipValues()))) }}">
                            <small class="text-muted">
                                Configure 1 to 8 chips. Example: <code>400, 2K, 5K, 10K</code>. These amounts appear in the game footer and are enforced by the bet API.
                            </small>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold text-uppercase">Session TTL (minutes)</label>
                            <input type="number" min="5" max="1440" class="form-control"
                                   name="session_ttl_minutes"
                                   value="{{ old('session_ttl_minutes', (int) $authTenant->session_ttl_minutes) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Result Mode</label>
                            <select class="form-select" name="result_mode" id="resultModeSelect">
                                @php($resultMode = old('result_mode', $authTenant->result_mode ?: 'random'))
                                <option value="random" {{ $resultMode === 'random' ? 'selected' : '' }}>Fair Random</option>
                                <option value="manual" {{ $resultMode === 'manual' ? 'selected' : '' }}>Manual Override</option>
                            </select>
                            <small class="text-muted">Fair Random chooses winners randomly. Manual Override lets you force the winner side.</small>
                        </div>
                        <div class="col-6" id="manualSideFieldWrap">
                            <label class="form-label text-muted small fw-bold text-uppercase">Manual Winner Side</label>
                            <select class="form-select" name="manual_result_side" id="manualResultSideSelect">
                                @php($manualResultSide = old('manual_result_side', $authTenant->manual_result_side))
                                <option value="">Select side</option>
                                <option value="silver" {{ $manualResultSide === 'silver' ? 'selected' : '' }}>Silver</option>
                                <option value="gold" {{ $manualResultSide === 'gold' ? 'selected' : '' }}>Gold</option>
                                <option value="diamond" {{ $manualResultSide === 'diamond' ? 'selected' : '' }}>Diamond</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-3 small">
                        <strong>Payout Rule:</strong> Set a fixed winner payout multiplier for each side. <br>
                        Example: with <code>2.80x</code> and <code>10%</code> commission, a 2000 bet returns <code>5040</code>. Leave a side blank to use the dynamic rule:
                        <code>(Total Pool / Winner Side Pool) × (1 - Commission% / 100)</code>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Silver Payout X</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   name="silver_profit_x"
                                   placeholder="Blank = Dynamic, e.g. 2.8x"
                                   value="{{ old('silver_profit_x', $authTenant->silver_profit_x !== null ? number_format((float) $authTenant->silver_profit_x, 2, '.', '') : '') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Gold Payout X</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   name="gold_profit_x"
                                   placeholder="Blank = Dynamic, e.g. 2.9x"
                                   value="{{ old('gold_profit_x', $authTenant->gold_profit_x !== null ? number_format((float) $authTenant->gold_profit_x, 2, '.', '') : '') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Diamond Payout X</label>
                            <input type="text" inputmode="decimal" class="form-control"
                                   name="diamond_profit_x"
                                   placeholder="Blank = Dynamic, e.g. 3x"
                                   value="{{ old('diamond_profit_x', $authTenant->diamond_profit_x !== null ? number_format((float) $authTenant->diamond_profit_x, 2, '.', '') : '') }}">
                        </div>
                    </div>

                    <div class="border rounded p-2 mb-3">
                        <div class="small fw-semibold mb-2">Multiplier Preview</div>
                        <div class="row g-2">
                            <div class="col-3">
                                <label class="form-label text-muted small mb-1">Winner Side</label>
                                <select id="previewWinnerSide" class="form-select form-select-sm">
                                    <option value="silver">Silver</option>
                                    <option value="gold">Gold</option>
                                    <option value="diamond">Diamond</option>
                                </select>
                            </div>
                            <div class="col-3">
                                <label class="form-label text-muted small mb-1">Total Pool</label>
                                <input type="number" step="0.01" min="0" id="previewPool" class="form-control form-control-sm" value="10000">
                            </div>
                            <div class="col-3">
                                <label class="form-label text-muted small mb-1">Winner Pool</label>
                                <input type="number" step="0.01" min="0.01" id="previewWinnerPool" class="form-control form-control-sm" value="2500">
                            </div>
                            <div class="col-3">
                                <label class="form-label text-muted small mb-1">Player Bet</label>
                                <input type="number" step="0.01" min="0.01" id="previewBet" class="form-control form-control-sm" value="100">
                            </div>
                        </div>
                        <div class="small mt-2 text-info" id="previewMode">
                            Mode: Dynamic Pool Formula
                        </div>
                        <div class="small mt-2">
                            Gross Multiplier: <strong id="previewGross">0.00x</strong> |
                            Net Multiplier: <strong id="previewNet">0.00x</strong> |
                            Estimated Payout: <strong id="previewPayout">0.00</strong>
                        </div>
                    </div>

                    <div class="border rounded p-2 mb-3 d-none" id="manualOverrideInsightsCard">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="small fw-semibold">Manual Override Decision Helper</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="manualOverrideRefreshBtn">
                                <i class="las la-sync"></i> Refresh
                            </button>
                        </div>
                        <div class="small text-muted mb-2" id="manualOverrideMeta">Loading current round data...</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Side</th>
                                        <th class="text-end">Total Bet</th>
                                        <th class="text-end">Projected Payout</th>
                                        <th class="text-end">Company Net</th>
                                    </tr>
                                </thead>
                                <tbody id="manualOverrideInsightsBody">
                                    <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-{{ $authTenant->status ? 'success' : 'danger' }}">
                                {{ $authTenant->status ? 'Tenant Active' : 'Tenant Disabled' }}
                            </span>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="las la-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Quick Integration --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3">
                <i class="las la-code text-success"></i> Quick Integration Guide
            </div>
            <div class="card-body">
                <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="small">
                        <strong>Tenant Test Launch:</strong> Click this to generate a real tenant session and open Teen Patti.
                    </div>
                    <form method="POST" action="{{ route('tenant.launch.teen_patti') }}" target="_blank" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="las la-play-circle"></i> Launch Teen Patti Test
                        </button>
                    </form>
                </div>

                <p class="text-muted small mb-3">
                    Call <strong>POST /api/v1/session/create</strong> from your server to generate a game URL for a player.
                </p>

                @if($authTenant->balance_mode === 'webhook')
                    <div class="alert alert-warning small">
                        <strong>Wallet Sync Contract:</strong> session creation and game focus trigger your webhook with
                        <code>action=balance</code>. The in-game <code>Top Up</code> button appends
                        <code>player_id</code>, <code>player_name</code>, <code>session_token</code>,
                        <code>game_id</code>, <code>currency</code>, <code>tenant_id</code>, and <code>return_url</code>
                        query parameters to your configured wallet URL so the correct player wallet can be opened and returned to the game.
                    </div>
                @endif

                <div class="mb-3">
                    <p class="fw-semibold small mb-1">1. Create a game session (server-side)</p>
                    <pre class="bg-dark text-light p-3 rounded small" style="overflow-x:auto">POST {{ url('/api/v1/session/create') }}
Content-Type: application/json
X-API-Key: {{ $authTenant->api_key }}
X-Signature: &lt;hmac_sha256_of_raw_json_with_api_secret&gt;

{
  "player_id": "user_123",
  "player_name": "Rahul",
  "game_id": "teen_patti",
  "currency": "{{ strtoupper($authTenant->currency) }}"
}

// Response:
{
  "success": true,
  "data": {
    "session_token": "abc...xyz",
    "game_url": "{{ url('/play?token=abc...xyz') }}",
    "player_balance": 2450,
    "currency": "{{ strtoupper($authTenant->currency) }}",
    "expires_at": "2026-04-09T14:30:00Z"
  }
}</pre>
                </div>

                @if($authTenant->balance_mode === 'webhook')
                    <div class="mb-3">
                        <p class="fw-semibold small mb-1">Webhook balance response shape</p>
                        <pre class="bg-dark text-light p-3 rounded small" style="overflow-x:auto">{
  "status": "ok",
  "balance": 2450.00,
  "transaction_id": "tenant_txn_12345"
}</pre>
                    </div>
                @endif

                <div class="mb-3">
                    <p class="fw-semibold small mb-1">2. Open in Android WebView</p>
                    <pre class="bg-dark text-light p-3 rounded small" style="overflow-x:auto">// Kotlin
webView.loadUrl(gameUrl)

// Add bridge for game close callback
webView.addJavascriptInterface(object {
    @JavascriptInterface
    fun onGameClose(playerId: String) { finish() }
}, "AndroidBridge")</pre>
                </div>

                <div>
                    <p class="fw-semibold small mb-1">3. HMAC Signature (server-side)</p>
                    <pre class="bg-dark text-light p-3 rounded small" style="overflow-x:auto">// PHP: sign the RAW JSON request body with API Secret
$rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $rawJson, $api_secret);

// Python
import hmac, hashlib
sig = hmac.new(
    api_secret.encode(),
    raw_json.encode(),
    hashlib.sha256
).hexdigest()</pre>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
const manualOverrideInsightsUrl = @json(route('tenant.settings.manual_override_insights'));
let manualOverrideInsightsInterval = null;

function copyField(id) {
    var el = document.getElementById(id);
    el.select(); el.setSelectionRange(0, 9999);
    navigator.clipboard.writeText(el.value).then(() => {
        var btn = el.nextElementSibling;
        btn.innerHTML = '<i class="las la-check"></i>';
        setTimeout(() => btn.innerHTML = '<i class="las la-copy"></i>', 1500);
    });
}

function updatePayoutPreview() {
    const commissionInput = document.querySelector('input[name="commission_percent"]');
    const winnerSideInput = document.getElementById('previewWinnerSide');
    const poolInput = document.getElementById('previewPool');
    const winnerPoolInput = document.getElementById('previewWinnerPool');
    const betInput = document.getElementById('previewBet');
    const modeOutput = document.getElementById('previewMode');
    if (!commissionInput || !winnerSideInput || !poolInput || !winnerPoolInput || !betInput || !modeOutput) {
        return;
    }

    const winnerSide = winnerSideInput.value || 'silver';
    const commission = Math.max(0, Math.min(95, parseFloat(commissionInput.value || '0') || 0));
    const pool = Math.max(0, parseFloat(poolInput.value || '0') || 0);
    const winnerPool = Math.max(0.01, parseFloat(winnerPoolInput.value || '0.01') || 0.01);
    const bet = Math.max(0, parseFloat(betInput.value || '0') || 0);

    const sideProfitInput = document.querySelector('input[name="' + winnerSide + '_profit_x"]');
    const sideProfitRaw = sideProfitInput ? sideProfitInput.value.trim() : '';
    const hasFixedProfit = sideProfitRaw !== '';

    let gross = 0;
    let net = 0;
    let payout = 0;

    if (hasFixedProfit) {
        const payoutX = Math.max(0, parseFloat(sideProfitRaw || '0') || 0);
        gross = payoutX;
        net = gross * ((100 - commission) / 100);
        payout = bet * net;
        modeOutput.innerText = 'Mode: Fixed ' + winnerSide.charAt(0).toUpperCase() + winnerSide.slice(1) + ' Payout (' + payoutX.toFixed(2) + 'x)';
    } else {
        gross = pool / winnerPool;
        net = gross * ((100 - commission) / 100);
        payout = bet * net;
        modeOutput.innerText = 'Mode: Dynamic Pool Formula';
    }

    document.getElementById('previewGross').innerText = gross.toFixed(2) + 'x';
    document.getElementById('previewNet').innerText = net.toFixed(2) + 'x';
    document.getElementById('previewPayout').innerText = payout.toFixed(2);
}

function toggleManualOverrideFields() {
    const modeSelect = document.getElementById('resultModeSelect');
    const sideWrap = document.getElementById('manualSideFieldWrap');
    const sideSelect = document.getElementById('manualResultSideSelect');
    const insightsCard = document.getElementById('manualOverrideInsightsCard');
    if (!modeSelect || !sideWrap || !sideSelect || !insightsCard) {
        return;
    }

    const manualMode = modeSelect.value === 'manual';
    sideWrap.classList.toggle('d-none', !manualMode);
    insightsCard.classList.toggle('d-none', !manualMode);
    sideSelect.required = manualMode;

    if (!manualMode) {
        sideSelect.value = '';
        stopManualOverrideAutoRefresh();
    } else {
        loadManualOverrideInsights();
        startManualOverrideAutoRefresh();
    }
}

function formatDecisionAmount(value) {
    const amount = Number(value || 0);
    return amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderManualOverrideInsights(payload) {
    const meta = document.getElementById('manualOverrideMeta');
    const tbody = document.getElementById('manualOverrideInsightsBody');
    const sideSelect = document.getElementById('manualResultSideSelect');
    if (!meta || !tbody) {
        return;
    }

    const sideLabels = { silver: 'Silver', gold: 'Gold', diamond: 'Diamond' };
    const phase = String(payload.phase || 'betting');
    const remaining = Number(payload.remaining || 0);
    const round = Number(payload.round || 0);
    const activePlayers = Number(payload.active_players || 0);
    const selectedManualSide = String(payload.manual_result_side || '');

    meta.textContent = 'Round #' + round + ' • Phase: ' + phase + ' • ' + remaining + 's left • Active players: ' + activePlayers;

    const rows = ['silver', 'gold', 'diamond'].map(function (side) {
        const totals = payload.totals || {};
        const projection = payload.projection || {};
        const totalBet = Number(totals[side] || 0);
        const projectedPayout = Number((projection[side] || {}).projected_payout || 0);
        const companyNet = Number((projection[side] || {}).projected_company_net || 0);
        const isSelected = selectedManualSide === side;

        const netClass = companyNet >= 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold';
        const selectedBadge = isSelected ? ' <span class="badge bg-primary ms-1">Selected</span>' : '';

        return '<tr>' +
            '<td>' + sideLabels[side] + selectedBadge + '</td>' +
            '<td class="text-end">' + formatDecisionAmount(totalBet) + '</td>' +
            '<td class="text-end">' + formatDecisionAmount(projectedPayout) + '</td>' +
            '<td class="text-end ' + netClass + '">' + formatDecisionAmount(companyNet) + '</td>' +
        '</tr>';
    }).join('');

    tbody.innerHTML = rows;

    if (sideSelect && !sideSelect.value && selectedManualSide) {
        sideSelect.value = selectedManualSide;
    }
}

function loadManualOverrideInsights() {
    const modeSelect = document.getElementById('resultModeSelect');
    if (!modeSelect || modeSelect.value !== 'manual') {
        return;
    }

    fetch(manualOverrideInsightsUrl, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Failed to fetch override insights');
            }
            return response.json();
        })
        .then(renderManualOverrideInsights)
        .catch(function () {
            const tbody = document.getElementById('manualOverrideInsightsBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Unable to load current round totals.</td></tr>';
            }
        });
}

function startManualOverrideAutoRefresh() {
    stopManualOverrideAutoRefresh();
    manualOverrideInsightsInterval = setInterval(loadManualOverrideInsights, 5000);
}

function stopManualOverrideAutoRefresh() {
    if (manualOverrideInsightsInterval) {
        clearInterval(manualOverrideInsightsInterval);
        manualOverrideInsightsInterval = null;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    ['previewWinnerSide', 'previewPool', 'previewWinnerPool', 'previewBet'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener(id === 'previewWinnerSide' ? 'change' : 'input', updatePayoutPreview);
        }
    });
    const commissionInput = document.querySelector('input[name="commission_percent"]');
    if (commissionInput) {
        commissionInput.addEventListener('input', updatePayoutPreview);
    }
    ['silver_profit_x', 'gold_profit_x', 'diamond_profit_x'].forEach(function (name) {
        const el = document.querySelector('input[name="' + name + '"]');
        if (el) {
            el.addEventListener('input', updatePayoutPreview);
        }
    });

    const resultModeSelect = document.getElementById('resultModeSelect');
    if (resultModeSelect) {
        resultModeSelect.addEventListener('change', toggleManualOverrideFields);
    }

    const manualRefreshBtn = document.getElementById('manualOverrideRefreshBtn');
    if (manualRefreshBtn) {
        manualRefreshBtn.addEventListener('click', loadManualOverrideInsights);
    }

    toggleManualOverrideFields();
    updatePayoutPreview();
});
</script>
@endpush
