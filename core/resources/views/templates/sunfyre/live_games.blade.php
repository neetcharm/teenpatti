@extends('Template::layouts.frontend')
@section('content')

    <section class="games-section pt-100 pb-50">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <h2 class="mb-0">@lang('Live Games')</h2>
                <a href="{{ route('games') }}" class="btn btn--gradient btn--sm">@lang('All Games')</a>
            </div>
            <div class="row gy-4">
                <div class="col-lg-8">
                    <div class="games-section-wrapper">
                        @include('Template::partials.game', ['games' => $games])
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="live-stats-widget">
                        <h5 class="mb-3">@lang('Global Live Bets')</h5>
                        <ul id="globalLiveStatsList" class="list-unstyled mb-0"></ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (@$sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection

@push('style')
    <style>
        .live-stats-widget {
            background: hsl(var(--black) / 0.5);
            border: 1px solid hsl(var(--white) / 0.1);
            border-radius: 12px;
            padding: 20px;
            max-height: 520px;
            overflow: auto;
        }

        .live-stat-item {
            border: 1px solid hsl(var(--white) / 0.08);
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .live-stat-item:last-child {
            margin-bottom: 0;
        }

        .live-stat-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .live-stat-item .status-win {
            color: #28c76f;
        }

        .live-stat-item .status-loss {
            color: #ea5455;
        }

        .live-stat-item .status-running {
            color: #ff9f43;
        }
    </style>
@endpush

@push('script')
    <script>
        "use strict";

        (function() {
            const endpoint = @json(route('live.stats'));
            const $list = $('#globalLiveStatsList');

            function renderItems(items) {
                if (!items || !items.length) {
                    $list.html('<li class="text-muted">@lang("No live bet data yet")</li>');
                    return;
                }

                let html = '';
                items.forEach(function(item) {
                    const statusClass = 'status-' + item.status;
                    html += `
                        <li class="live-stat-item">
                            <div class="live-stat-row">
                                <strong>${item.user}</strong>
                                <span class="${statusClass}">${item.status.toUpperCase()}</span>
                            </div>
                            <div class="live-stat-row mt-1">
                                <span>${item.game}</span>
                                <span>${item.invest} {{ gs('cur_text') }}</span>
                            </div>
                            <div class="live-stat-row mt-1 text-muted">
                                <span>${item.result ?? '-'}</span>
                                <span>${item.created_human}</span>
                            </div>
                        </li>
                    `;
                });
                $list.html(html);
            }

            function fetchStats() {
                $.getJSON(endpoint, function(res) {
                    renderItems(res.stats || []);
                });
            }

            fetchStats();
            setInterval(fetchStats, 3000);
        })();
    </script>
@endpush
