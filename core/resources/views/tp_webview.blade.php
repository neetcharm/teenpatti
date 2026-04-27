<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a1a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Royal Teen Patti</title>

    {{-- Decorative fonts (mobile-first fantasy look) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Teen Patti CSS --}}
    @php $tpCssV = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time(); @endphp
    <link rel="stylesheet" href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssV }}">

    <style>
        /* WebView reset – no site chrome */
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background: #0a0a1a;
            overflow: hidden;
            /* Disable pull-to-refresh on Android WebView */
            overscroll-behavior: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            user-select: none;
        }

        /* Full-height single-column layout */
        .tp-wv-root {
            display: flex;
            align-items: stretch;
            justify-content: center;
            min-height: var(--tp-wv-height, 100svh);
            height: var(--tp-wv-height, 100svh);
            padding: 0;
            overflow: hidden;
        }

        .tp-wv-root .tp-game-wrapper {
            width: 100%;
            max-width: 100%;
            min-height: var(--tp-wv-height, 100svh);
            height: var(--tp-wv-height, 100svh);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .tp-stage-shell {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: stretch;
        }

        .tp-stage {
            width: 100%;
            min-height: 100%;
            transform-origin: top center;
            transform: scale(1);
            will-change: transform;
        }

        /* Balance / Win bar at the very top */
        .tp-wv-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,.55);
            padding: 6px 14px;
            font-size: 12px;
            color: #ccc;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .tp-wv-topbar .player-name {
            font-weight: 600;
            color: #f0c040;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .tp-wv-topbar .player-bal {
            color: #7fffd4;
            font-weight: 700;
        }

        /* Spinner shown while first sync loads */
        #tpWvLoader {
            position: fixed;
            inset: 0;
            background: #0a0a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity .4s;
        }
        #tpWvLoader.hidden { opacity: 0; pointer-events: none; }
        .wv-spinner {
            width: 46px; height: 46px;
            border: 4px solid rgba(255,255,255,.15);
            border-top-color: #f0c040;
            border-radius: 50%;
            animation: wvSpin .7s linear infinite;
        }
        @keyframes wvSpin { to { transform: rotate(360deg); } }

        .tp-wv-root .tp-game-wrapper {
            width: 100%;
            max-width: 100%;
            height: var(--tp-wv-height, 100svh);
            min-height: 0;
        }

        .tp-stage {
            min-width: 0;
            overflow: hidden;
            display: grid;
            grid-template-rows: auto auto minmax(88px, .44fr) minmax(212px, 1fr) auto auto;
        }

        body.tp-split-view .tp-stage {
            grid-template-rows: auto auto auto minmax(88px, 1fr) auto auto;
        }

        body.tp-split-view .tp-header {
            min-height: 30px !important;
            margin: 2px 4px 0 !important;
            padding: 2px 5px !important;
            border-radius: 10px !important;
        }

        body.tp-split-view .tp-btn-icon {
            width: 24px !important;
            height: 24px !important;
            font-size: 10px !important;
        }

        body.tp-split-view .tp-logo-icon {
            width: 24px !important;
            height: 24px !important;
            font-size: 15px !important;
        }

        body.tp-split-view .tp-logo-text {
            font-size: 11px !important;
            line-height: .9 !important;
        }

        body.tp-split-view .tp-header-right {
            gap: 4px !important;
        }

        body.tp-split-view .tp-timer-section {
            min-height: 28px !important;
            margin: 2px 4px 1px !important;
            padding: 2px 5px !important;
            gap: 6px !important;
            border-radius: 10px !important;
        }

        body.tp-split-view .tp-phase-badge {
            min-width: 106px !important;
            padding: 3px 7px !important;
            font-size: 7px !important;
        }

        body.tp-split-view .tp-timer-p {
            min-width: 48px !important;
            padding: 2px 6px !important;
            gap: 4px !important;
        }

        body.tp-split-view .tp-timer-num {
            font-size: 14px !important;
        }

        body.tp-split-view .tp-dealer-section {
            display: flex !important;
            min-height: 30px !important;
            height: 30px !important;
            margin: 0 !important;
            padding: 0 !important;
            align-items: center !important;
            overflow: hidden !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        body.tp-split-view .tp-dealer-section::before {
            display: block !important;
            width: 112px !important;
            height: 18px !important;
            bottom: 5px !important;
            border-radius: 9px !important;
        }

        body.tp-split-view .tp-dealer-avatar {
            display: flex !important;
            width: 28px !important;
            height: 30px !important;
        }

        body.tp-split-view .tp-dealer-avatar-body {
            width: 24px !important;
            height: 30px !important;
        }

        body.tp-split-view .dealer-glow-ring,
        body.tp-split-view .tp-dealer-name,
        body.tp-split-view .tp-dealer-status,
        body.tp-split-view .tp-shuffle-deck {
            display: none !important;
        }

        body.tp-split-view .tp-play-area {
            min-height: 88px !important;
            padding: 1px 3px 0 !important;
        }

        body.tp-split-view .tp-columns {
            height: 100% !important;
            gap: 2px !important;
        }

        body.tp-split-view .tp-col {
            height: 100% !important;
            gap: 1px !important;
            padding: 2px 1px !important;
            border-radius: 8px !important;
            border-width: 1px !important;
        }

        body.tp-split-view .tp-char-frame {
            width: clamp(20px, 30%, 28px) !important;
            height: clamp(20px, 30%, 28px) !important;
        }

        body.tp-split-view .tp-char-label {
            width: 12px !important;
            height: 12px !important;
            font-size: 6px !important;
        }

        body.tp-split-view .tp-side-name {
            font-size: 6px !important;
            line-height: 1 !important;
            margin: 0 !important;
        }

        body.tp-split-view .tp-cards-row {
            min-height: 18px !important;
            gap: 1px !important;
            margin: 0 !important;
        }

        body.tp-split-view .tp-card-container,
        body.tp-split-view .tp-card-img {
            width: clamp(10px, 28%, 14px) !important;
        }

        body.tp-split-view .tp-hand-rank {
            min-height: 11px !important;
            margin: 0 !important;
            padding: 0 3px !important;
            font-size: 5px !important;
            line-height: 11px !important;
        }

        body.tp-split-view .tp-bet-slot {
            min-height: 28px !important;
            border-radius: 6px !important;
        }

        body.tp-split-view .tp-chips-pile {
            inset: 1px 1px 17px 1px !important;
        }

        body.tp-split-view .tp-bet-info {
            left: 1px !important;
            right: 1px !important;
            bottom: 1px !important;
            padding: 0 1px !important;
            font-size: 5px !important;
            line-height: 1.05 !important;
            border-radius: 4px !important;
        }

        body.tp-split-view .tp-history-bar {
            min-height: 20px !important;
            margin: 1px 4px !important;
            padding: 2px 4px !important;
            gap: 3px !important;
            border-radius: 8px !important;
        }

        body.tp-split-view .tp-hist-label {
            font-size: 7px !important;
        }

        body.tp-split-view .tp-hist-items {
            gap: 2px !important;
        }

        body.tp-split-view .tp-hist-dot {
            width: 10px !important;
            height: 14px !important;
            flex-basis: 10px !important;
            font-size: 7px !important;
        }

        body.tp-split-view .tp-footer {
            margin: 0 4px 2px !important;
            padding: 2px 4px calc(2px + env(safe-area-inset-bottom)) !important;
            border-radius: 9px 9px 0 0 !important;
        }

        body.tp-split-view .tp-chips-row {
            gap: 2px !important;
            margin-bottom: 2px !important;
        }

        body.tp-split-view .tp-chip-btn {
            width: 27px !important;
            height: 27px !important;
            font-size: 8px !important;
            border-width: 1px !important;
            box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.18), 0 4px 8px rgba(63, 92, 122, 0.18) !important;
        }

        body.tp-split-view .tp-chip-btn.selected {
            transform: translateY(-1px) scale(1.04) !important;
        }

        body.tp-split-view .tp-bottom-actions {
            grid-template-columns: minmax(0, 1fr) 42px 58px !important;
            gap: 3px !important;
        }

        body.tp-split-view .tp-bal-p,
        body.tp-split-view .tp-win-p,
        body.tp-split-view .tp-btn-repeat,
        body.tp-split-view .tp-btn-topup {
            height: 23px !important;
            border-radius: 8px !important;
        }

        body.tp-split-view .tp-bal-p {
            padding: 0 5px !important;
        }

        body.tp-split-view .tp-bal-val {
            font-size: 10px !important;
        }

        body.tp-split-view .tp-win-label {
            font-size: 6px !important;
        }

        body.tp-split-view .tp-win-val {
            font-size: 12px !important;
        }

        body.tp-split-view .tp-btn-repeat,
        body.tp-split-view .tp-btn-topup {
            padding: 0 4px !important;
            font-size: 9px !important;
            line-height: 1 !important;
        }

        .tp-wv-topbar {
            display: none !important;
        }
    </style>
