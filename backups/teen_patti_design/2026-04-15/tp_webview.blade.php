<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a1a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Royal Teen Patti</title>

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- Teen Patti CSS --}}
    @php $tpCssV = @filemtime(public_path('assets/global/css/game/teen-patti.css')) ?: time(); @endphp
    <link rel="stylesheet" href="{{ asset('assets/global/css/game/teen-patti.css') }}?v={{ $tpCssV }}">

    <style>
        /* ── WebView reset – no site chrome ──────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            width: 100%; height: 100%;
            background: #0a0a1a;
            overflow-x: hidden;
            /* Disable pull-to-refresh on Android WebView */
            overscroll-behavior: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            user-select: none;
        }

        /* Full-height single-column layout */
        .tp-wv-root {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            min-height: 100vh;
            padding: 0;
        }

        .tp-wv-root .tp-game-wrapper {
            width: 100%;
            max-width: 480px;
            min-height: 100vh;
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
    </style>
</head>
<body>

{{-- Loading overlay (hidden after first sync) --}}
<div id="tpWvLoader"><div class="wv-spinner"></div></div>

<div class="tp-wv-root">
    <div class="tp-game-wrapper" id="tpGameWrapper">

        {{-- ── Top bar: player name + balance ── --}}
        <div class="tp-wv-topbar">
            <span class="player-name">{{ $user->firstname }}</span>
            <span>Balance: <strong class="player-bal bal">{{ $balance }}</strong></span>
        </div>

        {{-- ── Header ── --}}
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

        {{-- ── Timer / Phase badge ── --}}
        <div class="tp-timer-section">
            <div id="tpPhaseBadge" class="tp-phase-badge phase-betting">Place Your Bets</div>
            <div class="tp-timer-p">
                <div class="tp-timer-clock"><i class="far fa-clock"></i></div>
                <div class="tp-timer-num" id="tpTimer">20</div>
            </div>
        </div>

        {{-- ── Fantasy Dealer Lady ── --}}
        <div class="tp-dealer-section">
            <div class="tp-dealer-avatar" id="tpDealerAvatar">
                <div class="dealer-lady">
                    <div class="lady-hair">
                        <div class="hair-strand left-strand"></div>
                        <div class="hair-strand right-strand"></div>
                    </div>
                    <div class="lady-tiara"><div class="tiara-gem"></div></div>
                    <div class="lady-face">
                        <div class="lady-eye left-eye"><div class="eye-pupil"></div></div>
                        <div class="lady-eye right-eye"><div class="eye-pupil"></div></div>
                        <div class="lady-bindi"></div>
                        <div class="lady-nose"></div>
                        <div class="lady-lips"></div>
                    </div>
                    <div class="lady-earring left-earring"></div>
                    <div class="lady-earring right-earring"></div>
                    <div class="lady-neck"><div class="lady-necklace"></div></div>
                    <div class="lady-dress">
                        <div class="dress-detail"></div>
                        <div class="lady-dupatta"></div>
                    </div>
                    <div class="lady-hand left-hand"></div>
                    <div class="lady-hand right-hand"></div>
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

        {{-- ── Narration bar ── --}}
        <div class="tp-narration-bar" id="tpNarrationBar">
            <div class="tp-narration-icon"><i class="fas fa-comment-dots"></i></div>
            <div class="tp-narration-text" id="tpNarrationText"></div>
        </div>

        {{-- ── Betting columns: Silver / Gold / Diamond ── --}}
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

        {{-- ── History bar ── --}}
        <div class="tp-history-bar">
            <span class="tp-hist-label">Result</span>
            <div class="tp-hist-items" id="tpHistory"></div>
            <button class="tp-btn-chart"><i class="fas fa-chart-line"></i></button>
        </div>

        {{-- ── Chip selector + balance/win/repeat ── --}}
        <div class="tp-footer">
            <div class="tp-chips-row">
                <div class="tp-chip-btn c-400 selected" data-value="400"><span>400</span></div>
                <div class="tp-chip-btn c-2k" data-value="2000"><span>2K</span></div>
                <div class="tp-chip-btn c-4k" data-value="4000"><span>4K</span></div>
                <div class="tp-chip-btn c-20k" data-value="20000"><span>20K</span></div>
                <div class="tp-chip-btn c-40k" data-value="40000"><span>40K</span></div>
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
                <button class="tp-btn-repeat" id="tpBtnRepeat">Repeat</button>
            </div>
        </div>

        {{-- ── Stop-betting overlay ── --}}
        <div class="tp-overlay" id="tpStopOverlay">
            <img src="{{ asset('assets/templates/parimatch/images/stop_betting.png') }}" class="tp-stop-char" alt="stop">
            <div class="tp-stop-sign"><span>Stop Betting!</span></div>
        </div>

        {{-- ── Winner modal ── --}}
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

        {{-- ── Round summary panel ── --}}
        <div class="tp-round-summary" id="tpRoundSummary">
            <div class="tp-summary-title" id="tpSummaryTitle">Round Summary</div>
            <div class="tp-summary-hands" id="tpSummaryHands"></div>
            <div class="tp-summary-next">
                Next round in <span class="countdown-num" id="tpNextRoundCountdown">7</span>
            </div>
        </div>

    </div>{{-- /.tp-game-wrapper --}}
</div>{{-- /.tp-wv-root --}}

{{-- Hidden form for CSRF token (jQuery reads from meta tag, not form) --}}
<form id="game" method="post" style="display:none">@csrf</form>

{{-- ════════════════════════════════════════════════════════════════
     Scripts
     ════════════════════════════════════════════════════════════════ --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- Set up CSRF + base URL before loading game JS --}}
<script>
"use strict";

// ── CSRF: send with every AJAX request ───────────────────────────────
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        'X-Requested-With': 'XMLHttpRequest'
    }
});

// ── Game config variables (read by teenPatti.js) ──────────────────────
var imagePath        = "{{ asset(activeTemplate(true) . 'images/cards/') }}";
var avatarPath       = "{{ asset('assets/templates/parimatch/images/') }}";
var cardBackImage    = "{{ asset(activeTemplate(true) . 'images/cards/BACK.png') }}";
var investUrl        = "{{ $investUrl }}";
var syncUrl          = "{{ $syncUrl }}";
var gameEndUrl       = "{{ $gameEndUrl }}";
var tpAudioAssetPath = "{{ asset('assets/audio') }}";
var currentUserId    = "{{ auth()->id() }}";

// ── Hide loader after first sync ──────────────────────────────────────
var tpWvLoaderHidden = false;
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

// ── Android WebView bridge: allow app to close the WebView ────────────
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

// ── Balance refresh helper (called by teenPatti.js after win/loss) ────
// The existing game JS updates .bal elements; this also syncs the topbar.
var _origUpdateBal = null;
$(document).ready(function () {
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
});
</script>

{{-- Sound control (must come before teenPatti.js) --}}
<script src="{{ asset('assets/global/js/soundControl.js') }}"></script>

{{-- Main game logic --}}
@php $tpJsV = @filemtime(public_path('assets/global/js/game/teenPatti.js')) ?: time(); @endphp
<script src="{{ asset('assets/global/js/game/teenPatti.js') }}?v={{ $tpJsV }}"></script>

</body>
</html>
