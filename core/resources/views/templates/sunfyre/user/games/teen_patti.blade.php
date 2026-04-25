@extends('Template::layouts.master')
@section('content')
<script>
document.body.classList.add('tp-game-page');
</script>
<style>
    html, body.tp-game-page {
        overflow: hidden !important;
        background: #050505 !important;
    }

    body.tp-game-page .header,
    body.tp-game-page .breadcrumb,
    body.tp-game-page .footer-area,
    body.tp-game-page footer,
    body.tp-game-page .scroll-top,
    body.tp-game-page .scroll-to-top {
        display: none !important;
    }

    body.tp-game-page section.py-100,
    body.tp-game-page section.py-100 > .container,
    body.tp-game-page .row.min-vh-100,
    body.tp-game-page .col-12 {
        width: 100% !important;
        max-width: 100% !important;
        height: 100svh !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
    }

    .tp-game-wrapper {
        width: min(100vw, 375px) !important;
        max-width: min(100vw, 375px) !important;
        height: 100svh !important;
        min-height: 0 !important;
        margin: 0 auto !important;
        border-radius: 0 !important;
        overflow: hidden !important;
        background:
            linear-gradient(180deg, rgba(8, 9, 7, 0.74) 0%, rgba(8, 9, 7, 0.94) 100%),
            radial-gradient(ellipse at 50% 34%, rgba(13, 87, 56, 0.86) 0%, rgba(6, 46, 32, 0.76) 38%, rgba(5, 5, 5, 0.94) 78%),
            linear-gradient(145deg, #1c0b08 0%, #052417 44%, #070707 100%) !important;
    }

    .tp-play-area {
        width: 100% !important;
        max-width: 100% !important;
        padding: 4px 4px 0 !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .tp-columns {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        gap: 3px !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .tp-col {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        flex: 0 1 auto !important;
        padding: 5px 2px !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }

    .tp-char-frame {
        width: 48px !important;
        height: 48px !important;
        border-radius: 15px !important;
    }

    .tp-side-name {
        font-size: 8px !important;
        letter-spacing: 0.2px !important;
        white-space: nowrap !important;
    }

    .tp-cards-row {
        min-height: 33px !important;
        gap: 1px !important;
    }

    .tp-card-container,
    .tp-card-img {
        width: 18px !important;
        height: 27px !important;
    }

    .tp-hand-rank {
        width: 84% !important;
        min-width: 0 !important;
        min-height: 19px !important;
        margin: 2px 0 !important;
    }

    .tp-bet-slot {
        min-height: 70px !important;
    }

    .tp-bet-info {
        font-size: 7px !important;
        line-height: 1.08 !important;
    }
</style>
<style>
    body.tp-game-page {
        background:
            radial-gradient(circle at 12% 0%, rgba(36, 210, 255, 0.28), transparent 30%),
            radial-gradient(circle at 90% 4%, rgba(255, 116, 188, 0.26), transparent 34%),
            linear-gradient(160deg, #e9fbff 0%, #f5efff 50%, #fff8e7 100%) !important;
    }

    .tp-game-wrapper {
        background:
            linear-gradient(145deg, rgba(255, 255, 255, 0.62), rgba(227, 249, 255, 0.44) 44%, rgba(255, 238, 249, 0.5)),
            radial-gradient(circle at 50% 16%, rgba(255, 218, 93, 0.28), transparent 34%) !important;
        border: 1px solid rgba(255, 255, 255, 0.72) !important;
        box-shadow: 0 24px 60px rgba(72, 102, 136, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.75) !important;
        backdrop-filter: blur(22px) saturate(150%) !important;
        -webkit-backdrop-filter: blur(22px) saturate(150%) !important;
    }

    .tp-header,
    .tp-timer-section,
    .tp-history-bar,
    .tp-footer,
    .tp-col,
    .tp-bet-slot,
    .tp-bal-p,
    .tp-win-p {
        background: rgba(255, 255, 255, 0.34) !important;
        border-color: rgba(255, 255, 255, 0.58) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.62), 0 12px 26px rgba(70, 110, 140, 0.16) !important;
        backdrop-filter: blur(18px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(18px) saturate(160%) !important;
    }

    .tp-logo-text,
    .tp-side-name,
    .tp-hist-label {
        color: #20343f !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.75) !important;
    }

    .tp-btn-icon,
    .tp-timer-p {
        background: rgba(255, 255, 255, 0.48) !important;
        border-color: rgba(255, 208, 92, 0.72) !important;
        color: #25313d !important;
    }

    .tp-phase-badge {
        background: linear-gradient(180deg, rgba(35, 210, 157, 0.74), rgba(24, 157, 128, 0.78)) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.62) !important;
    }

    .tp-bet-info,
    .tp-hand-rank {
        background: rgba(255, 255, 255, 0.44) !important;
        color: #25313d !important;
    }

    .gold-text,
    .tp-bal-val,
    .tp-win-val {
        color: #008b6f !important;
        text-shadow: none !important;
    }

    .tp-btn-repeat {
        background: linear-gradient(180deg, #ffd96a 0%, #f4b533 100%) !important;
        color: #2b2100 !important;
        border-color: rgba(255, 255, 255, 0.62) !important;
    }
</style>

<div class="row align-items-center justify-content-center min-vh-100 p-0 m-0">
    <div class="col-12 col-md-8 col-lg-5 p-0">
        <div class="tp-game-wrapper" id="tpGameWrapper">

            <div class="tp-header">
                <a href="{{ route('user.home') }}" class="tp-btn-icon"><i class="fas fa-times"></i></a>
                <div class="tp-logo">
                    <div class="tp-logo-icon"><i class="fas fa-crown"></i></div>
                    <div class="tp-logo-text">Royal<br>Teen Patti</div>
                </div>
                <div class="tp-header-right">
                    <button class="tp-btn-icon audioBtn"><i class="fas fa-volume-up"></i></button>
                    <button class="tp-btn-icon" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i></button>
                    <button class="tp-btn-icon"><i class="fas fa-cog"></i></button>
                </div>
            </div>

            <div class="tp-timer-section">
                <div id="tpPhaseBadge" class="tp-phase-badge phase-betting">@lang('Place Your Bets')</div>
                <div class="tp-timer-p">
                    <div class="tp-timer-clock"><i class="far fa-clock"></i></div>
                    <div class="tp-timer-num" id="tpTimer">20</div>
                </div>
            </div>

            <!-- ===== FANTASY DEALER LADY ===== -->
            <div class="tp-dealer-section">
                <div class="tp-dealer-avatar" id="tpDealerAvatar">
                    <div class="tp-dealer-avatar-body">
                        <img
                            src="{{ asset('assets/templates/parimatch/images/dealer_avatar_body.png') }}"
                            class="tp-dealer-avatar-img"
                            alt="Dealer Avatar"
                            loading="eager"
                            decoding="async"
                        >
                        <div class="tp-dealer-hand-layer tp-dealer-hand-right" aria-hidden="true">
                            <img
                                src="{{ asset('assets/templates/parimatch/images/dealer_avatar_hand_right.png') }}"
                                alt=""
                                loading="eager"
                                decoding="async"
                            >
                        </div>
                    </div>
                    <div class="dealer-glow-ring"></div>
                    <div class="tp-dealer-name">Dealer Riya</div>
                </div>
                <div class="tp-dealer-status" id="tpDealerStatus">Waiting...</div>
                <div class="tp-shuffle-deck" id="tpShuffleDeck">
                    <div class="tp-shuffle-card shuffle-1"></div>
                    <div class="tp-shuffle-card shuffle-2"></div>
                    <div class="tp-shuffle-card shuffle-3"></div>
                </div>
            </div>

            <!-- Narration Bar -->
            <div class="tp-narration-bar" id="tpNarrationBar">
                <div class="tp-narration-icon"><i class="fas fa-comment-dots"></i></div>
                <div class="tp-narration-text" id="tpNarrationText"></div>
            </div>

            <div class="tp-play-area">
                <div class="tp-columns">

                    <div class="tp-col" id="tpColSilver" data-choose="silver">
                        <div class="tp-char-frame silver-border">
                            <div class="tp-char-label">S</div>
                            <img src="{{ asset('assets/templates/parimatch/images/silver_character.png') }}" alt="Silver">
                        </div>
                        <div class="tp-side-name">@lang('Silver')</div>
                        <div class="tp-cards-row" id="tpCardsSilver">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankSilver">--</div>
                        <div class="tp-bet-slot silver-bg" id="tpSlotSilver">
                            <div class="tp-chips-pile" id="tpPileSilver"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllSilver">0</span></div>
                                <div class="gold-text">You: <span id="tpYouSilver">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColGold" data-choose="gold">
                        <div class="tp-char-frame gold-border">
                            <div class="tp-char-label">G</div>
                            <img src="{{ asset('assets/templates/parimatch/images/gold_character.png') }}" alt="Gold">
                        </div>
                        <div class="tp-side-name">@lang('Gold')</div>
                        <div class="tp-cards-row" id="tpCardsGold">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankGold">--</div>
                        <div class="tp-bet-slot gold-bg" id="tpSlotGold">
                            <div class="tp-chips-pile" id="tpPileGold"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllGold">0</span></div>
                                <div class="gold-text">You: <span id="tpYouGold">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="tp-col" id="tpColDiamond" data-choose="diamond">
                        <div class="tp-char-frame diamond-border">
                            <div class="tp-char-label">D</div>
                            <img src="{{ asset('assets/templates/parimatch/images/diamond_character.png') }}" alt="Diamond">
                        </div>
                        <div class="tp-side-name">@lang('Diamond')</div>
                        <div class="tp-cards-row" id="tpCardsDiamond">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                            <img src="{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}" class="tp-card-img">
                        </div>
                        <div class="tp-hand-rank" id="tpRankDiamond">--</div>
                        <div class="tp-bet-slot diamond-bg" id="tpSlotDiamond">
                            <div class="tp-chips-pile" id="tpPileDiamond"></div>
                            <div class="tp-bet-info">
                                <div>All: <span id="tpAllDiamond">0</span></div>
                                <div class="gold-text">You: <span id="tpYouDiamond">0</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tp-history-bar">
                <span class="tp-hist-label">Result</span>
                <div class="tp-hist-items" id="tpHistory"></div>
                <button class="tp-btn-chart"><i class="fas fa-chart-line"></i></button>
            </div>

            <div class="tp-footer">
                @php
                    $teenPattiChipValues = array_values($teenPattiChipValues ?? \App\Models\Tenant::DEFAULT_TEEN_PATTI_CHIPS);
                    $chipLabel = static function ($amount) {
                        $amount = (int) $amount;
                        return $amount >= 1000 && $amount % 1000 === 0 ? ((int) ($amount / 1000)) . 'K' : number_format($amount);
                    };
                @endphp
                <div class="tp-chips-row">
                    @foreach($teenPattiChipValues as $index => $chipAmount)
                        <div class="tp-chip-btn chip-color-{{ $index % 5 }} {{ $index === 0 ? 'selected' : '' }}" data-value="{{ (int) $chipAmount }}">
                            <span>{{ $chipLabel($chipAmount) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="tp-bottom-actions">
                    <div class="tp-bal-p">
                        <div class="tp-bal-icon"><i class="fas fa-coins"></i></div>
                        <div class="tp-bal-val bal">{{ showAmount($balance, currencyFormat: false) }}</div>
                    </div>
                    <div class="tp-win-p">
                        <span class="tp-win-label">WIN</span>
                        <span class="tp-win-val" id="tpWinVal">0</span>
                    </div>
                    <button class="tp-btn-repeat" id="tpBtnRepeat">@lang('Repeat')</button>
                </div>
            </div>

            <div class="tp-overlay" id="tpStopOverlay">
                <img src="{{ asset('assets/templates/parimatch/images/dealer_avatar_body.png') }}" class="tp-stop-char tp-stop-avatar" alt="Betting Stopped Avatar">
                <div class="tp-stop-sign">
                    <span>@lang('Stop Betting!')</span>
                </div>
            </div>

            <div class="tp-winner-modal" id="tpWinnerModal">
                <div class="tp-avatar-pop">
                    <div class="tp-avatar-pop-frame">
                        <img src="" id="tpWinnerImg" alt="winner">
                    </div>
                    <div class="tp-avatar-pop-sign">
                        <div class="tp-pop-round" id="tpWinnerRoundTitle">@lang('Round Result')</div>
                        <div class="tp-pop-message" id="tpWinnerStatusMsg">@lang('Winner Popup')</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<form id="game" method="post" style="display:none">@csrf</form>
@endsection

@php
    $tpCssVersion = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time();
    $tpPremiumCssVersion = @filemtime(public_path('assets/global/css/game/teen-patti-premium.css')) ?: time();
    $tpJsVersion = @filemtime(public_path('assets/global/js/game/teenPatti.js')) ?: time();
@endphp

@push('style-lib')
    <link href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssVersion }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/game/teen-patti-premium.css') }}?v={{ $tpPremiumCssVersion }}" rel="stylesheet">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/soundControl.js') }}"></script>
@endpush

@push('script')
<script>
"use strict";
document.body.classList.add('tp-game-page');
let imagePath = "{{ asset(activeTemplate(true) . 'images/cards/') }}";
let avatarPath = "{{ asset('assets/templates/parimatch/images/') }}";
let cardBackImage = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}";
let investUrl = "{{ route('user.play.invest', ['teen_patti', @$isDemo]) }}";
let syncUrl = "{{ route('user.play.teen_patti.global.sync', [@$isDemo]) }}";
let gameEndUrl = "{{ route('user.play.end', ['teen_patti', @$isDemo]) }}";
let tpAudioAssetPath = "{{ asset('assets/audio') }}";
let currentUserId = "{{ auth()->id() }}";
let tpChipValues = @json(array_values($teenPattiChipValues ?? \App\Models\Tenant::DEFAULT_TEEN_PATTI_CHIPS));
</script>
<script src="{{ asset('assets/global/js/game/teenPatti.js') }}?v={{ $tpJsVersion }}"></script>
@endpush