</head>
<body>

{{-- Loading overlay (hidden after first sync) --}}
<div id="tpWvLoader"><div class="wv-spinner"></div></div>

<div class="tp-wv-root">
    <div class="tp-game-wrapper" id="tpGameWrapper">
        <div class="tp-stage-shell" id="tpStageShell">
            <div class="tp-stage" id="tpGameStage">

                {{-- Top bar: player name + balance --}}
                <div class="tp-wv-topbar">
                    <span class="player-name">{{ $user->firstname }}</span>
                    <span>Balance: <strong class="player-bal bal">{{ $balance }}</strong></span>
                </div>

                {{-- Header --}}
                <div class="tp-header">
                    {{-- Close button posts a JS message to Android so the app can close WebView --}}
                    <button class="tp-btn-icon" onclick="closeWebView()"><i class="fas fa-times"></i></button>
                    <div class="tp-logo">
                        <div class="tp-logo-icon"><i class="fas fa-crown"></i></div>
                        <div class="tp-logo-text">Royal<br>Teen Patti</div>
                    </div>
                    <div class="tp-header-right">
                        <button class="tp-btn-icon audioBtn"><i class="fas fa-volume-up"></i></button>
                        <button class="tp-btn-icon" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>

                {{-- Timer / Phase badge --}}
                <div class="tp-timer-section">
                    <div id="tpPhaseBadge" class="tp-phase-badge phase-betting">Place Your Bets</div>
                    <div class="tp-timer-p">
                        <div class="tp-timer-clock"><i class="far fa-clock"></i></div>
                        <div class="tp-timer-num" id="tpTimer">20</div>
                    </div>
                </div>

                {{-- Fantasy Dealer Lady --}}
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

                {{-- Narration bar --}}
                <div class="tp-narration-bar" id="tpNarrationBar">
                    <div class="tp-narration-icon"><i class="fas fa-comment-dots"></i></div>
                    <div class="tp-narration-text" id="tpNarrationText"></div>
                </div>

                {{-- Betting columns: Silver / Gold / Diamond --}}
                <div class="tp-play-area">
                    <div class="tp-columns">

                        <div class="tp-col" id="tpColSilver" data-choose="silver">
                            <div class="tp-char-frame silver-border">
                                <div class="tp-char-label">S</div>
                                <img src="{{ asset('assets/templates/parimatch/images/silver_character.png') }}" alt="Silver">
                            </div>
                            <div class="tp-side-name">Silver</div>
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
                            <div class="tp-side-name">Gold</div>
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
                            <div class="tp-side-name">Diamond</div>
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

                {{-- History bar --}}
            <div class="tp-history-bar">
                <span class="tp-hist-label">Result</span>
                <div class="tp-hist-items" id="tpHistory"></div>
            </div>

                {{-- Chip selector + balance/win/repeat --}}
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
                            <div class="tp-bal-val bal">{{ $balance }}</div>
                        </div>
                        <div class="tp-win-p">
                            <span class="tp-win-label">WIN</span>
                            <span class="tp-win-val" id="tpWinVal">0</span>
                        </div>
                        <button
                            class="tp-btn-topup"
                            id="tpBtnAddBalance"
                            type="button"
                            {{ empty($walletTopupUrl) ? 'disabled' : '' }}
                        >
                            Top Up
                        </button>
                        <button class="tp-btn-repeat" id="tpBtnRepeat">Repeat</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- History slide panel --}}
        <div id="tpHistBackdrop" class="tp-hist-panel-backdrop"></div>
        <div id="tpHistPanel" class="tp-hist-panel">
            <div class="tp-hist-panel-header">
                <div class="tp-hist-panel-title">Round History</div>
                <button id="tpHistClose" class="tp-hist-panel-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="tpHistBody" class="tp-hist-panel-body">
                <div class="tp-hist-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>

        {{-- Stop-betting overlay --}}
        <div class="tp-overlay" id="tpStopOverlay">
            <img src="{{ asset('assets/templates/parimatch/images/dealer_avatar_body.png') }}" class="tp-stop-char tp-stop-avatar" alt="Betting Stopped Avatar">
            <div class="tp-stop-sign"><span>Stop Betting!</span></div>
        </div>

        {{-- Winner modal --}}
        <div class="tp-winner-modal" id="tpWinnerModal">
            <div class="tp-avatar-pop">
                <div class="tp-avatar-pop-frame">
                    <img src="" id="tpWinnerImg" alt="winner">
                </div>
                <div class="tp-avatar-pop-sign">
                    <div class="tp-pop-round" id="tpWinnerRoundTitle">Round Result</div>
                    <div class="tp-pop-message" id="tpWinnerStatusMsg">Winner Popup</div>
                </div>
            </div>
        </div>

    </div>{{-- /.tp-game-wrapper --}}
