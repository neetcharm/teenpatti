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
        /* ============================================================
           ROYAL TEEN PATTI — FLUID WEBVIEW LAYOUT v3
           Pure CSS, zero JS scaling. Works on any screen size and
           any aspect ratio. No wasted space. No hidden buttons.
           ============================================================ */

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            width: 100%; height: 100dvh;
            background: #0a0a1a;
            overflow: hidden;
            overscroll-behavior: none;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            user-select: none;
        }

        /* ----- Loader ----- */
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

        /* ----- Root + Wrapper: occupy full viewport, no scaling ----- */
        .tp-wv-root {
            width: 100vw;
            height: 100dvh;
            min-height: 100dvh;
            display: flex;
            align-items: stretch;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .tp-wv-root .tp-game-wrapper {
            width: 100%;
            height: 100dvh;
            min-height: 100dvh;
            max-height: 100dvh;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* The shell holds the stage and lets it stretch fully */
        .tp-stage-shell {
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        /* ============================================================
           THE STAGE — proportional grid using dynamic viewport units.
           Rows scale automatically with the available height, so no
           gaps, no overflow, on ANY screen height between 360px-1200px.
           ============================================================ */
        .tp-stage {
            position: relative;
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-rows:
                clamp(40px, 7dvh, 56px)              /* header */
                clamp(34px, 5.5dvh, 44px)            /* timer */
                minmax(0, 1fr)                       /* play area */
                clamp(22px, 3.5dvh, 30px)            /* history */
                clamp(118px, 22dvh, 170px);          /* footer */
            grid-template-columns: 100%;
            gap: clamp(2px, 0.6dvh, 6px);
            padding: clamp(3px, 0.8dvh, 8px) clamp(3px, 0.8vw, 8px);
            transform: none !important;     /* no JS scaling */
            min-height: 0;
            overflow: hidden;
        }

        /* All direct grid children must respect their grid track */
        .tp-stage > * {
            min-width: 0;
            min-height: 0;
            overflow: hidden;
        }

        /* ============================================================
           DEALER + NARRATION: completely removed (zero footprint)
           ============================================================ */
        .tp-dealer-section,
        .tp-dealer-avatar,
        .tp-dealer-avatar-body,
        .tp-dealer-hand-layer,
        .tp-dealer-hand-right,
        .tp-dealer-name,
        .tp-dealer-status,
        .tp-dealer-avatar-img,
        .dealer-glow-ring,
        .tp-narration-bar,
        body .tp-dealer-section,
        body .tp-dealer-avatar,
        body .tp-dealer-name,
        body.tp-split-view .tp-dealer-section,
        body.tp-split-view .tp-dealer-avatar,
        body.tp-split-view .tp-dealer-name,
        .tp-game-wrapper .tp-dealer-section,
        .tp-game-wrapper .tp-dealer-name,
        .tp-stage .tp-dealer-section,
        .tp-stage .tp-dealer-name,
        .tp-stage .tp-dealer-avatar {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            max-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            opacity: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            position: absolute !important;
            left: -9999px !important;
        }

        /* Invisible deal anchor (origin for flying card animation) */
        .tp-deal-anchor {
            position: absolute;
            top: 18%;
            left: 50%;
            transform: translateX(-50%);
            width: 1px;
            height: 1px;
            pointer-events: none;
            opacity: 0;
            z-index: 0;
        }
        .tp-deal-anchor .tp-shuffle-deck {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        /* ============================================================
           HEADER (row 1)
           ============================================================ */
        .tp-stage > .tp-header {
            min-height: 0 !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 4px clamp(6px, 2vw, 12px) !important;
            border-radius: clamp(8px, 1.4vw, 14px) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 6px !important;
        }
        .tp-stage .tp-btn-icon {
            width: clamp(26px, 5vw, 34px) !important;
            height: clamp(26px, 5vw, 34px) !important;
            font-size: clamp(11px, 2.6vw, 14px) !important;
        }
        .tp-stage .tp-logo { gap: 6px !important; }
        .tp-stage .tp-logo-icon {
            width: clamp(24px, 5vw, 32px) !important;
            height: clamp(24px, 5vw, 32px) !important;
            font-size: clamp(14px, 3.5vw, 20px) !important;
        }
        .tp-stage .tp-logo-text {
            font-size: clamp(10px, 2.6vw, 14px) !important;
            line-height: 1 !important;
        }
        .tp-stage .tp-header-right { gap: 4px !important; }

        /* ============================================================
           TIMER / PHASE BADGE (row 2)
           ============================================================ */
        .tp-stage > .tp-timer-section {
            min-height: 0 !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 3px clamp(6px, 2vw, 12px) !important;
            border-radius: clamp(8px, 1.4vw, 14px) !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .tp-stage .tp-phase-badge {
            min-width: clamp(110px, 36%, 200px) !important;
            padding: 4px 8px !important;
            font-size: clamp(8px, 2.2vw, 11px) !important;
        }
        .tp-stage .tp-timer-p {
            min-width: clamp(48px, 14%, 68px) !important;
            padding: 3px 8px !important;
            gap: 4px !important;
        }
        .tp-stage .tp-timer-num {
            font-size: clamp(13px, 3.8vw, 18px) !important;
        }

        /* ============================================================
           PLAY AREA (row 3) — 3 equal columns, fluid card sizing
           ============================================================ */
        .tp-stage > .tp-play-area {
            min-height: 0 !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            align-items: stretch !important;
            justify-content: stretch !important;
        }
        .tp-stage .tp-columns {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: clamp(3px, 1vw, 8px) !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
        }
        .tp-stage .tp-col {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            min-width: 0 !important;
            min-height: 0 !important;
            height: 100% !important;
            padding: clamp(3px, 1vw, 8px) clamp(2px, 0.8vw, 6px) !important;
            gap: clamp(2px, 0.6dvh, 5px) !important;
            border-radius: clamp(8px, 1.4vw, 14px) !important;
            border-width: 1.5px !important;
            overflow: hidden !important;
        }
        .tp-stage .tp-char-frame {
            width: clamp(34px, 9vw, 60px) !important;
            height: clamp(34px, 9vw, 60px) !important;
            flex: 0 0 auto !important;
        }
        .tp-stage .tp-char-label {
            width: clamp(14px, 3.5vw, 18px) !important;
            height: clamp(14px, 3.5vw, 18px) !important;
            font-size: clamp(7px, 1.8vw, 9px) !important;
        }
        .tp-stage .tp-side-name {
            font-size: clamp(8px, 2vw, 11px) !important;
            line-height: 1 !important;
            margin: 1px 0 !important;
            flex: 0 0 auto !important;
        }
        .tp-stage .tp-cards-row {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: clamp(1px, 0.4vw, 3px) !important;
            margin: 1px 0 !important;
            flex-wrap: nowrap !important;
            overflow: hidden !important;
            min-height: 0 !important;
            flex: 0 0 auto !important;
            width: 100% !important;
        }
        .tp-stage .tp-card-container,
        .tp-stage .tp-card-img,
        .tp-stage .tp-card-inner {
            width: clamp(20px, 7vw, 36px) !important;
            aspect-ratio: 5 / 7 !important;
            height: auto !important;
            flex: 0 1 clamp(20px, 7vw, 36px) !important;
            max-width: 32% !important;
        }
        .tp-stage .tp-hand-rank {
            min-height: 14px !important;
            padding: 1px 4px !important;
            margin: 0 !important;
            font-size: clamp(7px, 1.8vw, 10px) !important;
            line-height: 1.2 !important;
            flex: 0 0 auto !important;
        }
        .tp-stage .tp-bet-slot {
            flex: 1 1 auto !important;
            width: 100% !important;
            min-height: 32px !important;
            border-radius: clamp(5px, 1vw, 8px) !important;
            position: relative !important;
        }
        .tp-stage .tp-chips-pile {
            inset: 2px 2px clamp(16px, 3.5dvh, 22px) 2px !important;
        }
        .tp-stage .tp-bet-info {
            position: absolute !important;
            left: 2px !important;
            right: 2px !important;
            bottom: 2px !important;
            padding: 1px 3px !important;
            font-size: clamp(7px, 1.7vw, 9px) !important;
            line-height: 1.15 !important;
            border-radius: 4px !important;
        }

        /* ============================================================
           HISTORY (row 4)
           ============================================================ */
        .tp-stage > .tp-history-bar {
            min-height: 0 !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 2px clamp(6px, 2vw, 12px) !important;
            border-radius: clamp(8px, 1.4vw, 14px) !important;
            display: flex !important;
            align-items: center !important;
            gap: clamp(3px, 0.8vw, 6px) !important;
            overflow: hidden !important;
        }
        .tp-stage .tp-hist-label {
            font-size: clamp(8px, 2vw, 11px) !important;
            flex: 0 0 auto !important;
        }
        .tp-stage .tp-hist-items {
            gap: clamp(2px, 0.6vw, 4px) !important;
            min-width: 0 !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
            flex: 1 1 auto !important;
            display: flex !important;
            align-items: center !important;
        }
        .tp-stage .tp-hist-items::-webkit-scrollbar { display: none; }
        .tp-stage .tp-hist-dot {
            width: clamp(14px, 3vw, 18px) !important;
            height: clamp(14px, 3vw, 18px) !important;
            flex: 0 0 auto !important;
            font-size: clamp(8px, 2vw, 10px) !important;
        }

        /* ============================================================
           FOOTER (row 5) — chips + bottom actions stacked
           ============================================================ */
        .tp-stage > .tp-footer {
            min-height: 0 !important;
            height: 100% !important;
            margin: 0 !important;
            padding: clamp(4px, 1dvh, 8px) clamp(5px, 1.5vw, 10px) calc(clamp(4px, 1dvh, 8px) + env(safe-area-inset-bottom)) !important;
            border-radius: clamp(10px, 2vw, 16px) clamp(10px, 2vw, 16px) 0 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: clamp(3px, 0.8dvh, 6px) !important;
            overflow: hidden !important;
        }
        .tp-stage .tp-chips-row {
            display: flex !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: clamp(3px, 1vw, 6px) !important;
            width: 100% !important;
            flex: 0 1 auto !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .tp-stage .tp-chips-row::-webkit-scrollbar { display: none; }
        .tp-stage .tp-chip-btn {
            flex: 1 1 0 !important;
            width: clamp(34px, 9.5vw, 52px) !important;
            height: clamp(34px, 9.5vw, 52px) !important;
            min-width: clamp(34px, 9.5vw, 52px) !important;
            font-size: clamp(9px, 2.4vw, 13px) !important;
            border-width: 2px !important;
        }
        .tp-stage .tp-bottom-actions {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto auto auto !important;
            gap: clamp(4px, 1.4vw, 8px) !important;
            align-items: center !important;
            width: 100% !important;
            flex: 0 0 auto !important;
        }
        .tp-stage .tp-bal-p {
            min-width: 0 !important;
            height: clamp(28px, 5dvh, 36px) !important;
            padding: 0 clamp(6px, 2vw, 10px) !important;
            border-radius: clamp(8px, 1.4vw, 12px) !important;
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
        }
        .tp-stage .tp-bal-icon {
            font-size: clamp(11px, 2.4vw, 14px) !important;
        }
        .tp-stage .tp-bal-val {
            font-size: clamp(10px, 2.6vw, 13px) !important;
            min-width: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }
        .tp-stage .tp-win-p {
            min-width: clamp(40px, 11vw, 60px) !important;
            height: clamp(28px, 5dvh, 36px) !important;
            padding: 1px clamp(4px, 1.5vw, 8px) !important;
            border-radius: clamp(8px, 1.4vw, 12px) !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
        }
        .tp-stage .tp-win-label {
            font-size: clamp(7px, 1.6vw, 9px) !important;
            line-height: 1 !important;
        }
        .tp-stage .tp-win-val {
            font-size: clamp(11px, 2.8vw, 14px) !important;
            line-height: 1.1 !important;
        }
        .tp-stage .tp-btn-topup {
            height: clamp(28px, 5dvh, 36px) !important;
            min-width: clamp(46px, 12vw, 64px) !important;
            padding: 0 clamp(6px, 1.6vw, 10px) !important;
            font-size: clamp(9px, 2.2vw, 12px) !important;
            border-radius: clamp(8px, 1.4vw, 12px) !important;
            line-height: 1 !important;
        }
        .tp-stage .tp-btn-repeat.tp-btn-repeat--icon {
            width: clamp(28px, 5dvh, 36px) !important;
            height: clamp(28px, 5dvh, 36px) !important;
            min-width: clamp(28px, 5dvh, 36px) !important;
            padding: 0 !important;
            font-size: clamp(12px, 3vw, 15px) !important;
            border-radius: 50% !important;
        }

        /* ============================================================
           Hide legacy site chrome / topbar
           ============================================================ */
        .tp-wv-topbar { display: none !important; }
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

                {{-- Invisible deal anchor (origin for flying card animation) --}}
                <div class="tp-deal-anchor" id="tpDealerAvatar" aria-hidden="true">
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
                        <button class="tp-btn-repeat tp-btn-repeat--icon" id="tpBtnRepeat" type="button" title="Repeat last bet" aria-label="Repeat last bet">
                            <i class="fas fa-redo-alt" aria-hidden="true"></i>
                        </button>
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

        {{-- Win celebration modal --}}
        <div class="tp-win-modal" id="tpWinnerModal" aria-hidden="true">
            <div class="tp-win-modal__backdrop"></div>
            <div class="tp-win-modal__confetti" id="tpWinConfetti" aria-hidden="true"></div>
            <div class="tp-win-modal__card" role="dialog" aria-live="polite">
                <div class="tp-win-modal__rays" aria-hidden="true"></div>
                <div class="tp-win-modal__ring" aria-hidden="true"></div>
                <div class="tp-win-modal__crown" aria-hidden="true"><i class="fas fa-crown"></i></div>
                <div class="tp-win-modal__title" id="tpWinnerRoundTitle">You Win</div>
                <div class="tp-win-modal__amount" id="tpWinAmount">
                    <span class="tp-win-modal__currency">+</span>
                    <span class="tp-win-modal__amount-value" id="tpWinAmountValue">0</span>
                </div>
                <div class="tp-win-modal__message" id="tpWinnerStatusMsg">Congratulations!</div>
                <img src="" id="tpWinnerImg" alt="" class="tp-win-modal__avatar" aria-hidden="true">
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
var tpCardDeckPaths  = [
    "{{ asset(activeTemplate(true) . 'images/cards/') }}",
    "{{ asset('assets/templates/parimatch/images/cards/') }}",
    "{{ asset('assets/templates/sunfyre/images/cards/') }}",
    "{{ asset('assets/templates/basic/images/cards/') }}"
];
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
var walletRefreshLastAt = 0;
var walletRefreshBurstLastAt = 0;
var walletRefreshRetryAfter = 0;
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
    /* Pure CSS handles all sizing. No JS scaling. */
    var stage = document.getElementById('tpGameStage');
    var stageShell = document.getElementById('tpStageShell');
    if (stage) {
        stage.style.transform = '';
        stage.style.minHeight = '';
        stage.style.height = '';
    }
    if (stageShell) {
        stageShell.style.height = '';
    }
}

function tpApplySplitViewMode() {
    /* Pure CSS handles all responsive sizing via dvh + clamp().
       Just expose the viewport height as a CSS var for legacy code. */
    var viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
    if (viewportHeight > 0) {
        document.documentElement.style.setProperty('--tp-wv-height', Math.ceil(viewportHeight) + 'px');
    }
    /* Always remove the legacy split-view class — the new layout
       does not depend on it and keeping it would re-introduce
       the dealer/positioning hacks. */
    document.body.classList.remove('tp-split-view');
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

    var now = Date.now();
    if (walletRefreshBurst || (walletRefreshBurstLastAt > 0 && now - walletRefreshBurstLastAt < 15000)) {
        refreshTenantWalletBalance({ minInterval: 5000 });
        return;
    }

    walletRefreshBurstLastAt = now;
    stopWalletRefreshBurst();
    refreshTenantWalletBalance({ force: true, minInterval: 0 });

    var attempts = 0;
    walletRefreshBurst = window.setInterval(function () {
        attempts += 1;
        refreshTenantWalletBalance({ force: true, minInterval: 4500 });

        if (attempts >= 4) {
            stopWalletRefreshBurst();
        }
    }, 5000);
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

function refreshTenantWalletBalance(options) {
    if (!walletRefreshUrl || walletRefreshInFlight) {
        return;
    }

    var opts = options || {};
    var now = Date.now();
    var minInterval = typeof opts.minInterval === 'number' ? opts.minInterval : 5000;

    if (now < walletRefreshRetryAfter) {
        return;
    }

    if (walletRefreshLastAt > 0 && now - walletRefreshLastAt < minInterval) {
        return;
    }

    walletRefreshInFlight = true;
    walletRefreshLastAt = now;

    var refreshTarget = walletRefreshUrl;
    if (opts.force) {
        try {
            var targetUrl = new URL(walletRefreshUrl, window.location.origin);
            targetUrl.searchParams.set('force', '1');
            refreshTarget = targetUrl.toString();
        } catch (error) {
            refreshTarget = walletRefreshUrl + (walletRefreshUrl.indexOf('?') === -1 ? '?' : '&') + 'force=1';
        }
    }

    $.ajax({
        url: refreshTarget,
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
        error: function (xhr) {
            if (xhr && xhr.status === 429) {
                var retryAfter = parseInt(xhr.getResponseHeader('Retry-After') || '30', 10);
                walletRefreshRetryAfter = Date.now() + (Math.max(10, retryAfter) * 1000);
                stopWalletRefreshBurst();
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
