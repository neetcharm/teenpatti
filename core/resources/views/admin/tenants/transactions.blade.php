@extends('admin.layouts.app')
@section('panel')

<div class="row mb-3">
    <div class="col-12 d-flex align-items-center justify-content-between">
        <div>
            <h5 class="mb-0">{{ $tenant->name }}</h5>
            <small class="text-muted">Webhook Transaction Log</small>
        </div>
        <a href="{{ route('admin.tenants.index') }}" class="btn btn-sm btn-outline--secondary">
            <i class="las la-arrow-left"></i> Back to Tenants
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Action</th>
                                <th>Player ID</th>
                                <th>Round ID</th>
                                <th>Amount</th>
                                <th>Balance Before</th>
                                <th>Balance After</th>
                                <th>Our Txn ID</th>
                                <th>Tenant Txn ID</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $txn)
                            <tr>
                                <td>{{ $txn->id }}</td>
                                <td>
                                    @php
                                        $badgeClass = match($txn->action) {
                                            'debit'    => 'badge--danger',
                                            'credit'   => 'badge--success',
                                            'balance'  => 'badge--info',
                                            'rollback' => 'badge--warning',
                                            default    => 'badge--secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ strtoupper($txn->action) }}</span>
                                </td>
                                <td><span class="small font-monospace">{{ $txn->player_id }}</span></td>
                                <td><span class="small font-monospace">{{ $txn->round_id ?? '—' }}</span></td>
                                <td>
                                    @if($txn->amount > 0)
                                        {{ number_format($txn->amount, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ number_format($txn->balance_before, 2) }}</td>
                                <td>{{ number_format($txn->balance_after, 2) }}</td>
                                <td>
                                    <span class="small font-monospace text-muted"
                                          title="{{ $txn->our_txn_id }}"
                                          style="max-width:120px;overflow:hidden;display:inline-block;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle">
                                        {{ $txn->our_txn_id }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small font-monospace text-muted">{{ $txn->tenant_txn_id ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($txn->status === 'ok')
                                        <span class="badge badge--success">OK</span>
                                    @elseif($txn->status === 'failed')
                                        <span class="badge badge--danger" title="{{ $txn->error_message }}">Failed</span>
                                    @else
                                        <span class="badge badge--warning">Pending</span>
                                    @endif
                                </td>
                                <td><span class="small">{{ $txn->created_at->format('d M y H:i:s') }}</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline--info"
                                            data-bs-toggle="modal"
                                            data-bs-target="#txnModal{{ $txn->id }}">
                                        <i class="las la-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            {{-- Detail Modal --}}
                            <div class="modal fade" id="txnModal{{ $txn->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">
                                                Txn #{{ $txn->id }} — {{ strtoupper($txn->action) }} — {{ $txn->player_id }}
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            @if($txn->status === 'failed' && $txn->error_message)
                                            <div class="alert alert-danger small mb-3">
                                                <strong>Error:</strong> {{ $txn->error_message }}
                                            </div>
                                            @endif
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="text-muted small text-uppercase mb-1">Request Payload</h6>
                                                    <pre class="bg-dark text-light p-3 rounded small" style="max-height:300px;overflow:auto">{{ json_encode($txn->request_payload, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-muted small text-uppercase mb-1">Response Payload</h6>
                                                    <pre class="bg-dark text-light p-3 rounded small" style="max-height:300px;overflow:auto">{{ json_encode($txn->response_payload, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted">No webhook transactions yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($transactions->hasPages())
            <div class="card-footer">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