</div>{{-- /.tp-wv-root --}}

{{-- Hidden form for CSRF token (jQuery reads from meta tag, not form) --}}
<form id="game" method="post" style="display:none">@csrf</form>

{{-- Scripts --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- Set up CSRF + base URL before loading game JS --}}
<script>
"use strict";

// CSRF: send with every AJAX request
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest'
    }
});

// Game config variables (read by teenPatti.js)
var imagePath        = "{{ asset(activeTemplate(true) . 'images/cards/') }}";
var avatarPath       = "{{ asset('assets/templates/parimatch/images/') }}";
var cardBackImage    = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}";
var investUrl        = "{{ $investUrl }}";
var syncUrl          = "{{ $syncUrl }}";
var gameEndUrl       = "{{ $gameEndUrl }}";
var historyUrl       = "{{ $historyUrl ?? '' }}";
var tpAudioAssetPath = "{{ asset('assets/audio') }}";
var currentUserId    = "{{ auth()->id() }}";
var walletTopupUrl   = @json($walletTopupUrl ?? '');
var walletRefreshUrl = @json($walletRefreshUrl ?? '');
var walletContext    = @json($walletContext ?? (object) []);
var tpChipValues     = @json(array_values($teenPattiChipValues ?? \App\Models\Tenant::DEFAULT_TEEN_PATTI_CHIPS));

