@extends('tenant.layouts.app')
@section('title', 'Players & Tokens')
@section('page-title', 'Players & Tokens')

@section('content')

@if($authTenant->balance_mode === 'internal')
<div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
    <i class="las la-info-circle fs-5"></i>
    <span><strong>Internal Token Mode:</strong> Balances are stored in our system. Use the top-up button to add tokens to any player.</span>
</div>
@else
<div class="alert alert-secondary d-flex align-items-center gap-2 py-2 mb-3">
    <i class="las la-info-circle fs-5"></i>
    <span><strong>Webhook Mode:</strong> Balances are managed by your server. Token top-up is not available in this mode.</span>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="las la-users text-primary"></i> All Players</span>
        <span class="text-muted small">{{ $players->total() }} players</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 table-hover align-middle">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Currency</th>
                    <th>Token Balance</th>
                    <th>Sessions</th>
                    <th>Last Seen</th>
                    @if($authTenant->balance_mode === 'internal')
                    <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($players as $player)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $player->player_name }}</div>
                        <small class="text-muted font-monospace">{{ $player->player_id }}</small>
                    </td>
                    <td>{{ $player->currency }}</td>
                    <td>
                        <span class="balance-pill">
                            {{ number_format($player->token_balance, 2) }}
                        </span>
                    </td>
                    <td>{{ $player->session_count }}</td>
                    <td class="text-muted small">
                        {{ $player->last_seen ? \Carbon\Carbon::parse($player->last_seen)->diffForHumans() : '—' }}
                    </td>
                    @if($authTenant->balance_mode === 'internal')
                    <td>
                        <button class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal"
                                data-bs-target="#topupModal{{ $player->internal_user_id }}">
                            <i class="las la-plus"></i> Add Tokens
                        </button>
                        <button class="btn btn-sm btn-outline-danger ms-1"
                                data-bs-toggle="modal"
                                data-bs-target="#deductModal{{ $player->internal_user_id }}">
                            <i class="las la-minus"></i>
                        </button>
                        <a href="{{ route('tenant.players.history', $player->internal_user_id) }}"
                           class="btn btn-sm btn-outline-secondary ms-1">
                            <i class="las la-history"></i>
                        </a>
                    </td>
                    @endif
                </tr>

                {{-- Top-up Modal --}}
                @if($authTenant->balance_mode === 'internal')
                <div class="modal fade" id="topupModal{{ $player->internal_user_id }}" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">Add Tokens — {{ $player->player_name }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="{{ route('tenant.players.topup', $player->internal_user_id) }}">
                                @csrf
                                <div class="modal-body">
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Amount</label>
                                        <div class="input-group">
                                            <input type="number" name="amount" class="form-control"
                                                   min="1" step="1" placeholder="e.g. 500" required>
                                            <span class="input-group-text">{{ $player->currency }}</span>
                                        </div>
                                    </div>
                                    <div class="mb-1">
                                        <label class="form-label fw-semibold">Note (optional)</label>
                                        <input type="text" name="note" class="form-control"
                                               placeholder="e.g. Recharge">
                                    </div>
                                    <div class="text-muted small mt-2">
                                        Current balance: <strong>{{ number_format($player->token_balance, 2) }}</strong>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-success">Add Tokens</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Deduct Modal --}}
                <div class="modal fade" id="deductModal{{ $player->internal_user_id }}" tabindex="-1">
                    <div class="modal-dialog modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">Deduct Tokens — {{ $player->player_name }}</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="{{ route('tenant.players.deduct', $player->internal_user_id) }}">
                                @csrf
                                <div class="modal-body">
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Amount to Deduct</label>
                                        <div class="input-group">
                                            <input type="number" name="amount" class="form-control"
                                                   min="1" max="{{ $player->token_balance }}" step="1" required>
                                            <span class="input-group-text">{{ $player->currency }}</span>
                                        </div>
                                    </div>
                                    <div class="text-muted small mt-2">
                                        Current balance: <strong>{{ number_format($player->token_balance, 2) }}</strong>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Deduct</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="las la-users" style="font-size:32px;opacity:.3"></i><br>
                        No players yet. Players appear here once they launch a game via your integration.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($players->hasPages())
    <div class="card-footer bg-white">{{ $players->links() }}</div>
    @endif
</div>

@endsection
