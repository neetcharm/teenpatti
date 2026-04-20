@extends('admin.layouts.app')
@section('panel')

{{-- Flash: show newly generated credentials once --}}
@if(session('new_api_key'))
<div class="row mb-4">
    <div class="col-12">
        <div class="card border border-warning">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="las la-exclamation-triangle"></i>
                Save These Credentials — They Will NOT Be Shown Again!
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fw-bold">API Key:</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_api_key') }}" readonly id="flashApiKey">
                            <button class="btn btn-outline-secondary" onclick="copyVal('flashApiKey')"><i class="las la-copy"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Webhook Secret (for HMAC signing):</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_webhook_secret') }}" readonly id="flashSecret">
                            <button class="btn btn-outline-secondary" onclick="copyVal('flashSecret')"><i class="las la-copy"></i></button>
                        </div>
                    </div>
                    @if(session('new_panel_email'))
                    <div class="col-md-6">
                        <label class="fw-bold">Panel Login Email:</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_panel_email') }}" readonly id="flashEmail">
                            <button class="btn btn-outline-secondary" onclick="copyVal('flashEmail')"><i class="las la-copy"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold">Panel Login Password:</label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" value="{{ session('new_panel_password') }}" readonly id="flashPwd">
                            <button class="btn btn-outline-secondary" onclick="copyVal('flashPwd')"><i class="las la-copy"></i></button>
                        </div>
                        <small class="text-muted">Panel URL: <a href="{{ url('/tenant/login') }}" target="_blank">{{ url('/tenant/login') }}</a></small>
                    </div>
                    @endif
                </div>
                <small class="text-danger mt-2 d-block"><strong>Warning:</strong> Close this page = credentials gone forever. Store them securely now.</small>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">Game Provider Tenants</h5>
                <a href="{{ route('admin.tenants.create') }}" class="btn btn-sm btn-outline--primary">
                    <i class="las la-plus"></i> Add Tenant
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>API Key</th>
                                <th>Webhook URL</th>
                                <th>Currency</th>
                                <th>Commission</th>
                                <th>Sessions</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tenants as $tenant)
                            <tr>
                                <td>{{ $tenant->id }}</td>
                                <td><strong>{{ $tenant->name }}</strong></td>
                                <td>
                                    <div class="input-group input-group-sm" style="width:200px">
                                        <input type="text" class="form-control form-control-sm font-monospace"
                                               value="{{ $tenant->api_key }}" readonly id="ak{{ $tenant->id }}">
                                        <button class="btn btn-outline-secondary btn-sm" onclick="copyVal('ak{{ $tenant->id }}')">
                                            <i class="las la-copy"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small" style="max-width:160px;overflow:hidden;display:block;text-overflow:ellipsis;white-space:nowrap"
                                          title="{{ $tenant->webhook_url }}">{{ $tenant->webhook_url }}</span>
                                </td>
                                <td>{{ $tenant->currency }}</td>
                                <td>{{ $tenant->commission_percent }}%</td>
                                <td>
                                    <a href="{{ route('admin.tenants.sessions', $tenant->id) }}" class="badge bg--primary">
                                        {{ $tenant->sessions_count }} sessions
                                    </a>
                                </td>
                                <td>
                                    @if($tenant->status)
                                        <span class="badge badge--success">Active</span>
                                    @else
                                        <span class="badge badge--danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="button--group">
                                        <a href="{{ route('admin.tenants.edit', $tenant->id) }}"
                                           class="btn btn-sm btn-outline--primary">
                                            <i class="las la-edit"></i> Edit
                                        </a>
                                        <a href="{{ route('admin.tenants.games', $tenant->id) }}"
                                           class="btn btn-sm btn-outline--success">
                                            <i class="las la-gamepad"></i> Games
                                        </a>
                                        <a href="{{ route('admin.tenants.transactions', $tenant->id) }}"
                                           class="btn btn-sm btn-outline--info">
                                            <i class="las la-list"></i> Txns
                                        </a>
                                        <button type="button"
                                                class="btn btn-sm btn-outline--warning confirmationBtn"
                                                data-action="{{ route('admin.tenants.regenerate', $tenant->id) }}"
                                                data-question="Regenerate API Key + Secret? All active sessions will be expired.">
                                            <i class="las la-key"></i> Regen Keys
                                        </button>
                                        <button type="button"
                                                class="btn btn-sm {{ $tenant->status ? 'btn-outline--danger' : 'btn-outline--success' }} confirmationBtn"
                                                data-action="{{ route('admin.tenants.status', $tenant->id) }}"
                                                data-question="{{ $tenant->status ? 'Deactivate this tenant?' : 'Activate this tenant?' }}">
                                            <i class="las la-{{ $tenant->status ? 'ban' : 'check' }}"></i>
                                            {{ $tenant->status ? 'Disable' : 'Enable' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center">No tenants yet. <a href="{{ route('admin.tenants.create') }}">Add one</a></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($tenants->hasPages())
            <div class="card-footer">{{ $tenants->links() }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Integration Guide --}}
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="las la-book"></i> Quick Integration Guide</h6></div>
            <div class="card-body">
                <h6>Step 1 — Create Game Session (Tenant Server → Our API)</h6>
                <pre class="bg-dark text-light p-3 rounded small">POST {{ url('api/v1/session/create') }}
Content-Type: application/json
X-API-Key: your_api_key
X-Signature: HMAC-SHA256(api_secret, raw_json_body)

{
  "player_id":   "your_user_123",
  "player_name": "Rahul Kumar",
  "game_id":     "teen_patti",
  "currency":    "INR"
}</pre>

                <h6 class="mt-3">Step 2 — Open game_url in Android WebView</h6>
                <pre class="bg-dark text-light p-3 rounded small">// Response will contain:
{ "game_url": "{{ url('launch') }}/{session_token}" }

// Android Java:
webView.loadUrl(gameUrl);</pre>

                <h6 class="mt-3">Step 3 — Your Webhook Endpoint must handle these events:</h6>
                <pre class="bg-dark text-light p-3 rounded small">POST your_webhook_url

// Balance check:
{ "action": "balance", "player_id": "...", "session_id": "...", "signature": "..." }
→ { "status": "ok", "balance": 5000.00 }

// Bet (debit):
{ "action": "debit", "player_id": "...", "amount": 500, "round_id": "tp_r1234", ... }
→ { "status": "ok", "balance": 4500.00, "transaction_id": "your_txn_id" }

// Win (credit):
{ "action": "credit", "player_id": "...", "amount": 900, "round_id": "tp_r1234", ... }
→ { "status": "ok", "balance": 5400.00, "transaction_id": "your_txn_id" }

// Verify signature:
HMAC-SHA256(your_webhook_secret, "action|player_id|amount|round_id|timestamp")</pre>
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
</script>
@endpush
