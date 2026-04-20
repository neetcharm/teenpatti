@extends('layouts.master_saas')

@push('game_style')
    @php $tpCssV = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time(); @endphp
    <link rel="stylesheet" href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssV }}">
    <style>
        .tp-game-wrapper { background: none; box-shadow: none; border: none; }
        .tp-topbar, .tp-header { display: none; } /* Hidden because master_saas provides them */
        .tp-wv-root { min-height: auto; }
        .tp-timer-section { background: rgba(0,0,0,0.2); }
    </style>
@endpush

@section('game_content')
    <div class="tp-wv-root">
        <div class="tp-game-wrapper">
            {{-- Timer --}}
            <div class="tp-timer-section">
                <div id="tpPhaseBadge" class="tp-phase-badge phase-betting">Place Your Bets</div>
                <div class="tp-timer-p">
                    <div class="tp-timer-num" id="tpTimer">20</div>
                </div>
            </div>

            {{-- Dealer --}}
            <div class="tp-dealer-section">
                <div class="tp-dealer-avatar" id="tpDealerAvatar">
                    <div class="dealer-glow-ring"></div>
                </div>
                <div class="tp-dealer-status" id="tpDealerStatus">Waiting...</div>
            </div>

            {{-- Betting area --}}
            <div class="tp-play-area">
                <div class="tp-columns">
                    <div class="tp-col" id="tpColSilver" data-choose="silver">
                        <div class="tp-char-frame silver-border">
                            <div class="tp-char-label">S</div>
                            <img src="{{ asset('assets/templates/parimatch/images/silver_character.png') }}" alt="Silver">
                        </div>
                        <div class="tp-side-name">SILVER</div>
                        <div class="tp-bet-slot silver-bg" id="slot-silver">
                            <div class="tp-bet-info">
                                <span class="total-bet">0</span>
                                <span class="my-bet gold-text">0</span>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColGold" data-choose="gold">
                        <div class="tp-char-frame gold-border">
                            <div class="tp-char-label">G</div>
                            <img src="{{ asset('assets/templates/parimatch/images/gold_character.png') }}" alt="Gold">
                        </div>
                        <div class="tp-side-name">GOLD</div>
                        <div class="tp-bet-slot gold-bg" id="slot-gold">
                            <div class="tp-bet-info">
                                <span class="total-bet">0</span>
                                <span class="my-bet gold-text">0</span>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColDiamond" data-choose="diamond">
                        <div class="tp-char-frame diamond-border">
                            <div class="tp-char-label">D</div>
                            <img src="{{ asset('assets/templates/parimatch/images/diamond_character.png') }}" alt="Diamond">
                        </div>
                        <div class="tp-side-name">DIAMOND</div>
                        <div class="tp-bet-slot diamond-bg" id="slot-diamond">
                            <div class="tp-bet-info">
                                <span class="total-bet">0</span>
                                <span class="my-bet gold-text">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- History --}}
            <div class="tp-history-bar">
                <div class="tp-hist-label">RECENT</div>
                <div class="tp-hist-items" id="tpHistoryItems"></div>
            </div>

            {{-- Chips --}}
            <div class="tp-footer">
                <div class="tp-chips-row">
                    <div class="tp-chip-btn c-400 selected" data-amount="10"><span>10</span></div>
                    <div class="tp-chip-btn c-2k" data-amount="50"><span>50</span></div>
                    <div class="tp-chip-btn c-4k" data-amount="100"><span>100</span></div>
                    <div class="tp-chip-btn c-20k" data-amount="500"><span>500</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('game_script')
    <script>
        let currentBet = 10;
        let isBetting = false;

        $('.tp-chip-btn').on('click', function() {
            $('.tp-chip-btn').removeClass('selected');
            $(this).addClass('selected');
            currentBet = $(this).data('amount');
        });

        $('.tp-col').on('click', function() {
            let choose = $(this).data('choose');
            placeBet(choose);
        });

        function placeBet(choose) {
            if(isBetting) return;

            $.ajax({
                url: "{{ route('user.play.invest', ['teen_patti']) }}",
                method: "POST",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { choose: choose, invest: currentBet },
                beforeSend: () => { isBetting = true; },
                success: (res) => {
                    if(!res.error) {
                        updateBalance(res.balance);
                        refreshSync();
                    } else {
                        alert(res.error);
                    }
                },
                complete: () => { isBetting = false; }
            });
        }

        function refreshSync() {
            $.get("{{ route('user.play.teen_patti.global.sync') }}", (res) => {
                renderState(res);
            });
        }

        function renderState(state) {
            $('#tpTimer').text(typeof state.remaining !== 'undefined' ? state.remaining : 0);
            $('#tpPhaseBadge').text(state.phase === 'betting' ? 'Place Your Bets' : 'Round Holding');

            // Update slots
            ['silver', 'gold', 'diamond'].forEach(key => {
                const allBets = state.bets || {};
                const myBets = state.my_bets || {};
                $(`#slot-${key} .total-bet`).text(typeof allBets[key] !== 'undefined' ? allBets[key] : 0);
                $(`#slot-${key} .my-bet`).text(typeof myBets[key] !== 'undefined' ? myBets[key] : 0);
            });
        }

        refreshSync();
        setInterval(refreshSync, 1000);
    </script>
@endpush
