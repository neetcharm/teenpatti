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
                            <label class="form-label text-muted small fw-bold text-uppercase">Session TTL (minutes)</label>
                            <input type="number" min="5" max="1440" class="form-control"
                                   name="session_ttl_minutes"
                                   value="{{ old('session_ttl_minutes', (int) $authTenant->session_ttl_minutes) }}">
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-3 small">
                        <strong>Payout Rule:</strong> Aap har side ke liye fixed winner payout multiplier set kar sakte ho. <br>
                        Example: <code>2.80x</code> aur <code>10%</code> commission par 2000 bet ka total return <code>5040</code>. Agar side ka multiplier blank hai to dynamic rule chalega:
                        <code>(Total Pool / Winner Side Pool) × (1 - Commission% / 100)</code>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Silver Payout X</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control"
                                   name="silver_profit_x"
                                   placeholder="Blank = Dynamic"
                                   value="{{ old('silver_profit_x', $authTenant->silver_profit_x !== null ? number_format((float) $authTenant->silver_profit_x, 2, '.', '') : '') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Gold Payout X</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control"
                                   name="gold_profit_x"
                                   placeholder="Blank = Dynamic"
                                   value="{{ old('gold_profit_x', $authTenant->gold_profit_x !== null ? number_format((float) $authTenant->gold_profit_x, 2, '.', '') : '') }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label text-muted small fw-bold text-uppercase">Diamond Payout X</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control"
                                   name="diamond_profit_x"
                                   placeholder="Blank = Dynamic"
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
                        <strong>Wallet Sync Contract:</strong> session create aur game focus par hum aapke webhook ko
                        <code>action=balance</code> ke saath hit karte hain. In-game <code>Top Up</code> button aapke
                        configured wallet URL me <code>player_id</code>, <code>player_name</code>, <code>session_token</code>,
                        <code>game_id</code>, <code>currency</code>, <code>tenant_id</code> aur <code>return_url</code>
                        query params append karta hai, taki aap sahi user wallet open karke user ko game me wapas bhej sako.
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
    updatePayoutPreview();
});
</script>
@endpush
