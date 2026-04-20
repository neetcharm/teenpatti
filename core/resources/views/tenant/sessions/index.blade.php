@extends('tenant.layouts.app')
@section('title', 'Game Sessions')
@section('page-title', 'Game Sessions')

@section('content')

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="las la-play-circle text-primary"></i> All Sessions</span>
        <span class="text-muted small">{{ $sessions->total() }} total</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 table-hover align-middle">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Game</th>
                    <th>Currency</th>
                    <th>Balance Cache</th>
                    <th>Status</th>
                    <th>IP</th>
                    <th>Expires</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $s)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $s->player_name }}</div>
                        <small class="text-muted font-monospace">{{ $s->player_id }}</small>
                    </td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $s->game_id }}</span></td>
                    <td>{{ $s->currency }}</td>
                    <td><span class="balance-pill">{{ number_format($s->balance_cache, 2) }}</span></td>
                    <td>
                        @if($s->status === 'active' && $s->expires_at > now())
                            <span class="badge bg-success">Live</span>
                        @elseif($s->status === 'active')
                            <span class="badge bg-warning text-dark">Expired</span>
                        @elseif($s->status === 'closed')
                            <span class="badge bg-secondary">Closed</span>
                        @else
                            <span class="badge bg-danger">{{ ucfirst($s->status) }}</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $s->ip_address }}</td>
                    <td class="small text-muted">{{ $s->expires_at?->format('d M y H:i') ?? '—' }}</td>
                    <td class="small text-muted">{{ $s->last_activity_at?->diffForHumans() ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No sessions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sessions->hasPages())
    <div class="card-footer bg-white">{{ $sessions->links() }}</div>
    @endif
</div>

@endsection