// Hide loader after first sync
var tpWvLoaderHidden = false;
var walletRefreshInFlight = false;
var walletRefreshBurst = null;
function tpWvHideLoader() {
    if (!tpWvLoaderHidden) {
        tpWvLoaderHidden = true;
        var el = document.getElementById('tpWvLoader');
        if (el) {
            el.classList.add('hidden');
            setTimeout(function() { el.style.display = 'none'; }, 450);
        }
    }
}

function tpFitStageToViewport() {
    var wrapper = document.getElementById('tpGameWrapper');
    var stageShell = document.getElementById('tpStageShell');
    var stage = document.getElementById('tpGameStage');

    if (!wrapper || !stageShell || !stage) {
        return;
    }

    stage.style.transform = 'scale(1)';
    stage.style.minHeight = '0';
    stage.style.height = 'auto';
    stageShell.style.height = 'auto';

    var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    var wrapperHeight = wrapper.clientHeight || viewportHeight;
    var availableHeight = Math.max(0, Math.min(viewportHeight, wrapperHeight));

    var naturalHeight = Math.ceil(stage.scrollHeight || stage.offsetHeight || 0);

    if (!naturalHeight || !availableHeight) {
        return;
    }

    if (naturalHeight < availableHeight) {
        stageShell.style.height = Math.ceil(availableHeight) + 'px';
        stage.style.minHeight = Math.ceil(availableHeight) + 'px';
        stage.style.height = Math.ceil(availableHeight) + 'px';
        return;
    }

    var scale = Math.min(1, availableHeight / naturalHeight);
    stage.style.transform = 'scale(' + scale + ')';
    stageShell.style.height = Math.ceil(naturalHeight * scale) + 'px';
}

function tpApplySplitViewMode() {
    var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    var splitThreshold = 430;

    if (viewportHeight > 0) {
        document.documentElement.style.setProperty('--tp-wv-height', Math.ceil(viewportHeight) + 'px');
    }

    if (viewportHeight > 0 && viewportHeight <= splitThreshold) {
        document.body.classList.add('tp-split-view');
    } else {
        document.body.classList.remove('tp-split-view');
    }
}

function tpRefreshWebViewLayout() {
    tpApplySplitViewMode();
    tpFitStageToViewport();
}

function buildWalletTopupUrl() {
    if (!walletTopupUrl) {
        return '';
    }

    try {
        var targetUrl = new URL(walletTopupUrl, window.location.origin);
        var ctx = walletContext || {};

        if (ctx.playerId) targetUrl.searchParams.set('player_id', ctx.playerId);
        if (ctx.playerName) targetUrl.searchParams.set('player_name', ctx.playerName);
        if (ctx.sessionToken) targetUrl.searchParams.set('session_token', ctx.sessionToken);
        if (ctx.gameId) targetUrl.searchParams.set('game_id', ctx.gameId);
        if (ctx.currency) targetUrl.searchParams.set('currency', ctx.currency);
        if (ctx.tenantId) targetUrl.searchParams.set('tenant_id', ctx.tenantId);

        targetUrl.searchParams.set('source', 'game_webview');
        targetUrl.searchParams.set('return_url', window.location.href);

        return targetUrl.toString();
    } catch (error) {
        return walletTopupUrl;
    }
}

function stopWalletRefreshBurst() {
    if (walletRefreshBurst) {
        window.clearInterval(walletRefreshBurst);
        walletRefreshBurst = null;
    }
}

function startWalletRefreshBurst() {
    if (!walletRefreshUrl) {
        return;
    }

    stopWalletRefreshBurst();
    refreshTenantWalletBalance();

    var attempts = 0;
    walletRefreshBurst = window.setInterval(function () {
        attempts += 1;
        refreshTenantWalletBalance();

        if (attempts >= 8) {
            stopWalletRefreshBurst();
        }
    }, 2500);
}

// Android WebView bridge: allow app to close the WebView
function closeWebView() {
    // If the Android app has injected a JS interface named "AndroidBridge",
    // call its close() method. Otherwise just call window.close().
    if (window.AndroidBridge && typeof window.AndroidBridge.close === 'function') {
        window.AndroidBridge.close();
    } else if (window.history.length > 1) {
        window.history.back();
    } else {
        window.close();
    }
}

function openWalletTopup() {
    var targetUrl = buildWalletTopupUrl();

    if (!targetUrl) {
        if (typeof tpNotify === 'function') {
            tpNotify('error', 'Add balance link is not configured');
        }
        return;
    }

    startWalletRefreshBurst();

    if (window.AndroidBridge && typeof window.AndroidBridge.openExternal === 'function') {
        window.AndroidBridge.openExternal(targetUrl);
        return;
    }

    window.location.href = targetUrl;
}

function refreshTenantWalletBalance() {
    if (!walletRefreshUrl || walletRefreshInFlight) {
        return;
    }

    walletRefreshInFlight = true;

    $.ajax({
        url: walletRefreshUrl,
        type: 'GET',
        cache: false,
        timeout: 5000,
        success: function (data) {
            if (data && typeof data.balance !== 'undefined') {
                if (typeof updateBalanceDisplay === 'function') {
                    updateBalanceDisplay(data.balance);
                } else {
                    $('.bal').text(data.balance);
                }
            }
        },
        complete: function () {
            walletRefreshInFlight = false;
        }
    });
}

// Balance refresh helper (called by teenPatti.js after win/loss)
// The existing game JS updates .bal elements; this also syncs the topbar.
$(document).ready(function () {
    tpRefreshWebViewLayout();

    // Observe .bal changes so the top-bar balance stays in sync
    var observer = new MutationObserver(function () {
        var val = $('.tp-bal-val.bal').first().text();
        $('.player-bal.bal').text(val);
    });
    $('.tp-bal-val.bal').each(function () {
        observer.observe(this, { childList: true, subtree: true, characterData: true });
    });

    // Hide the loader once the game JS calls updateUI for the first time.
    // We hook into the existing syncGlobalState success path by wrapping $.ajax.
    var _origAjax = $.ajax;
    $.ajax = function (opts) {
        var origSuccess = opts.success;
        opts.success = function (data) {
            tpWvHideLoader();
            $.ajax = _origAjax; // unwrap after first call
            if (origSuccess) origSuccess.apply(this, arguments);
        };
        return _origAjax.apply(this, arguments);
    };

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            startWalletRefreshBurst();
        }
    });

    window.addEventListener('load', tpRefreshWebViewLayout);
    window.addEventListener('focus', function () {
        startWalletRefreshBurst();
        tpRefreshWebViewLayout();
    });
    window.addEventListener('resize', tpRefreshWebViewLayout);
    window.addEventListener('orientationchange', tpRefreshWebViewLayout);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', tpRefreshWebViewLayout);
    }

    window.setTimeout(tpRefreshWebViewLayout, 120);
    window.setTimeout(tpRefreshWebViewLayout, 450);

    if (document.fonts && typeof document.fonts.ready === 'object') {
        document.fonts.ready.then(tpRefreshWebViewLayout);
    }
});
</script>

{{-- Sound control (must come before teenPatti.js) --}}
<script src="{{ asset('assets/global/js/soundControl.js') }}"></script>

{{-- Main game logic --}}
@php $tpJsV = @filemtime(public_path('assets/global/js/game/teenPatti.js')) ?: time(); @endphp
<script src="{{ asset('assets/global/js/game/teenPatti.js') }}?v={{ $tpJsV }}"></script>

</body>
</html>
