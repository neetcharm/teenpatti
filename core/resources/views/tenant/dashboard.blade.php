@extends('tenant.layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box" style="background:#eef2ff;color:#5a67d8"><i class="las la-users"></i></div>
                <div>
                    <div class="text-muted small">Total Players</div>
                    <div class="fw-bold fs-5">{{ number_format($totalPlayers) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box" style="background:#f0fdf4;color:#16a34a"><i class="las la-play-circle"></i></div>
                <div>
                    <div class="text-muted small">Active Sessions</div>
                    <div class="fw-bold fs-5">{{ number_format($activeSessions) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box" style="background:#fef2f2;color:#dc2626"><i class="las la-arrow-circle-down"></i></div>
                <div>
                    <div class="text-muted small">Total Bet (Tokens)</div>
                    <div class="fw-bold fs-5">{{ number_format($totalDebited, 0) }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box" style="background:#fffbeb;color:#d97706"><i class="las la-arrow-circle-up"></i></div>
                <div>
                    <div class="text-muted small">Total Won (Tokens)</div>
                    <div class="fw-bold fs-5">{{ number_format($totalCredited, 0) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Recent Sessions --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3 d-flex justify-content-between align-items-center">
                <span><i class="las la-play-circle text-primary"></i> Recent Sessions</span>
                <a href="{{ route('tenant.sessions.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead><tr>
                        <th>Player</th><th>Game</th><th>Status</th><th>Started</th>
                    </tr></thead>
                    <tbody>
                        @forelse($recentSessions as $s)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $s->player_name }}</div>
                                <small class="text-muted">{{ $s->player_id }}</small>
                            </td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $s->game_id }}</span></td>
                            <td>
                                @if($s->status === 'active' && $s->expires_at > now())
                                    <span class="badge bg-success">Live</span>
                                @elseif($s->status === 'active')
                                    <span class="badge bg-warning text-dark">Expired</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($s->status) }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $s->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No sessions yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Recent Transactions --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3 d-flex justify-content-between align-items-center">
                <span><i class="las la-exchange-alt text-warning"></i> Recent Transactions</span>
                <a href="{{ route('tenant.transactions.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 table-hover">
                    <thead><tr>
                        <th>Action</th><th>Player</th><th>Amount</th><th>Time</th>
                    </tr></thead>
                    <tbody>
                        @forelse($recentTransactions as $t)
                        <tr>
                            <td>
                                @php $cls = match($t->action){ 'debit'=>'danger','credit'=>'success','balance'=>'info',default=>'secondary' }; @endphp
                                <span class="badge bg-{{ $cls }}">{{ strtoupper($t->action) }}</span>
                            </td>
                            <td class="small">{{ $t->player_id }}</td>
                            <td class="fw-semibold">{{ number_format($t->amount, 2) }}</td>
                            <td class="text-muted small">{{ $t->created_at->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No transactions yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
