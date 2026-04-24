@extends('admin.layouts.app')
@section('panel')

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Edit Tenant — {{ $tenant->name }}</h5>
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-sm btn-outline--secondary">
                    <i class="las la-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">

                {{-- Read-only credentials block --}}
                <div class="card border border-secondary mb-4">
                    <div class="card-header bg-secondary bg-opacity-10">
                        <h6 class="mb-0"><i class="las la-key"></i> API Credentials (read-only)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="fw-bold small">API Key</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" value="{{ $tenant->api_key }}" readonly id="editApiKey">
                                <button class="btn btn-outline-secondary" onclick="copyVal('editApiKey')"><i class="las la-copy"></i></button>
                            </div>
                        </div>
                        <div class="text-muted small">
                            Webhook Secret is stored encrypted and cannot be displayed. Use <strong>Regenerate Keys</strong> on the list page to issue new credentials.
                        </div>
                    </div>
                </div>

                <form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Tenant / Operator Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $tenant->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Panel Login Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $tenant->email) }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Balance Mode</label>
                        <div class="d-flex gap-4 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="balance_mode" id="modeInternal"
                                       value="internal" {{ old('balance_mode', $tenant->balance_mode) === 'internal' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeInternal">
                                    <strong>Internal Tokens</strong> — balances in our DB
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="balance_mode" id="modeWebhook"
                                       value="webhook" {{ old('balance_mode', $tenant->balance_mode) === 'webhook' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeWebhook">
                                    <strong>Webhook Mode</strong> — tenant's own server manages balances
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="webhookSection" style="{{ old('balance_mode', $tenant->balance_mode) === 'webhook' ? '' : 'display:none' }}">
                        <div class="form-group">
                            <label class="{{ old('balance_mode', $tenant->balance_mode) === 'webhook' ? 'required' : '' }}">Webhook URL</label>
                            <input type="url" name="webhook_url" class="form-control @error('webhook_url') is-invalid @enderror"
                                   value="{{ old('webhook_url', $tenant->webhook_url) }}">
                            @error('webhook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Currency</label>
                                <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror"
                                       value="{{ old('currency', $tenant->currency) }}" maxlength="10" required>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Callback / Redirect URL <small class="text-muted">(optional)</small></label>
                        <input type="url" name="callback_url" class="form-control @error('callback_url') is-invalid @enderror"
                               value="{{ old('callback_url', $tenant->callback_url) }}">
                        @error('callback_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Wallet Top-up URL <small class="text-muted">(optional)</small></label>
                        <input type="url" name="wallet_topup_url" class="form-control @error('wallet_topup_url') is-invalid @enderror"
                               value="{{ old('wallet_topup_url', $tenant->wallet_topup_url) }}"
                               placeholder="https://operator.com/wallet/add-balance">
                        <small class="text-muted">Used by the in-game Add Balance button to open the tenant's main wallet flow.</small>
                        @error('wallet_topup_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Commission %</label>
                                <div class="input-group">
                                    <input type="number" name="commission_percent" step="0.01" min="0" max="100"
                                           class="form-control @error('commission_percent') is-invalid @enderror"
                                           value="{{ old('commission_percent', $tenant->commission_percent) }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                @error('commission_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Min Bet</label>
                                <input type="number" name="min_bet" step="1" min="1"
                                       class="form-control @error('min_bet') is-invalid @enderror"
                                       value="{{ old('min_bet', $tenant->min_bet) }}" required>
                                @error('min_bet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Max Bet</label>
                                <input type="number" name="max_bet" step="1"
                                       class="form-control @error('max_bet') is-invalid @enderror"
                                       value="{{ old('max_bet', $tenant->max_bet) }}" required>
                                @error('max_bet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required">Session TTL (minutes)</label>
                        <input type="number" name="session_ttl_minutes" min="10" max="1440"
                               class="form-control @error('session_ttl_minutes') is-invalid @enderror"
                               value="{{ old('session_ttl_minutes', $tenant->session_ttl_minutes) }}" required>
                        @error('session_ttl_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Allowed IPs <small class="text-muted">(optional)</small></label>
                        <textarea name="allowed_ips" rows="2"
                                  class="form-control @error('allowed_ips') is-invalid @enderror">{{ old('allowed_ips', $tenant->allowed_ips) }}</textarea>
                        <small class="text-muted">Comma-separated. Leave blank to allow any IP.</small>
                        @error('allowed_ips')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Reset Panel Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="text" name="new_panel_password"
                               class="form-control @error('new_panel_password') is-invalid @enderror"
                               placeholder="New password (min 8 chars)">
                        @error('new_panel_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Panel login URL: <a href="{{ url('/tenant/login') }}" target="_blank">{{ url('/tenant/login') }}</a></small>
                    </div>

                    {{-- Separate Database Section --}}
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="las la-database text-primary"></i> Separate Database (Optional)</h6>
                    <p class="text-muted small mb-3">
                        Configure a dedicated MySQL database for this tenant's transaction logs.
                        Sessions always remain in the main DB. After saving, run:
                        <code>php artisan tenant:db-setup {{ $tenant->id }}</code>
                    </p>

                    <div class="form-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="use_separate_db" value="1"
                                   id="useSeparateDb" {{ old('use_separate_db', $tenant->use_separate_db) ? 'checked' : '' }}
                                   onchange="toggleDbSection(this.checked)">
                            <label class="form-check-label fw-bold" for="useSeparateDb">
                                Use Separate Database for this tenant
                            </label>
                        </div>
                    </div>

                    <div id="dbSection" style="{{ old('use_separate_db', $tenant->use_separate_db) ? '' : 'display:none' }}">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">DB Host</label>
                                <input type="text" name="db_host" class="form-control"
                                       value="{{ old('db_host', $tenant->db_host ?? '127.0.0.1') }}"
                                       placeholder="127.0.0.1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DB Port</label>
                                <input type="number" name="db_port" class="form-control"
                                       value="{{ old('db_port', $tenant->db_port ?? 3306) }}"
                                       placeholder="3306">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Database Name</label>
                                <input type="text" name="db_name" class="form-control"
                                       value="{{ old('db_name', $tenant->db_name) }}"
                                       placeholder="tenant_gamedb">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">DB Username</label>
                                <input type="text" name="db_username" class="form-control"
                                       value="{{ old('db_username', $tenant->db_username) }}"
                                       placeholder="db_user">
                            </div>
                            <div class="col-12">
                                <label class="form-label">DB Password <small class="text-muted">(leave blank to keep current)</small></label>
                                <input type="password" name="db_password" class="form-control"
                                       autocomplete="new-password" placeholder="••••••••">
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline--info" onclick="testDbConnection()">
                            <i class="las la-plug"></i> Test Connection
                        </button>
                        <span id="dbTestResult" class="ms-2 small"></span>
                    </div>

                    <hr class="my-4">

                    <button type="submit" class="btn btn--primary w-100">
                        <i class="las la-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
function copyVal(id) {
    var el = document.getElementById(id);
    el.select(); el.setSelectionRange(0, 99999);
    document.execCommand('copy');
    iziToast.success({ message: 'Copied!', position: 'topRight', timeout: 1500 });
}
document.querySelectorAll('input[name="balance_mode"]').forEach(function(el) {
    el.addEventListener('change', function() {
        var ws = document.getElementById('webhookSection');
        if (ws) ws.style.display = this.value === 'webhook' ? '' : 'none';
    });
});
function toggleDbSection(show) {
    document.getElementById('dbSection').style.display = show ? '' : 'none';
}
function testDbConnection() {
    var btn = event.target; btn.disabled = true;
    var result = document.getElementById('dbTestResult');
    result.textContent = 'Testing…'; result.className = 'ms-2 small text-muted';
    fetch('{{ route("admin.tenants.test.db", $tenant->id) }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => {
        result.textContent = d.ok ? '✓ Connection OK' : '✗ ' + d.error;
        result.className   = 'ms-2 small ' + (d.ok ? 'text-success fw-bold' : 'text-danger fw-bold');
    }).catch(() => {
        result.textContent = '✗ Request failed';
        result.className   = 'ms-2 small text-danger fw-bold';
    }).finally(() => { btn.disabled = false; });
}
</script>
@endpush
