@extends('tenant.layouts.app')
@section('title', 'Player History')
@section('page-title', 'Player Transaction History')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="mb-0">{{ $user->firstname }} {{ $user->lastname }}</h5>
        <small class="text-muted font-monospace">{{ $user->username }}</small>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="balance-pill fs-6">
            Balance: {{ number_format($user->balance, 2) }}
        </div>
        <a href="{{ route('tenant.players.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="las la-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 table-hover align-middle">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Amount</th>
                    <th>Balance Before</th>
                    <th>Balance After</th>
                    <th>Round</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $t)
                <tr>
                    <td>
                        @php $cls = match($t->action){ 'debit'=>'danger','credit'=>'success','balance'=>'info','rollback'=>'warning',default=>'secondary' }; @endphp
                        <span class="badge bg-{{ $cls }}">{{ strtoupper($t->action) }}</span>
                    </td>
                    <td class="fw-semibold">{{ number_format($t->amount, 2) }}</td>
                    <td class="text-muted">{{ number_format($t->balance_before, 2) }}</td>
                    <td class="fw-semibold text-{{ $t->action === 'credit' ? 'success' : ($t->action === 'debit' ? 'danger' : 'dark') }}">
                        {{ number_format($t->balance_after, 2) }}
                    </td>
                    <td class="small text-muted font-monospace">{{ $t->round_id ?? '—' }}</td>
                    <td>
                        @if($t->status === 'ok')<span class="badge bg-success">OK</span>
                        @elseif($t->status === 'failed')<span class="badge bg-danger">Failed</span>
                        @else<span class="badge bg-warning text-dark">Pending</span>@endif
                    </td>
                    <td class="small text-muted">{{ $t->created_at->format('d M y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer bg-white">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection
