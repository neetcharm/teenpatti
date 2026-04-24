@extends('admin.layouts.app')
@section('panel')

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Add New Tenant</h5>
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-sm btn-outline--secondary">
                    <i class="las la-arrow-left"></i> Back
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.tenants.store') }}" method="POST">
                    @csrf

                    {{-- Name + Email --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Tenant / Operator Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="e.g. Acme Gaming Ltd" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Panel Login Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}" placeholder="tenant@example.com" required>
                                <small class="text-muted">Tenant logs in at <code>/tenant/login</code></small>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Balance Mode --}}
                    <div class="form-group">
                        <label class="required">Balance Mode</label>
                        <div class="d-flex gap-4 mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="balance_mode" id="modeInternal"
                                       value="internal" {{ old('balance_mode', 'internal') !== 'webhook' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeInternal">
                                    <strong>Internal Tokens</strong>
                                    <div class="text-muted small">Balances stored in our DB. Tenant tops up players from their panel.</div>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="balance_mode" id="modeWebhook"
                                       value="webhook" {{ old('balance_mode') === 'webhook' ? 'checked' : '' }}>
                                <label class="form-check-label" for="modeWebhook">
                                    <strong>Webhook Mode</strong>
                                    <div class="text-muted small">Tenant's server manages balances. We POST debit/credit to their webhook.</div>
                                </label>
                            </div>
                        </div>
                        @error('balance_mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    {{-- Webhook URL (visible only in webhook mode) --}}
                    <div id="webhookSection" style="{{ old('balance_mode') === 'webhook' ? '' : 'display:none' }}">
                        <div class="form-group">
                            <label>Webhook URL</label>
                            <input type="url" name="webhook_url" class="form-control @error('webhook_url') is-invalid @enderror"
                                   value="{{ old('webhook_url') }}" placeholder="https://operator.com/api/game/webhook">
                            <small class="text-muted">We POST balance/debit/credit events here.</small>
                            @error('webhook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Callback + Currency --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="required">Currency</label>
                                <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror"
                                       value="{{ old('currency', 'INR') }}" placeholder="INR" maxlength="10" required>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Callback / Redirect URL <small class="text-muted">(optional)</small></label>
                                <input type="url" name="callback_url" class="form-control @error('callback_url') is-invalid @enderror"
                                       value="{{ old('callback_url') }}" placeholder="https://operator.com/game/return">
                                @error('callback_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Wallet Top-up URL <small class="text-muted">(optional)</small></label>
                        <input type="url" name="wallet_topup_url" class="form-control @error('wallet_topup_url') is-invalid @enderror"
                               value="{{ old('wallet_topup_url') }}" placeholder="https://operator.com/wallet/add-balance">
                        <small class="text-muted">When the player taps Add Balance inside the game, we redirect here.</small>
                        @error('wallet_topup_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Bet limits + Commission --}}
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Min Bet</label>
                                <input type="number" name="min_bet" step="1" min="1"
                                       class="form-control @error('min_bet') is-invalid @enderror"
                                       value="{{ old('min_bet', 10) }}" required>
                                @error('min_bet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Max Bet</label>
                                <input type="number" name="max_bet" step="1"
                                       class="form-control @error('max_bet') is-invalid @enderror"
                                       value="{{ old('max_bet', 10000) }}" required>
                                @error('max_bet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Commission %</label>
                                <div class="input-group">
                                    <input type="number" name="commission_percent" step="0.01" min="0" max="100"
                                           class="form-control @error('commission_percent') is-invalid @enderror"
                                           value="{{ old('commission_percent', 0) }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                @error('commission_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- TTL + IPs --}}
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="required">Session TTL (minutes)</label>
                                <input type="number" name="session_ttl_minutes" min="10" max="1440"
                                       class="form-control @error('session_ttl_minutes') is-invalid @enderror"
                                       value="{{ old('session_ttl_minutes', 120) }}" required>
                                @error('session_ttl_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Allowed IPs <small class="text-muted">(optional, comma-separated)</small></label>
                                <input type="text" name="allowed_ips"
                                       class="form-control @error('allowed_ips') is-invalid @enderror"
                                       value="{{ old('allowed_ips') }}" placeholder="192.168.1.1, 10.0.0.2">
                                @error('allowed_ips')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        <i class="las la-info-circle"></i>
                        API Key, Webhook Secret, and Panel Password are created automatically on save.
                        You will see them <strong>once</strong> - copy them immediately.
                    </div>

                    <button type="submit" class="btn btn--primary w-100">
                        <i class="las la-plus"></i> Create Tenant & Generate Credentials
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
document.querySelectorAll('input[name="balance_mode"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var ws = document.getElementById('webhookSection');
        ws.style.display = this.value === 'webhook' ? '' : 'none';
    });
});
</script>
@endpush
