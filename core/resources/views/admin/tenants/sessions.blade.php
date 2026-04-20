@extends('admin.layouts.app')
@section('panel')

<div class="row mb-3">
    <div class="col-12 d-flex align-items-center justify-content-between">
        <div>
            <h5 class="mb-0">{{ $tenant->name }}</h5>
            <small class="text-muted">Game Sessions</small>
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
                                <th>Player ID</th>
                                <th>Player Name</th>
                                <th>Game</th>
                                <th>Currency</th>
                                <th>Balance Cache</th>
                                <th>Status</th>
                                <th>IP</th>
                                <th>Expires At</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                            <tr>
                                <td>{{ $session->id }}</td>
                                <td>
                                    <span class="font-monospace small">{{ $session->player_id }}</span>
                                </td>
                                <td>{{ $session->player_name }}</td>
                                <td><span class="badge bg--primary">{{ $session->game_id }}</span></td>
                                <td>{{ $session->currency }}</td>
                                <td>{{ number_format($session->balance_cache, 2) }}</td>
                                <td>
                                    @if($session->status === 'active')
                                        @if($session->expires_at && $session->expires_at->isPast())
                                            <span class="badge badge--warning">Expired</span>
                                        @else
                                            <span class="badge badge--success">Active</span>
                                        @endif
                                    @elseif($session->status === 'expired')
                                        <span class="badge badge--warning">Expired</span>
                                    @else
                                        <span class="badge badge--secondary">{{ ucfirst($session->status) }}</span>
                                    @endif
                                </td>
                                <td><span class="small text-muted">{{ $session->ip_address }}</span></td>
                                <td>
                                    <span class="small">
                                        {{ $session->expires_at ? $session->expires_at->format('d M y H:i') : '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="small">
                                        {{ $session->last_activity_at ? $session->last_activity_at->diffForHumans() : '—' }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">No sessions yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($sessions->hasPages())
            <div class="card-footer">{{ $sessions->links() }}</div>
            @endif
        </div>
    </div>
</div>

@endsection
