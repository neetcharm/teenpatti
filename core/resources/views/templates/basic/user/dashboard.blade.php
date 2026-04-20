@extends('Template::layouts.master')
@section('content')
    @php
        $kyc = getContent('user_kyc.content', true);
    @endphp
    <section class="pt-120 pb-120">
        <div class="container container-xxl">
            <div class="notice"></div>
            <div class="row mb-3">
                <div class="col-md-12">
                    @if ($user->kv == Status::KYC_UNVERIFIED && $user->kyc_rejection_reason)
                        <div class="d-widget" role="alert">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h4 class="alert-heading text--danger">@lang('KYC Documents Rejected')</h4>
                                <button class="btn btn--danger btn--sm" data-bs-toggle="modal"
                                    data-bs-target="#kycRejectionReason">
                                    @lang('Show Reason')
                                </button>
                            </div>
                            <hr>
                            <p class="mb-0">
                                {{ __(@$kyc->data_values->reject) }}
                                <a href="{{ route('user.kyc.form') }}">
                                    @lang('Click Here to Re-submit Documents')
                                </a>.
                            </p>
                            <br>
                            <a href="{{ route('user.kyc.data') }}">@lang('See KYC Data')</a>
                        </div>
                    @elseif ($user->kv == Status::KYC_UNVERIFIED)
                        <div class="d-widget" role="alert">
                            <h4 class="alert-heading text--danger">@lang('KYC Verification required')</h4>
                            <hr>
                            <p class="mb-0">
                                {{ __($kyc->data_values->verification_content) }}
                                <a class="text--base" href="{{ route('user.kyc.form') }}">
                                    @lang('Click Here to Submit Documents')
                                </a>
                            </p>
                        </div>
                    @elseif($user->kv == Status::KYC_PENDING)
                        <div class="d-widget" role="alert">
                            <h4 class="alert-heading text--warning">@lang('KYC Verification pending')</h4>
                            <hr>
                            <p class="mb-0">
                                {{ __($kyc->data_values->pending_content) }}
                                <a class="text--base" href="{{ route('user.kyc.data') }}">
                                    @lang('See KYC Data')
                                </a>
                            </p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-balance h-100">
                        <div class="d-widget-icon">
                            <i class="las la-money-bill-wave"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Balance')</p>
                            <h2 class="title">{{ showAmount($widget['total_balance']) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-deposit h-100">
                        <div class="d-widget-icon">
                            <i class="las la-wallet"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Deposit')</p>
                            <h2 class="title">{{ showAmount($widget['total_deposit']) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-withdraw h-100">
                        <div class="d-widget-icon">
                            <i class="las la-hand-holding-usd"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Withdraw')</p>
                            <h2 class="title">{{ showAmount($widget['total_withdrawn']) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-invest h-100">
                        <div class="d-widget-icon">
                            <i class="las la-cash-register"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Invest')</p>
                            <h2 class="title">{{ showAmount($widget['total_invest']) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-win h-100">
                        <div class="d-widget-icon">
                            <i class="las la-trophy"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Win')</p>
                            <h2 class="title">{{ showAmount($widget['total_win']) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-30">
                    <div class="d-widget dashbaord-widget-card d-widget-loss h-100">
                        <div class="d-widget-icon">
                            <i class="las la-money-bill-alt"></i>
                        </div>
                        <div class="d-widget-content">
                            <p>@lang('Total Loss')</p>
                            <h2 class="title">{{ showAmount($widget['total_loss']) }}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="premium-dashboard-panel mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h4 class="mb-0">@lang('Premium Analytics')</h4>
                    <span class="premium-badge">@lang('Live Data')</span>
                </div>
                <div class="row g-3">
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Today Bets')</span>
                            <h4>{{ $dashboardStats['today_bets'] }}</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Today Invest')</span>
                            <h4>{{ showAmount($dashboardStats['today_invest']) }}</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Today Win')</span>
                            <h4>{{ showAmount($dashboardStats['today_win']) }}</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Win Rate')</span>
                            <h4>{{ getAmount($dashboardStats['win_rate']) }}%</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Highest Win')</span>
                            <h4>{{ showAmount($dashboardStats['highest_win']) }}</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Rounds (W/L)')</span>
                            <h4>{{ $dashboardStats['total_rounds'] }} ({{ $dashboardStats['win_rounds'] }}/{{ $dashboardStats['loss_rounds'] }})</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Games Available')</span>
                            <h4>{{ $dashboardStats['total_games'] }} ({{ $dashboardStats['live_games'] }} live)</h4>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6">
                        <div class="premium-stat-card">
                            <span>@lang('Global Live Players')</span>
                            <h4>{{ $dashboardStats['global_live_players_10m'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="premium-dashboard-panel h-100">
                        <h5 class="mb-3">@lang('Recent Results')</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless premium-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>@lang('Game')</th>
                                        <th>@lang('Bet')</th>
                                        <th>@lang('Win')</th>
                                        <th>@lang('Result')</th>
                                        <th>@lang('Time')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentLogs as $log)
                                        <tr>
                                            <td>{{ __(@$log->game->name) }}</td>
                                            <td>{{ showAmount($log->invest) }}</td>
                                            <td>{{ showAmount($log->win_amo) }}</td>
                                            <td>
                                                @if ($log->win_status == Status::WIN)
                                                    <span class="text-success">@lang('Win')</span>
                                                @elseif($log->win_status == Status::LOSS)
                                                    <span class="text-danger">@lang('Loss')</span>
                                                @else
                                                    <span class="text-warning">@lang('Running')</span>
                                                @endif
                                            </td>
                                            <td>{{ showDateTime($log->created_at, 'd M, H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">@lang('No game logs yet')</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="premium-dashboard-panel h-100">
                        <h5 class="mb-3">@lang('Global Top Wins')</h5>
                        <ul class="premium-list mb-4">
                            @forelse ($globalTopWins as $topWin)
                                @php
                                    $name = trim((string) (@$topWin->user->username ?: trim((@$topWin->user->firstname ?? '') . ' ' . (@$topWin->user->lastname ?? ''))));
                                    $name = $name ?: 'Player';
                                    $nameMask = strlen($name) <= 2 ? substr($name, 0, 1) . '*' : substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 3, 1)) . substr($name, -1);
                                @endphp
                                <li>
                                    <div>
                                        <strong>{{ $nameMask }}</strong>
                                        <small class="d-block text-muted">{{ __(@$topWin->game->name) }}</small>
                                    </div>
                                    <span class="text-success">{{ showAmount($topWin->win_amo) }}</span>
                                </li>
                            @empty
                                <li class="text-muted">@lang('No global wins found')</li>
                            @endforelse
                        </ul>

                        <h6 class="mb-2">@lang('Global Live Feed')</h6>
                        <ul id="dashboardLiveFeed" class="premium-list mb-0">
                            <li class="text-muted">@lang('Loading live feed...')</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="mb-2">@lang('Teen Patti Access')</h4>
                <p class="text-muted mb-0">@lang('Launch the live Teen Patti table or open demo mode.')</p>
            </div>
            <div class="row justify-content-center">
                @forelse($games as $game)
                    <div class="col-xl-3 col-lg-4 col-sm-6 mb-30 wow fadeInUp" data-wow-duration="0.5s"
                        data-wow-delay="0.3s">
                        <div class="game-card style--two">
                            <div class="game-card__thumb">
                                <img src="{{ getImage(getFilePath('game') . '/' . $game->image, getFileSize('game')) }}"
                                    alt="image">
                            </div>
                            <div class="game-card__content">
                                <h4 class="game-name">{{ __($game->name) }}</h4>
                                <div class="d-flex justify-content-between gap-2 w-100 mt-2">
                                    <a class="cmn-btn play_btn w-100 btn-sm text-center"
                                        href="{{ route('user.play.game', $game->alias) }}">
                                        @lang('Play Now')
                                    </a>
                                    <a class="cmn-btn-two play_btn w-100 btn-sm text-center"
                                        href="{{ route('user.play.game', [$game->alias, 'demo']) }}">
                                        @lang('Demo')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-center">@lang('Teen Patti is not available right now.')</h5>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    @if ($user->kv == Status::KYC_UNVERIFIED && $user->kyc_rejection_reason)
        <div class="modal fade" id="kycRejectionReason">
            <div class="modal-dialog" role="document">
                <div class="modal-content section--bg">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('KYC Document Rejection Reason')</h5>
                        <span class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                            <i class="las la-times"></i>
                        </span>
                    </div>
                    <div class="modal-body">
                        <p>{{ $user->kyc_rejection_reason }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('style')
    <style>
        .premium-dashboard-panel {
            background: hsl(var(--black)/0.5);
            border: 1px solid hsl(var(--white)/0.1);
            border-radius: 14px;
            padding: 18px;
        }

        .premium-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            background: hsl(var(--base));
            color: #fff;
        }

        .premium-stat-card {
            background: hsl(var(--black)/0.35);
            border: 1px solid hsl(var(--white)/0.08);
            border-radius: 12px;
            padding: 14px;
            height: 100%;
        }

        .premium-stat-card span {
            display: block;
            font-size: 13px;
            color: hsl(var(--white)/0.65);
            margin-bottom: 5px;
        }

        .premium-stat-card h4 {
            margin-bottom: 0;
            font-size: 20px;
        }

        .premium-table thead th {
            font-size: 12px;
            text-transform: uppercase;
            color: hsl(var(--white)/0.65);
            border-bottom: 1px solid hsl(var(--white)/0.08);
        }

        .premium-table tbody td {
            border-bottom: 1px solid hsl(var(--white)/0.06);
            color: hsl(var(--white));
            font-size: 14px;
            white-space: nowrap;
        }

        .premium-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 260px;
            overflow: auto;
        }

        .premium-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid hsl(var(--white)/0.07);
            font-size: 13px;
        }

        .premium-list li:last-child {
            border-bottom: none;
        }
    </style>
@endpush

@push('script')
    <script>
        "use strict";

        (function() {
            const endpoint = @json(route('live.stats'));
            const feedEl = document.getElementById('dashboardLiveFeed');

            if (!feedEl) {
                return;
            }

            function esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, function(ch) {
                    return ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    })[ch];
                });
            }

            function render(items) {
                if (!items || !items.length) {
                    feedEl.innerHTML = '<li class="text-muted">@lang("No live data yet")</li>';
                    return;
                }

                feedEl.innerHTML = items.slice(0, 8).map(function(item) {
                    return `
                        <li>
                            <div>
                                <strong>${esc(item.user)}</strong>
                                <small class="d-block text-muted">${esc(item.game)}</small>
                            </div>
                            <div class="text-end">
                                <div>${esc(item.invest)} {{ gs('cur_text') }}</div>
                                <small class="text-muted">${esc(item.created_human)}</small>
                            </div>
                        </li>
                    `;
                }).join('');
            }

            function fetchFeed() {
                $.getJSON(endpoint, function(response) {
                    render(response.stats || []);
                });
            }

            fetchFeed();
            setInterval(fetchFeed, 5000);
        })();
    </script>
@endpush
