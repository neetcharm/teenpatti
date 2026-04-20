@extends('tenant.layouts.app')
@section('title', 'Transactions')
@section('page-title', 'Webhook Transactions')

@section('content')

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="las la-exchange-alt text-warning"></i> All Transactions</span>
        <span class="text-muted small">{{ $transactions->total() }} total</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 table-hover align-middle" style="font-size:13px">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Player</th>
                    <th>Amount</th>
                    <th>Bal. Before</th>
                    <th>Bal. After</th>
                    <th>Round</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $t)
                <tr>
                    <td>
                        @php $cls = match($t->action){ 'debit'=>'danger','credit'=>'success','balance'=>'info','rollback'=>'warning',default=>'secondary' }; @endphp
                        <span class="badge bg-{{ $cls }}">{{ strtoupper($t->action) }}</span>
                    </td>
                    <td class="font-monospace small">{{ $t->player_id }}</td>
                    <td class="fw-semibold">{{ $t->amount > 0 ? number_format($t->amount, 2) : '—' }}</td>
                    <td class="text-muted">{{ number_format($t->balance_before, 2) }}</td>
                    <td class="fw-semibold">{{ number_format($t->balance_after, 2) }}</td>
                    <td class="small text-muted font-monospace">{{ $t->round_id ?? '—' }}</td>
                    <td>
                        @if($t->status === 'ok')<span class="badge bg-success">OK</span>
                        @elseif($t->status === 'failed')
                            <span class="badge bg-danger" title="{{ $t->error_message }}">Failed</span>
                        @else<span class="badge bg-warning text-dark">Pending</span>@endif
                    </td>
                    <td class="small text-muted">{{ $t->created_at->format('d M y H:i') }}</td>
                    <td>
                        <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal"
                                data-bs-target="#txnDetail{{ $t->id }}">
                            <i class="las la-eye"></i>
                        </button>
                    </td>
                </tr>

                <div class="modal fade" id="txnDetail{{ $t->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">#{{ $t->id }} — {{ strtoupper($t->action) }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if($t->error_message)
                                <div class="alert alert-danger small mb-3">{{ $t->error_message }}</div>
                                @endif
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="text-muted small text-uppercase mb-1 fw-bold">Request</p>
                                        <pre class="bg-dark text-light rounded p-3 small" style="max-height:250px;overflow:auto">{{ json_encode($t->request_payload, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-muted small text-uppercase mb-1 fw-bold">Response</p>
                                        <pre class="bg-dark text-light rounded p-3 small" style="max-height:250px;overflow:auto">{{ json_encode($t->response_payload, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-white">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection
