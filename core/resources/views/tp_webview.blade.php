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
            min-height: 100dvh;
            height: 100dvh;
            padding: 0;
            overflow: hidden;
        }

        .tp-wv-root .tp-game-wrapper {
            width: 100%;
            max-width: 480px;
            min-height: 100dvh;
            height: 100dvh;
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
            align-items: flex-start;
        }

        .tp-stage {
            width: 100%;
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
            max-width: 480px;
            height: 100dvh;
            min-height: 0;
        }

        .tp-stage {
            min-width: 0;
            overflow: hidden;
            display: grid;
            grid-template-rows: auto auto minmax(88px, .44fr) minmax(212px, 1fr) auto auto;
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
                    <button class="tp-btn-chart"><i class="fas fa-chart-line"></i></button>
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
    stageShell.style.height = 'auto';

    var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    var wrapperHeight = wrapper.clientHeight || viewportHeight;
    var availableHeight = Math.max(0, Math.min(viewportHeight, wrapperHeight));

    var naturalHeight = Math.ceil(stage.scrollHeight || stage.offsetHeight || 0);

    if (!naturalHeight || !availableHeight) {
        return;
    }

    var scale = Math.min(1, availableHeight / naturalHeight);
    stage.style.transform = 'scale(' + scale + ')';
    stageShell.style.height = Math.ceil(naturalHeight * scale) + 'px';
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
    tpFitStageToViewport();

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

    window.addEventListener('load', tpFitStageToViewport);
    window.addEventListener('focus', startWalletRefreshBurst);
    window.addEventListener('resize', tpFitStageToViewport);
    window.addEventListener('orientationchange', tpFitStageToViewport);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', tpFitStageToViewport);
    }

    window.setTimeout(tpFitStageToViewport, 150);
    window.setTimeout(tpFitStageToViewport, 450);

    if (document.fonts && typeof document.fonts.ready === 'object') {
        document.fonts.ready.then(tpFitStageToViewport);
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
