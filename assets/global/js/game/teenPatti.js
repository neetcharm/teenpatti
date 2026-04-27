"use strict";

/* ============================================================
   ROYAL TEEN PATTI - Fantasy Dealer Lady Cinematic Edition
   Compressed animation (~6s) to fit within the 7s server hold
   ============================================================ */

let syncInterval = null;
let localCountdownInterval = null;
let ambientRainInterval = null;

let currentPhase = "betting";
let currentRound = null;
let selectedChip = normalizeChipValues()[0] || 400;
let isBettingEnabled = true;
let resultShownForRound = null;
let countdownSeconds = 20;
let hasSyncedOnce = false;
let lastCoinSoundAt = 0;
let isDealingInProgress = false;
let dealingStartedAt = 0;       // Timestamp when dealing began (for watchdog)
let dealingForRound = null;     // Which round the current animation is for
let animationTimers = [];       // Track all setTimeout IDs for abort
let countdownTargetTime = 0;
let tpAutoLayoutStarted = false;
let tpAutoLayoutTimer = null;
let tpAutoLayoutObserver = null;
let winnerPartyTimer = null;

let lastTotals = { silver: 0, gold: 0, diamond: 0 };
let latestMyBets = { silver: 0, gold: 0, diamond: 0 };
let lastRoundBets = { silver: 0, gold: 0, diamond: 0 };
let lastRoundBetsRound = null;
let lastResultData = null;
let localHistoryMap = {};

function tpLastRoundStorageKey() {
    var uid = (typeof currentUserId !== "undefined" && currentUserId) ? currentUserId : "guest";
    return "tp_last_round_bets_" + uid;
}

function loadLastRoundBetsFromStorage() {
    try {
        var raw = window.localStorage && window.localStorage.getItem(tpLastRoundStorageKey());
        if (!raw) return;
        var data = JSON.parse(raw);
        if (data && typeof data === "object") {
            lastRoundBets = {
                silver: safeAmount(data.silver),
                gold: safeAmount(data.gold),
                diamond: safeAmount(data.diamond)
            };
        }
    } catch (e) { /* ignore */ }
}

function persistLastRoundBets() {
    try {
        if (window.localStorage) {
            window.localStorage.setItem(tpLastRoundStorageKey(), JSON.stringify(lastRoundBets));
        }
    } catch (e) { /* ignore */ }
}

const MAX_VISIBLE_HISTORY = 20;

const SIDES = ["silver", "gold", "diamond"];
const SIDE_LABELS = { silver: "Silver", gold: "Gold", diamond: "Diamond" };
const SIDE_COLORS = { silver: "#c0c0c0", gold: "#e9d01b", diamond: "#b9f2ff" };
const SIDE_HISTORY_CLASS = { silver: "dot-s", gold: "dot-g", diamond: "dot-d" };

let historyHydrationInFlight = false;
let historyHydratedOnce = false;

function normalizeChipValues() {
    var raw = (typeof tpChipValues !== "undefined" && Array.isArray(tpChipValues)) ? tpChipValues : [400, 2000, 4000, 20000, 40000];
    var values = [];
    raw.forEach(function (value) {
        var amount = safeAmount(value);
        if (amount > 0 && values.indexOf(amount) === -1) {
            values.push(amount);
        }
    });
    return values.length ? values : [400, 2000, 4000, 20000, 40000];
}

// Display-only crowd volume (50 lakh to 1.5 crore) for "All" bets.
const CROWD_DISPLAY_MIN = 5000000;
const CROWD_DISPLAY_MAX = 15000000;
const CROWD_DRIFT_MIN = 15000;
const CROWD_DRIFT_MAX = 120000;
let crowdDisplayRound = null;
let crowdDisplayTotals = { silver: 0, gold: 0, diamond: 0 };

const tpAudioPath = typeof tpAudioAssetPath !== "undefined"
    ? tpAudioAssetPath
    : (typeof audioAssetPath !== "undefined" ? audioAssetPath : "");

function avatarAsset(filename) {
    var base = (typeof avatarPath === "string" ? avatarPath : "");
    if (!base) return filename;
    if (base.charAt(base.length - 1) !== "/") {
        base += "/";
    }
    return base + filename;
}

function randomIntBetween(min, max) {
    var lo = Math.floor(Math.min(min, max));
    var hi = Math.floor(Math.max(min, max));
    return Math.floor(Math.random() * (hi - lo + 1)) + lo;
}

function initCrowdDisplayForRound(round) {
    crowdDisplayRound = safeAmount(round);

    SIDES.forEach(function (side) {
        crowdDisplayTotals[side] = randomIntBetween(CROWD_DISPLAY_MIN, CROWD_DISPLAY_MAX);
    });
}

function driftCrowdDisplayTotals() {
    SIDES.forEach(function (side) {
        var current = safeAmount(crowdDisplayTotals[side]);
        if (current <= 0) {
            current = randomIntBetween(CROWD_DISPLAY_MIN, CROWD_DISPLAY_MAX);
        }

        var drift = randomIntBetween(CROWD_DRIFT_MIN, CROWD_DRIFT_MAX);
        if (Math.random() < 0.45) {
            drift = -drift;
        }

        var next = current + drift;
        crowdDisplayTotals[side] = Math.max(CROWD_DISPLAY_MIN, Math.min(CROWD_DISPLAY_MAX, next));
    });
}

function resolveDisplayTotals(round, realTotals) {
    var roundNumber = safeAmount(round);

    if (crowdDisplayRound === null || crowdDisplayRound !== roundNumber) {
        initCrowdDisplayForRound(roundNumber);
    } else {
        driftCrowdDisplayTotals();
    }

    var display = { silver: 0, gold: 0, diamond: 0 };

    SIDES.forEach(function (side) {
        var real = safeAmount(realTotals && realTotals[side]);
        display[side] = safeAmount(crowdDisplayTotals[side]) + Math.max(0, real);
    });

    return display;
}

/* ===== INITIALIZATION ===== */
function bootTeenPattiGame() {
    if (typeof window.jQuery === "undefined") {
        setTimeout(bootTeenPattiGame, 50);
        return;
    }

    window.$ = window.jQuery;

    $(function () {
    if (typeof syncUrl === "undefined" || !syncUrl) {
        console.error("Teen Patti syncUrl is missing");
        return;
    }
    if (typeof investUrl === "undefined" || !investUrl) {
        console.error("Teen Patti investUrl is missing");
        return;
    }

    const initialChip = parseInt($(".tp-chip-btn.selected").data("value"), 10);
    if (!Number.isNaN(initialChip) && initialChip > 0) {
        selectedChip = initialChip;
    }

    initTeenPattiAutoLayout();
    setCountdownRemaining(countdownSeconds);
    loadLastRoundBetsFromStorage();
    syncGlobalState();
    syncInterval = setInterval(syncGlobalState, 1000);
    startLocalCountdown();
    startAmbientRain();
    setPhaseBadge("betting");

    // Watchdog: force-reset if animation is stuck for over 8 seconds
    setInterval(function () {
        if (isDealingInProgress && dealingStartedAt > 0) {
            var elapsed = Date.now() - dealingStartedAt;
            if (elapsed > 8000) {
                console.warn("[TP Watchdog] Animation stuck for " + elapsed + "ms, force-resetting");
                forceResetRound();
            }
        }
    }, 2000);

    $(".tp-chip-btn").on("click", function () {
        $(".tp-chip-btn").removeClass("selected");
        $(this).addClass("selected");
        selectedChip = safeAmount($(this).data("value"));
        playTpSound("click.mp3");
    });

    $(".tp-col").on("click", function () {
        if (!isBettingEnabled) {
            tpNotify("error", "Betting is closed for this round!");
            return;
        }
        const choose = String($(this).data("choose") || "").toLowerCase();
        if (!SIDE_LABELS[choose]) return;
        placeGlobalBet(choose);
    });

    // History panel open/close
    $(document).on("click", ".tp-btn-chart", openHistoryPanel);
    $(document).on("click", "#tpHistClose, #tpHistBackdrop", closeHistoryPanel);

    $("#tpBtnAddBalance").on("click", function () {
        if (typeof openWalletTopup === "function") {
            openWalletTopup();
            return;
        }

        tpNotify("error", "Add balance is unavailable right now");
    });

    $("#tpBtnRepeat").on("click", function () {
        if (!isBettingEnabled) {
            tpNotify("error", "Wait for next betting round");
            return;
        }

        var hasLast = SIDES.some(function (side) { return safeAmount(lastRoundBets[side]) > 0; });
        if (!hasLast) {
            tpNotify("error", "No previous bets to repeat");
            return;
        }

        var placed = 0;
        SIDES.forEach(function (side) {
            var amount = safeAmount(lastRoundBets[side]);
            if (amount > 0) {
                placeGlobalBet(side, amount);
                placed += amount;
            }
        });

        if (placed <= 0) {
            tpNotify("error", "No previous bets to repeat");
        }
    });

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            if (typeof refreshTenantWalletBalance === "function") {
                refreshTenantWalletBalance({ minInterval: 5000 });
            }
            syncGlobalState();
        }
    });

    $(window).on("focus", function () {
        if (typeof refreshTenantWalletBalance === "function") {
            refreshTenantWalletBalance({ minInterval: 5000 });
        }
    });
    });
}

function getTeenPattiViewportSize() {
    var viewport = window.visualViewport || null;
    var width = Math.floor((viewport && viewport.width) || window.innerWidth || document.documentElement.clientWidth || 0);
    var height = Math.floor((viewport && viewport.height) || window.innerHeight || document.documentElement.clientHeight || 0);

    return {
        width: Math.max(1, width),
        height: Math.max(1, height)
    };
}

function scheduleTeenPattiAutoLayout(delay) {
    window.clearTimeout(tpAutoLayoutTimer);
    tpAutoLayoutTimer = window.setTimeout(applyTeenPattiAutoLayout, typeof delay === "number" ? delay : 60);
}

function initTeenPattiAutoLayout() {
    if (tpAutoLayoutStarted) {
        scheduleTeenPattiAutoLayout(0);
        return;
    }

    tpAutoLayoutStarted = true;

    var refresh = function () {
        scheduleTeenPattiAutoLayout(35);
    };

    window.addEventListener("resize", refresh, { passive: true });
    window.addEventListener("orientationchange", refresh, { passive: true });
    window.addEventListener("load", refresh, { passive: true });

    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", refresh, { passive: true });
        window.visualViewport.addEventListener("scroll", refresh, { passive: true });
    }

    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === "function") {
        document.fonts.ready.then(refresh);
    }

    var wrapper = document.getElementById("tpGameWrapper") || document.querySelector(".tp-game-wrapper");
    if (wrapper && window.MutationObserver) {
        tpAutoLayoutObserver = new MutationObserver(function () {
            scheduleTeenPattiAutoLayout(80);
        });
        tpAutoLayoutObserver.observe(wrapper, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["class", "src"]
        });
    }

    scheduleTeenPattiAutoLayout(0);
    window.setTimeout(refresh, 180);
    window.setTimeout(refresh, 650);
}

function applyTeenPattiAutoLayout() {
    var wrapper = document.getElementById("tpGameWrapper") || document.querySelector(".tp-game-wrapper");
    var size = getTeenPattiViewportSize();
    var doc = document.documentElement;

    doc.style.setProperty("--tp-app-height", size.height + "px");
    doc.style.setProperty("--tp-app-width", size.width + "px");

    if (document.body) {
        document.body.classList.toggle("tp-compact-viewport", size.height <= 430 || size.width <= 340);
        document.body.classList.toggle("tp-ultra-compact-viewport", size.height <= 340 || size.width <= 300);
    }

    if (!wrapper) {
        return;
    }

    // WebView uses a stage shell; keep that precise stage-fit path as source of truth.
    if (wrapper.closest(".tp-wv-root") && typeof window.tpRefreshWebViewLayout === "function") {
        window.tpRefreshWebViewLayout();
        return;
    }

    wrapper.style.height = size.height + "px";
    wrapper.style.maxHeight = size.height + "px";
    wrapper.style.minHeight = "0";
    wrapper.style.transform = "scale(1)";
    wrapper.style.transformOrigin = "top center";

    var wrapperRect = wrapper.getBoundingClientRect();
    var selectors = [
        ".tp-header",
        ".tp-timer-section",
        ".tp-dealer-section",
        ".tp-play-area",
        ".tp-history-bar",
        ".tp-footer",
        ".tp-chips-row",
        ".tp-bottom-actions"
    ];
    var maxBottom = wrapperRect.top + Math.max(wrapper.scrollHeight, wrapper.offsetHeight, wrapper.clientHeight);
    var maxRight = wrapperRect.left + Math.max(wrapper.scrollWidth, wrapper.offsetWidth, wrapper.clientWidth);

    selectors.forEach(function (selector) {
        var element = wrapper.querySelector(selector);
        if (!element) {
            return;
        }
        var style = window.getComputedStyle(element);
        if (style.display === "none" || style.visibility === "hidden") {
            return;
        }
        var rect = element.getBoundingClientRect();
        maxBottom = Math.max(maxBottom, rect.bottom);
        maxRight = Math.max(maxRight, rect.right);
    });

    var visualHeight = Math.max(1, maxBottom - wrapperRect.top);
    var visualWidth = Math.max(1, maxRight - wrapperRect.left);
    var heightScale = visualHeight > size.height ? size.height / visualHeight : 1;
    var widthScale = visualWidth > size.width ? size.width / visualWidth : 1;
    var scale = Math.min(1, heightScale, widthScale);

    if (scale < 0.998) {
        scale = Math.max(0.72, Math.floor(scale * 1000) / 1000);
        wrapper.style.transform = "scale(" + scale + ")";
        if (document.body) {
            document.body.classList.add("tp-autofit-active");
        }
    } else if (document.body) {
        document.body.classList.remove("tp-autofit-active");
    }
}

bootTeenPattiGame();

/* ===== SYNC & UI UPDATE ===== */
var syncErrorCount = 0;

function syncGlobalState() {
    $.ajax({
        url: syncUrl,
        type: "GET",
        cache: false,
        timeout: 5000,
        success: function (data) {
            syncErrorCount = 0;
            if (!data || typeof data !== "object") return;
            updateUI(normalizeSyncData(data));
        },
        error: function (err) {
            syncErrorCount++;
            console.error("Teen Patti sync error #" + syncErrorCount, err);
            if (syncErrorCount >= 10 && isDealingInProgress) {
                console.warn("[TP] Too many sync errors, force-resetting");
                forceResetRound();
            }
        }
    });
}

function updateUI(data) {
    var prevPhase = currentPhase;
    var prevRound = currentRound;

    currentRound = data.round;
    currentPhase = data.phase;

    // Capture the bets from the previous round as "last round bets" when the round changes.
    if (prevRound !== null && prevRound !== currentRound) {
        var prevHasBets = SIDES.some(function (s) { return safeAmount(latestMyBets[s]) > 0; });
        if (prevHasBets) {
            lastRoundBets = {
                silver: safeAmount(latestMyBets.silver),
                gold: safeAmount(latestMyBets.gold),
                diamond: safeAmount(latestMyBets.diamond)
            };
            lastRoundBetsRound = prevRound;
            persistLastRoundBets();
        }
    }

    latestMyBets = data.my_bets;

    syncCountdown(data.remaining);
    updateBalanceDisplay(data.balance);
    updateTotals(data);
    var visibleHistory = buildVisibleHistory(data);
    updateHistory(visibleHistory);
    hydrateHistoryIfMissing(visibleHistory);

    if (currentPhase === "hold") {
        lockBetting();
        if (data.result && data.result.winner && resultShownForRound !== data.round && !isDealingInProgress) {
            dealingForRound = data.round;
            handleResult(data.result);
        }
    } else {
        if (!isDealingInProgress) {
            if (prevPhase === "hold" || prevRound !== currentRound) {
                resultShownForRound = null;
                unlockBetting();
            }
        }
    }

    scheduleTeenPattiAutoLayout(40);
}


function normalizeSyncData(raw) {
    const bets = raw && typeof raw.bets === "object" ? raw.bets : {};
    const myBets = raw && typeof raw.my_bets === "object" ? raw.my_bets : {};

    return {
        round: safeAmount(raw && raw.round),
        phase: (raw && raw.phase) === "hold" ? "hold" : "betting",
        remaining: Math.max(0, safeAmount(raw && raw.remaining)),
        bets: {
            silver: safeAmount(bets.silver),
            gold: safeAmount(bets.gold),
            diamond: safeAmount(bets.diamond)
        },
        my_bets: {
            silver: safeAmount(myBets.silver),
            gold: safeAmount(myBets.gold),
            diamond: safeAmount(myBets.diamond)
        },
        balance: raw && typeof raw.balance !== "undefined" ? raw.balance : null,
        result: raw && raw.result ? raw.result : null,
        history: Array.isArray(raw && raw.history) ? raw.history : []
    };
}

function buildVisibleHistory(data) {
    var merged = {};
    var rawHistory = Array.isArray(data.history) ? data.history : [];
    rawHistory.forEach(function (item) {
        var normalized = normalizeHistoryEntry(item);
        if (!normalized) {
            return;
        }
        merged[normalized.round] = normalized;
    });

    Object.keys(localHistoryMap).forEach(function (roundKey) {
        if (!merged[roundKey]) {
            merged[roundKey] = localHistoryMap[roundKey];
        }
    });

    var result = data.result && data.result.winner ? data.result : null;
    if (result) {
        var resultEntry = normalizeHistoryEntry({
            round: result.round || data.round,
            winner: result.winner,
            totals: result.totals || data.bets || {}
        });
        if (resultEntry) {
            merged[resultEntry.round] = resultEntry;
        }
    }

    var rounds = Object.keys(merged)
        .map(function (round) { return safeAmount(round); })
        .filter(function (round) { return round > 0; })
        .sort(function (a, b) { return b - a; })
        .slice(0, MAX_VISIBLE_HISTORY);

    localHistoryMap = {};
    rounds.forEach(function (round) {
        localHistoryMap[round] = merged[round];
    });

    return rounds.map(function (round) {
        return localHistoryMap[round];
    });
}

function normalizeHistoryEntry(item) {
    if (!item || typeof item !== "object") {
        return null;
    }

    var round = safeAmount(item.round);
    var winner = String(item.winner || "").toLowerCase();
    if (round <= 0 || SIDES.indexOf(winner) === -1) {
        return null;
    }
    if (currentRound && round > currentRound) {
        return null;
    }

    var totalsRaw = (item.totals && typeof item.totals === "object") ? item.totals : {};
    var totals = {
        silver: safeAmount(totalsRaw.silver),
        gold: safeAmount(totalsRaw.gold),
        diamond: safeAmount(totalsRaw.diamond)
    };

    return {
        round: round,
        winner: winner,
        totals: totals,
        pool: safeAmount(typeof item.pool !== "undefined" ? item.pool : (totals.silver + totals.gold + totals.diamond)),
        players: Math.max(0, safeAmount(item.players)),
        time: item.time || "",
        ranks: item.ranks && typeof item.ranks === "object" ? item.ranks : {}
    };
}

/* ===== PHASE BADGE ===== */
function setPhaseBadge(phase, text) {
    const badge = $("#tpPhaseBadge");
    badge.removeClass("phase-betting phase-shuffling phase-dealing phase-result");

    switch (phase) {
        case "betting":
            badge.addClass("phase-betting").text(text || "Place Your Bets");
            break;
        case "shuffling":
            badge.addClass("phase-shuffling").text(text || "Shuffling Cards...");
            break;
        case "dealing":
            badge.addClass("phase-dealing").text(text || "Dealing Cards...");
            break;
        case "result":
            badge.addClass("phase-result").text(text || "Round Complete!");
            break;
    }
}

/* ===== NARRATION BAR ===== */
function showNarration(text) {
    var bar = $("#tpNarrationBar");
    var textEl = $("#tpNarrationText");
    textEl.text(text);
    bar.addClass("show");
}

function hideNarration() {
    $("#tpNarrationBar").removeClass("show");
}

function showNarrationDelayed(text, delay) {
    setTimeout(function () {
        showNarration(text);
    }, delay);
}

/* ===== CARD DISPLAY NAME ===== */
function getCardDisplayName(card) {
    if (!card || card === "BACK") return "?";
    var code = normalizeCardCode(card);
    if (!code) return "?";
    var parts = code.split("-");
    var valueMap = { "A": "Ace", "K": "King", "Q": "Queen", "J": "Jack", "10": "10", "9": "9", "8": "8", "7": "7", "6": "6", "5": "5", "4": "4", "3": "3", "2": "2" };
    var suitMap = { "H": "\u2665", "D": "\u2666", "C": "\u2663", "S": "\u2660" };
    var value = valueMap[parts[0]] || parts[0];
    var suit = suitMap[parts[1]] || parts[1];
    return value + " " + suit;
}

/* ===== DEALER LADY CONTROLS ===== */
function setDealerStatus(text, show) {
    var status = $("#tpDealerStatus");
    status.text(text);
    if (show) {
        status.addClass("show");
    } else {
        status.removeClass("show");
    }
}

function setDealerState(state) {
    var avatar = $("#tpDealerAvatar");
    avatar.removeClass("shuffling dealing");
    if (state === "shuffling") {
        avatar.addClass("shuffling");
    } else if (state === "dealing") {
        avatar.addClass("dealing");
    }
}

function showShuffleDeck(show) {
    if (show) {
        $("#tpShuffleDeck").addClass("show");
    } else {
        $("#tpShuffleDeck").removeClass("show");
    }
}

/* ===== FLYING CARD ANIMATION ===== */
function flyCardToTarget(targetSelector, delay, onLand) {
    setTimeout(function () {
        var dealerEl = document.getElementById("tpDealerAvatar");
        var targetEl = document.querySelector(targetSelector);
        if (!dealerEl || !targetEl) {
            if (onLand) onLand();
            return;
        }

        var dealerRect = dealerEl.getBoundingClientRect();
        var targetRect = targetEl.getBoundingClientRect();

        var card = document.createElement("div");
        card.className = "tp-flying-card";
        card.style.left = (dealerRect.left + dealerRect.width / 2 - 18) + "px";
        card.style.top = (dealerRect.top + dealerRect.height / 2 - 26) + "px";
        document.body.appendChild(card);

        playTpSound("card.mp3");

        requestAnimationFrame(function () {
            card.style.left = (targetRect.left + targetRect.width / 2 - 18) + "px";
            card.style.top = (targetRect.top + targetRect.height / 2 - 26) + "px";
            card.style.transform = "rotate(" + (Math.random() * 20 - 10) + "deg)";
        });

        setTimeout(function () {
            card.classList.add("landed");
            createSparkles(targetRect.left + targetRect.width / 2, targetRect.top + targetRect.height / 2, 5);
            setTimeout(function () {
                card.remove();
                if (onLand) onLand();
            }, 350);
        }, 600);
    }, delay);
}

function createSparkles(x, y, count) {
    for (var i = 0; i < count; i++) {
        var sparkle = document.createElement("div");
        sparkle.className = "tp-sparkle";
        sparkle.style.left = (x + (Math.random() * 30 - 15)) + "px";
        sparkle.style.top = (y + (Math.random() * 30 - 15)) + "px";
        document.body.appendChild(sparkle);
        setTimeout(function () { sparkle.remove(); }, 800);
    }
}

/* ============================================================
   MAIN RESULT HANDLER - COMPRESSED CINEMATIC FLOW (~6s total)
   Phase 1: Shuffle (1s)
   Phase 2: Deal all 9 cards fast (2.4s - 3 per side simultaneously)
   Phase 3: Rank reveals (1.2s)
   Phase 4: Winner highlight (1.4s)
   Total: ~6 seconds — fits within the 7s server hold window
   ============================================================ */
function handleResult(result) {
    resultShownForRound = result.round || currentRound;
    isDealingInProgress = true;
    dealingStartedAt = Date.now();
    dealingForRound = result.round || currentRound;
    lastResultData = result;

    // Clear any previous animation timers
    clearAnimationTimers();

    var winner = String(result.winner || "").toLowerCase();
    var hands = result.hands || {};
    var ranks = result.ranks || {};

    // Hide countdown timer during dealing
    $(".tp-timer-section").addClass("hide-timer");

    // Reset cards to back (3D flip cards)
    resetCardsToBack();

    // ========== PHASE 1: SHUFFLE (1 second) ==========
    setPhaseBadge("shuffling", "Shuffling...");
    setDealerState("shuffling");
    setDealerStatus("Shuffling the deck...", true);
    showNarration("Dealer Riya shuffles the deck...");
    showShuffleDeck(true);

    animationTimers.push(setTimeout(function () {
        // ========== PHASE 2: FAST DEAL - 3 rounds of 3 cards (2.4s) ==========
        showShuffleDeck(false);
        setPhaseBadge("dealing", "Dealing Cards...");
        setDealerState("dealing");
        setDealerStatus("Dealing cards...", true);
        showNarration("Dealing begins!");

        // Fast deal: each round of 3 cards in ~0.8s
        var dealSequence = [
            { side: "Silver",  cardIdx: 0, delay: 0 },
            { side: "Gold",    cardIdx: 0, delay: 200 },
            { side: "Diamond", cardIdx: 0, delay: 400 },
            { side: "Silver",  cardIdx: 1, delay: 800 },
            { side: "Gold",    cardIdx: 1, delay: 1000 },
            { side: "Diamond", cardIdx: 1, delay: 1200 },
            { side: "Silver",  cardIdx: 2, delay: 1600 },
            { side: "Gold",    cardIdx: 2, delay: 1800 },
            { side: "Diamond", cardIdx: 2, delay: 2000 }
        ];

        dealSequence.forEach(function (deal, idx) {
            var sideLower = deal.side.toLowerCase();
            var sideCards = Array.isArray(hands[sideLower]) ? hands[sideLower] : [];
            var card = sideCards[deal.cardIdx] || "BACK";
            var targetSel = "#tpCards" + deal.side;

            animationTimers.push(setTimeout(function () {
                $(".tp-col").removeClass("receiving");
                $("#tpCol" + deal.side).addClass("receiving");
            }, deal.delay));

            // Fly card + flip
            flyCardToTarget(targetSel, deal.delay, (function (capturedCard, capturedIdx) {
                return function () {
                    var container = $(targetSel);
                    var cardInners = container.find(".tp-card-inner");
                    if (cardInners.length > capturedIdx) {
                        var cardInner = $(cardInners[capturedIdx]);
                        var code = normalizeCardCode(capturedCard);
                        renderCardFront(cardInner.find(".tp-card-front"), code);
                        cardInner.addClass("is-flipped");
                    }
                };
            })(card, deal.cardIdx));
        });

        // ========== PHASE 3: RANK REVEALS (1.2s after deal ends at 2.4s) ==========
        var dealEndTime = 2400;

        animationTimers.push(setTimeout(function () {
            $(".tp-col").removeClass("receiving");
            setDealerState("idle");
            setPhaseBadge("dealing", "Comparing...");
            showNarration("Comparing all hands...");

            // Reveal all ranks quickly — 0.4s apart
            animationTimers.push(setTimeout(function () {
                revealRankWithPause("Silver", ranks.silver || "--", 0, winner);
                playTpSound("card.mp3");
            }, 0));

            animationTimers.push(setTimeout(function () {
                revealRankWithPause("Gold", ranks.gold || "--", 0, winner);
                playTpSound("card.mp3");
            }, 400));

            animationTimers.push(setTimeout(function () {
                revealRankWithPause("Diamond", ranks.diamond || "--", 0, winner);
                playTpSound("card.mp3");
            }, 800));

            // ========== PHASE 4: WINNER + MODAL (1.4s after ranks) ==========
            animationTimers.push(setTimeout(function () {
                highlightWinnerColumn(winner);
                var winRank = ranks[winner] || "High Card";
                setPhaseBadge("result", SIDE_LABELS[winner] + " Wins!");
                setDealerStatus(SIDE_LABELS[winner] + " takes the pot!", true);
                showNarration(SIDE_LABELS[winner] + " wins with " + winRank + "!");

                var col = $("#tpCol" + sideCap(winner))[0];
                if (col) {
                    var rect = col.getBoundingClientRect();
                    createSparkles(rect.left + rect.width / 2, rect.top + 30, 12);
                }
                triggerWinConfetti(winner);

                showWinnerModal(result);

                animationTimers.push(setTimeout(function () {
                    closeWinnerModal();
                    hideNarration();
                    $(".tp-timer-section").removeClass("hide-timer");
                    isDealingInProgress = false;
                    dealingStartedAt = 0;
                }, 2600));

            }, 1200));

        }, dealEndTime));

    }, 1000)); // End of 1s shuffle phase
}

/* ===== Force-abort helper: clears all animation timers ===== */
function clearAnimationTimers() {
    for (var i = 0; i < animationTimers.length; i++) {
        clearTimeout(animationTimers[i]);
    }
    animationTimers = [];
}

/* ===== Force reset: abort any running animation and reset to clean state ===== */
function forceResetRound() {
    clearAnimationTimers();
    isDealingInProgress = false;
    dealingStartedAt = 0;
    dealingForRound = null;
    resultShownForRound = null;

    // Reset all UI elements immediately
    $(".tp-timer-section").removeClass("hide-timer");
    hideNarration();
    closeWinnerModal();

    $(".tp-col").removeClass("round-winner receiving winner-party");
    $(".tp-winner-crown").remove();
    $(".tp-card-inner").removeClass("highlight-card is-flipped");
    $(".tp-hand-rank").text("--").css({ background: "rgba(0,0,0,0.6)", color: "var(--tp-gold)", fontWeight: "" });
    resetCardsToBack();

    ["Silver", "Gold", "Diamond"].forEach(function (side) {
        $("#tpPile" + side).empty();
        $("#tpSlot" + side).removeClass("winner-slot hot");
    });

    showShuffleDeck(false);
    setDealerState("idle");
    setDealerStatus("", false);
    setPhaseBadge("betting");
    isBettingEnabled = true;
    startAmbientRain();
}

/* Build hand text like "A♠ K♥ Q♦" */
function buildHandText(cards) {
    if (!Array.isArray(cards) || cards.length === 0) return "?";
    return cards.map(function (c) {
        return getCardDisplayName(c);
    }).join("  ");
}

function resetCardsToBack() {
    ["Silver", "Gold", "Diamond"].forEach(function (side) {
        $("#tpCards" + side).html(
            create3DCard("BACK") +
            create3DCard("BACK") +
            create3DCard("BACK")
        );
    });
}

function createCardSrc(card) {
    if (!card || card === "BACK") return cardBackImage;
    var code = normalizeCardCode(card);
    if (!code) return cardBackImage;
    return cardAssetUrl(code + ".png");
}

function cardAssetUrl(filename) {
    var base = (typeof imagePath === "string" ? imagePath : "");
    if (!base) return filename;
    if (base.charAt(base.length - 1) !== "/") {
        base += "/";
    }
    return base + filename;
}

function deckBaseFromImagePath() {
    var raw = String((typeof imagePath === "string" ? imagePath : "") || "").trim();
    if (!raw) {
        return "";
    }
    return raw.charAt(raw.length - 1) === "/" ? raw : (raw + "/");
}

function sameOriginDeckBase(url) {
    var raw = String(url || "").trim();
    if (!raw) {
        return "";
    }
    try {
        var parsed = new URL(raw, window.location.href);
        return parsed.pathname.replace(/\/+$/, "") + "/";
    } catch (e) {
        return "";
    }
}

function addCandidateUrl(target, seen, url) {
    var value = String(url || "").trim();
    if (!value || seen[value]) {
        return;
    }
    seen[value] = true;
    target.push(value);
}

function buildCardAssetCandidates(code) {
    var candidates = [];
    var seen = {};
    var names = [
        code + ".png",
        code.replace("-", "") + ".png",
        code.replace("-", "_") + ".png",
    ];

    var deckBases = [];
    var pushDeckBase = function (base) {
        var normalized = String(base || "").trim();
        if (!normalized) {
            return;
        }
        normalized = normalized.replace(/\/+$/, "") + "/";
        if (deckBases.indexOf(normalized) === -1) {
            deckBases.push(normalized);
        }
    };

    if (typeof tpCardDeckPaths !== "undefined" && Array.isArray(tpCardDeckPaths)) {
        tpCardDeckPaths.forEach(function (base) {
            pushDeckBase(base);
            pushDeckBase(sameOriginDeckBase(base));
        });
    }

    var primaryBase = deckBaseFromImagePath();
    pushDeckBase(primaryBase);
    pushDeckBase(sameOriginDeckBase(primaryBase));

    var backBase = String((typeof cardBackImage === "string" ? cardBackImage : "") || "").replace(/BACK\.png(?:\?.*)?$/i, "");
    pushDeckBase(backBase);
    pushDeckBase(sameOriginDeckBase(backBase));

    deckBases.forEach(function (base) {
        names.forEach(function (name) {
            addCandidateUrl(candidates, seen, base + name);
        });
    });

    // Extra hard fallback: try known template decks too.
    if (primaryBase) {
        ["parimatch", "sunfyre", "basic"].forEach(function (template) {
            var fallbackBase = primaryBase.replace(/assets\/templates\/[^/]+\/images\/cards\/?$/i, "assets/templates/" + template + "/images/cards/");
            var fallbackBaseRelative = sameOriginDeckBase(fallbackBase);
            names.forEach(function (name) {
                addCandidateUrl(candidates, seen, fallbackBase + name);
                addCandidateUrl(candidates, seen, fallbackBaseRelative + name);
            });
        });
    }

    return candidates;
}

/* ============================================================
   CSS CARD FACE RENDERER — works for all 52 cards + Joker
   No image loading = no failure, always visible after flip
   ============================================================ */
var SUIT_SYM = { H: "\u2665", D: "\u2666", C: "\u2663", S: "\u2660" };

function createCardFaceHTML(code) {
    if (!code) return '<div class="tp-cf blk"></div>';

    // Joker cards
    if (code === "J-R" || code === "J-B") {
        var jkrColor = (code === "J-R") ? "#e63946" : "#aaaaaa";
        return '<div class="tp-cf jkr">' +
            '<div class="tp-cf-top"><span class="tp-cf-v sm" style="color:' + jkrColor + '">JK</span></div>' +
            '<div class="tp-cf-mid" style="font-size:18px;color:' + jkrColor + '">&#9824;</div>' +
            '<div class="tp-cf-bot"><span class="tp-cf-v sm" style="color:' + jkrColor + '">JK</span></div>' +
            '</div>';
    }

    var parts    = code.split("-");
    var val      = parts[0]; // 2-10, J, Q, K, A
    var suit     = parts[1]; // H D C S
    var sym      = SUIT_SYM[suit] || "?";
    var isRed    = (suit === "H" || suit === "D");
    var colClass = isRed ? "red" : "blk";
    var valClass = (val === "10") ? " sm" : "";
    var isFace   = (val === "J" || val === "Q" || val === "K");

    var midHtml;
    if (isFace) {
        // Face cards: big suit + letter below
        midHtml = '<div class="tp-cf-mid face-card">' + sym +
                  '<span class="tp-cf-fl">' + val + '</span></div>';
    } else if (val === "A") {
        // Ace: large suit symbol
        midHtml = '<div class="tp-cf-mid" style="font-size:24px">' + sym + '</div>';
    } else {
        midHtml = '<div class="tp-cf-mid">' + sym + '</div>';
    }

    return '<div class="tp-cf ' + colClass + '">' +
        '<div class="tp-cf-top">' +
            '<span class="tp-cf-v' + valClass + '">' + val + '</span>' +
            '<span class="tp-cf-s">'  + sym + '</span>' +
        '</div>' +
        midHtml +
        '<div class="tp-cf-bot">' +
            '<span class="tp-cf-v' + valClass + '">' + val + '</span>' +
            '<span class="tp-cf-s">'  + sym + '</span>' +
        '</div>' +
        '</div>';
}

function create3DCard(cardCode) {
    var fallbackAttr = 'onerror="this.src=\'' + cardBackImage + '\'"';
    // Front face: CSS-drawn — no image loading required
    var code = (cardCode && cardCode !== "BACK") ? normalizeCardCode(cardCode) : null;
    var frontContent = code ? createCardFaceHTML(code) : '<div class="tp-cf blk"></div>';

    return '<div class="tp-card-container">' +
                '<div class="tp-card-inner">' +
                    '<div class="tp-card-back"><img src="' + cardBackImage + '" ' + fallbackAttr + '></div>' +
                    '<div class="tp-card-front">' + frontContent + '</div>' +
                '</div>' +
           '</div>';
}

function renderCardFront(frontElement, code) {
    var front = (frontElement && frontElement.jquery) ? frontElement : $(frontElement);
    if (!front.length) {
        return;
    }

    if (!code) {
        front.html('<div class="tp-cf blk"></div>');
        return;
    }

    front.empty();
    var img = createCardImg(code, function (normalizedCode) {
        front.html(createCardFaceHTML(normalizedCode || code));
    });

    img.removeClass("tp-card-img anime-flip")
        .addClass("tp-card-front-img")
        .attr("alt", getCardDisplayName(code))
        .attr("title", code);

    front.append(img);
}

function revealRankWithPause(sideCapName, rank, delay, winner) {
    setTimeout(function () {
        var rankEl = $("#tpRank" + sideCapName);
        var sideLower = sideCapName.toLowerCase();
        var isWinner = sideLower === winner;

        rankEl.text(rank || "--");
        rankEl.addClass("rank-revealed");

        if (isWinner) {
            rankEl.css({ background: "linear-gradient(135deg, #ffd700, #ff8c00)", color: "#000", fontWeight: "900" });
        } else {
            rankEl.css({ background: "rgba(255,255,255,0.15)", color: "#aaa" });
        }

        setTimeout(function () {
            rankEl.removeClass("rank-revealed");
        }, 600);
    }, delay);
}

function highlightWinnerColumn(winner) {
    var cap = sideCap(winner);
    $(".tp-col").removeClass("round-winner winner-party");
    $(".tp-winner-crown").remove();

    var col = $("#tpCol" + cap);
    col.addClass("round-winner");

    var crown = $('<div class="tp-winner-crown">&#128081;</div>');
    col.find(".tp-char-frame").append(crown);

    $("#tpCards" + cap).find(".tp-card-inner").addClass("highlight-card");
    $("#tpSlot" + cap).addClass("winner-slot");

    var colEl = col[0];
    if (colEl) {
        var rect = colEl.getBoundingClientRect();
        createSparkles(rect.left + rect.width / 2, rect.top + 30, 24);
    }

    // Winner party effect should run only on the winner column.
    if (winnerPartyTimer) {
        clearTimeout(winnerPartyTimer);
        winnerPartyTimer = null;
    }
    col.addClass("winner-party");
    winnerPartyTimer = setTimeout(function () {
        col.removeClass("winner-party");
        winnerPartyTimer = null;
    }, 2400);
}

function closeSummaryAndReset() {
    $(".tp-timer-section").removeClass("hide-timer");
    hideNarration();

    isDealingInProgress = false;
    dealingStartedAt = 0;
    dealingForRound = null;

    $(".tp-col").removeClass("round-winner receiving winner-party");
    $(".tp-winner-crown").remove();
    $(".tp-card-inner").removeClass("highlight-card is-flipped");

    $(".tp-hand-rank").text("--").css({ background: "rgba(0,0,0,0.6)", color: "var(--tp-gold)", fontWeight: "" });
    resetCardsToBack();

    ["Silver", "Gold", "Diamond"].forEach(function (side) {
        $("#tpPile" + side).empty();
        $("#tpSlot" + side).removeClass("winner-slot hot");
    });

    isBettingEnabled = true;
    resultShownForRound = null;
    closeWinnerModal();
    startAmbientRain();
    setPhaseBadge("betting");
    setDealerStatus("", false);
    setDealerState("idle");
}

/* ===== CARD HELPERS ===== */
function normalizeCardCode(card) {
    if (!card) return "";
    var raw = String(card).trim().toUpperCase();
    raw = raw.replace(/_/g, "-");

    // Jokers
    if (raw === "J-R" || raw === "JR") return "J-R";
    if (raw === "J-B" || raw === "JB") return "J-B";

    if (raw.length === 2 && !raw.includes("-")) {
        raw = raw.charAt(0) + "-" + raw.charAt(1);
    }
    if (raw.length === 3 && raw.startsWith("10") && !raw.includes("-")) {
        raw = "10-" + raw.charAt(2);
    }

    var match = raw.match(/^([2-9]|10|[JQKA])-([HDCS])$/);
    if (!match) return "";
    return match[1] + "-" + match[2];
}

function createCardImg(card, onFallback) {
    var code = normalizeCardCode(card);
    var img = $('<img class="tp-card-img anime-flip" alt="card">');

    if (!code) {
        img.attr("src", cardBackImage);
        return img;
    }

    var candidates = buildCardAssetCandidates(code);
    var idx = 0;

    var applyNext = function () {
        if (idx >= candidates.length) {
            img.off("error", applyNext);
            if (typeof onFallback === "function") {
                onFallback(code);
            } else {
                img.attr("src", cardBackImage);
                img.attr("title", code);
            }
            return;
        }
        img.attr("src", candidates[idx]);
        idx += 1;
    };

    img.on("error", applyNext);
    applyNext();
    return img;
}

/* ===== WINNER MODAL ===== */
function showWinnerModal(result) {
    var winner = String(result.winner || "").toLowerCase();
    var winnerLabel = SIDE_LABELS[winner] || "Winner";
    window.tpLastWinnerSide = winner;

    var $modal = $("#tpWinnerModal");
    if (!$modal.length) return;

    var winnerImage = avatarAsset(winner + "_character.png");
    $("#tpWinnerImg")
        .off("error")
        .on("error", function () {
            $(this).attr("src", avatarAsset("gold_character.png"));
        })
        .attr("src", winnerImage);

    var payouts = result && result.user_payouts ? result.user_payouts : {};
    var payoutEntry = payouts[String(currentUserId)] || null;
    var hasOwnBet = SIDES.some(function (side) { return safeAmount(latestMyBets[side]) > 0; });

    // Reset classes
    $modal.removeClass("is-win is-lose is-idle").attr("aria-hidden", "false");

    if (payoutEntry) {
        var payout = safeAmount(payoutEntry.payout);
        $modal.addClass("is-win");
        $("#tpWinnerRoundTitle").text("You Win!");
        $("#tpWinnerStatusMsg")
            .removeClass("lost")
            .text("Congratulations! " + winnerLabel + " took the pot.");
        $("#tpWinVal").text(formatBetAmount(payout));
        animateWinAmount(payout);
        spawnWinConfetti();
        playTpSound("win.wav");
        if (typeof triggerFantasyWin === "function") triggerFantasyWin();

        if (typeof refreshTenantWalletBalance === "function") {
            setTimeout(function () {
                refreshTenantWalletBalance({ force: true, minInterval: 1500 });
            }, 350);
        }
    } else if (hasOwnBet) {
        $modal.addClass("is-lose");
        $("#tpWinnerRoundTitle").text(winnerLabel + " Wins");
        $("#tpWinnerStatusMsg")
            .addClass("lost")
            .text("Better luck next round!");
        $("#tpWinAmountValue").text("0");
        playTpSound("lose.wav");
    } else {
        $modal.addClass("is-idle");
        $("#tpWinnerRoundTitle").text(winnerLabel + " Wins");
        $("#tpWinnerStatusMsg")
            .addClass("lost")
            .text("Round complete — place your bets next round!");
        $("#tpWinAmountValue").text("0");
    }

    // Reveal modal
    $modal.addClass("is-open");
}

function animateWinAmount(finalAmount) {
    var $val = $("#tpWinAmountValue");
    if (!$val.length) {
        return;
    }
    var duration = 900;
    var start = performance && performance.now ? performance.now() : Date.now();
    var from = 0;
    var to = Math.max(0, Math.floor(safeAmount(finalAmount)));

    function step(now) {
        var elapsed = (now || Date.now()) - start;
        var t = Math.min(1, elapsed / duration);
        // easeOutCubic
        var eased = 1 - Math.pow(1 - t, 3);
        var current = Math.round(from + (to - from) * eased);
        $val.text(formatBetAmount(current));
        if (t < 1) {
            window.requestAnimationFrame(step);
        } else {
            $val.text(formatBetAmount(to));
        }
    }
    window.requestAnimationFrame(step);
}

function spawnWinConfetti() {
    var host = document.getElementById("tpWinConfetti");
    if (!host) return;
    host.innerHTML = "";
    var colors = ["#ffd84a", "#ff6bbf", "#5ee7ff", "#7cff7c", "#ffb347", "#b18cff"];
    var count = 38;
    for (var i = 0; i < count; i++) {
        var piece = document.createElement("span");
        piece.className = "tp-confetti-piece";
        var left = Math.random() * 100;
        var delay = Math.random() * 0.4;
        var duration = 1.6 + Math.random() * 1.4;
        var size = 6 + Math.random() * 6;
        var rotate = Math.floor(Math.random() * 360);
        var color = colors[Math.floor(Math.random() * colors.length)];
        var drift = (Math.random() * 60 - 30).toFixed(1) + "vw";
        piece.style.left = left + "%";
        piece.style.width = size + "px";
        piece.style.height = (size * 1.4) + "px";
        piece.style.background = color;
        piece.style.animationDelay = delay + "s";
        piece.style.animationDuration = duration + "s";
        piece.style.setProperty("--tp-confetti-rotate", rotate + "deg");
        piece.style.setProperty("--tp-confetti-drift", drift);
        host.appendChild(piece);
    }
    // Auto-clear after 3s
    setTimeout(function () {
        if (host) host.innerHTML = "";
    }, 3200);
}

function closeWinnerModal() {
    var $modal = $("#tpWinnerModal");
    if (!$modal.length) return;
    $modal.removeClass("is-open").attr("aria-hidden", "true");
    var host = document.getElementById("tpWinConfetti");
    if (host) host.innerHTML = "";
}

/* ===== BETTING FLOW ===== */
function lockBetting() {
    if (!isBettingEnabled) return;
    isBettingEnabled = false;
    stopAmbientRain();
    $("#tpStopOverlay").addClass("show");
    playTpSound("start.mp3");
    setTimeout(function () {
        $("#tpStopOverlay").removeClass("show");
    }, 2000);
}

function unlockBetting() {
    if (isBettingEnabled) return;
    if (isDealingInProgress) return;

    isBettingEnabled = true;
    closeWinnerModal();
    resetRoundUI();
    startAmbientRain();
    setPhaseBadge("betting");
}

function resetRoundUI() {
    $(".tp-hand-rank").text("--").css({ background: "rgba(0,0,0,0.6)", color: "var(--tp-gold)", fontWeight: "" });
    $(".tp-col").removeClass("round-winner receiving winner-party");
    $(".tp-winner-crown").remove();
    $(".tp-card-inner").removeClass("highlight-card is-flipped");
    $(".tp-timer-section").removeClass("hide-timer");
    hideNarration();
    ["Silver", "Gold", "Diamond"].forEach(function (side) {
        $("#tpCards" + side).html(
            create3DCard("BACK") +
            create3DCard("BACK") +
            create3DCard("BACK")
        );
        $("#tpPile" + side).empty();
        $("#tpSlot" + side).removeClass("winner-slot hot");
    });
    setDealerState("idle");
    setDealerStatus("", false);
}

function placeGlobalBet(choose, overrideAmount) {
    var amount = safeAmount(typeof overrideAmount !== "undefined" ? overrideAmount : selectedChip);
    if (amount <= 0) return;

    dropChip(choose, amount, { transient: false, ambient: false });
    flashSlot(choose);
    playCoinBurstSound();

    $.ajax({
        url: investUrl,
        type: "POST",
        data: {
            _token: $('input[name="_token"]').val(),
            invest: amount,
            choose: choose
        },
        success: function (data) {
            if (data && data.error) {
                tpNotify("error", data.error);
                return;
            }
            if (data && Array.isArray(data.errors) && data.errors.length) {
                tpNotify("error", data.errors[0]);
                return;
            }

            if (data && typeof data.balance !== "undefined") {
                $(".bal").text(data.balance);
            }

            if (data && data.my_bets) {
                latestMyBets = {
                    silver: safeAmount(data.my_bets.silver),
                    gold: safeAmount(data.my_bets.gold),
                    diamond: safeAmount(data.my_bets.diamond)
                };
                SIDES.forEach(function (side) {
                    $("#tpYou" + sideCap(side)).text(formatBetAmount(latestMyBets[side]));
                });
            }

            if (data && data.totals) {
                var displayTotals = resolveDisplayTotals(currentRound, data.totals);

                SIDES.forEach(function (side) {
                    var cap = sideCap(side);
                    var serverTotal = safeAmount(data.totals[side]);
                    var displayTotal = safeAmount(displayTotals[side]);

                    if (serverTotal > lastTotals[side]) {
                        animateIncomingBets(side, serverTotal - lastTotals[side]);
                    }

                    lastTotals[side] = serverTotal;
                    $("#tpAll" + cap).text(formatBetAmount(displayTotal));
                });
            }
        },
        error: function (xhr) {
            var msg = xhr && xhr.responseJSON
                ? (xhr.responseJSON.error || (xhr.responseJSON.errors && xhr.responseJSON.errors[0]))
                : null;
            tpNotify("error", msg || "Bet failed");
        }
    });
}

/* ===== UTILITY FUNCTIONS ===== */
function safeAmount(value) {
    var parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function updateBalanceDisplay(balance) {
    if (balance === null || typeof balance === "undefined") return;

    var displayValue = typeof balance === "number"
        ? formatBetAmount(balance)
        : String(balance).trim();

    if (!displayValue) return;
    $(".bal").text(displayValue);
}

function sideCap(side) {
    return side.charAt(0).toUpperCase() + side.slice(1);
}

function renderCountdown() {
    var remaining = getRenderedCountdownSeconds();
    countdownSeconds = remaining;
    $("#tpTimer").text(remaining);
}

function startLocalCountdown() {
    if (localCountdownInterval) clearInterval(localCountdownInterval);
    localCountdownInterval = setInterval(function () {
        renderCountdown();
    }, 250);
}

function syncCountdown(serverRemaining) {
    var remaining = Math.max(0, safeAmount(serverRemaining));
    var localRemaining = getRenderedCountdownSeconds();
    countdownSeconds = remaining;
    countdownTargetTime = Date.now() + (remaining * 1000);
    if (!hasSyncedOnce || Math.abs(remaining - localRemaining) > 1) {
        renderCountdown();
    }
}

function setCountdownRemaining(seconds) {
    countdownSeconds = Math.max(0, safeAmount(seconds));
    countdownTargetTime = Date.now() + (countdownSeconds * 1000);
    renderCountdown();
}

function getRenderedCountdownSeconds() {
    if (!countdownTargetTime) {
        return Math.max(0, Math.floor(countdownSeconds));
    }

    return Math.max(0, Math.ceil((countdownTargetTime - Date.now()) / 1000));
}

function updateTotals(data) {
    var displayTotals = resolveDisplayTotals(data.round, data.bets);

    SIDES.forEach(function (side) {
        var cap = sideCap(side);
        var mine = safeAmount(data.my_bets[side]);
        var displayTotal = safeAmount(displayTotals[side]);

        $("#tpAll" + cap).text(formatBetAmount(displayTotal));
        $("#tpYou" + cap).text(formatBetAmount(mine));
    });

    if (!hasSyncedOnce) {
        SIDES.forEach(function (side) {
            lastTotals[side] = safeAmount(data.bets[side]);
        });
        hasSyncedOnce = true;
        return;
    }

    SIDES.forEach(function (side) {
        var prev = safeAmount(lastTotals[side]);
        var next = safeAmount(data.bets[side]);
        if (next > prev) {
            var delta = next - prev;
            animateIncomingBets(side, delta);
        }
        lastTotals[side] = next;
    });
}

function updateHistory(history) {
    var rows = Array.isArray(history) ? history : [];
    var normalizedRows = rows
        .map(function (item) { return normalizeHistoryEntry(item); })
        .filter(function (item) { return !!item; })
        .sort(function (a, b) { return b.round - a.round; })
        .slice(0, MAX_VISIBLE_HISTORY);

    var html = "";
    normalizedRows.forEach(function (item) {
        var side = String(item.winner || "").toLowerCase();
        var char = side.charAt(0).toUpperCase();
        var cls = SIDE_HISTORY_CLASS[side] || ("dot-" + side);
        html += '<div class="tp-hist-dot ' + cls + '" data-round="' + item.round + '" title="Round #' + item.round + '">' + char + "</div>";
    });
    $("#tpHistory").html(html);
}

function resolveHistoryUrl() {
    var url = (typeof historyUrl !== "undefined" && historyUrl) ? historyUrl : null;
    if (!url && typeof syncUrl !== "undefined" && syncUrl) {
        url = String(syncUrl)
            .replace("/global/sync/", "/history/")
            .replace("/global/sync", "/history");
    }
    return url;
}

function hydrateHistoryIfMissing(visibleHistory) {
    var rows = Array.isArray(visibleHistory) ? visibleHistory : [];
    if (rows.length > 0 || historyHydrationInFlight || historyHydratedOnce) {
        return;
    }

    var url = resolveHistoryUrl();
    if (!url) {
        return;
    }

    historyHydrationInFlight = true;

    $.ajax({
        url: url,
        type: "GET",
        cache: false,
        timeout: 4000,
        success: function (data) {
            var rawRows = Array.isArray(data && data.history) ? data.history : [];
            var merged = {};

            rawRows.forEach(function (item) {
                var normalized = normalizeHistoryEntry(item);
                if (!normalized) {
                    return;
                }
                merged[normalized.round] = normalized;
            });

            var rounds = Object.keys(merged)
                .map(function (round) { return safeAmount(round); })
                .filter(function (round) { return round > 0; })
                .sort(function (a, b) { return b - a; })
                .slice(0, MAX_VISIBLE_HISTORY);

            if (!rounds.length) {
                return;
            }

            localHistoryMap = {};
            rounds.forEach(function (round) {
                localHistoryMap[round] = merged[round];
            });

            var hydratedRows = rounds.map(function (round) {
                return localHistoryMap[round];
            });

            updateHistory(hydratedRows);
            historyHydratedOnce = true;
        },
        complete: function () {
            historyHydrationInFlight = false;
        }
    });
}

/* ===== CHIP ANIMATIONS ===== */
function animateIncomingBets(side, delta) {
    var cap = sideCap(side);
    var chipCount = Math.min(14, Math.max(2, Math.round(delta / 400)));

    flashSlot(side);
    showDelta(side, delta);
    playCoinBurstSound();

    for (var i = 0; i < chipCount; i++) {
        var delay = i * 65;
        (function (d) {
            setTimeout(function () {
                dropChip(side, delta / chipCount, { transient: true, ambient: false });
            }, d);
        })(delay);
    }

    trimPile("#tpPile" + cap, 56);
}

function startAmbientRain() {
    if (ambientRainInterval) clearInterval(ambientRainInterval);

    // Keep ambient rain subtle so active bets stay clearly visible.
    var configuredAmounts = normalizeChipValues();
    var amounts = configuredAmounts.slice();

    function rainTick() {
        if (!isBettingEnabled) return;
        var count = Math.random() < 0.75 ? 1 : 2;

        for (var i = 0; i < count; i++) {
            var side = SIDES[Math.floor(Math.random() * SIDES.length)];
            var amount = amounts[Math.floor(Math.random() * amounts.length)];
            (function(s, a, delay) {
                setTimeout(function () {
                    if (isBettingEnabled) {
                        dropChip(s, a, { transient: true, ambient: true });
                    }
                }, delay);
            })(side, amount, i * 55);
        }
    }

    rainTick();
    ambientRainInterval = setInterval(rainTick, 800);
}

function stopAmbientRain() {
    if (ambientRainInterval) {
        clearInterval(ambientRainInterval);
        ambientRainInterval = null;
    }
}

function flashSlot(side) {
    var cap = sideCap(side);
    var slot = $("#tpSlot" + cap);
    slot.addClass("hot");
    setTimeout(function () {
        slot.removeClass("hot");
    }, 500);
}

function showDelta(side, amount) {
    var cap = sideCap(side);
    var slot = $("#tpSlot" + cap);
    var chipText = "+" + formatBetAmount(amount);
    var deltaEl = $('<div class="tp-delta-float"></div>').text(chipText);
    slot.append(deltaEl);
    setTimeout(function () {
        deltaEl.addClass("fade-out");
    }, 700);
    setTimeout(function () {
        deltaEl.remove();
    }, 1400);
}

function playCoinBurstSound() {
    var now = Date.now();
    if (now - lastCoinSoundAt < 120) return;
    lastCoinSoundAt = now;
    playTpSound("coin.mp3");
}

function playTpSound(filename) {
    if (typeof playAudio === "function" && tpAudioPath) {
        playAudio(tpAudioPath, filename);
    }
}

function tpNotify(type, message) {
    if (typeof notify === "function") {
        notify(type, message);
        return;
    }
    if (type === "error") {
        console.error(message);
    } else {
        console.log(message);
    }
}

function dropChip(choose, amount, options) {
    var cap = sideCap(String(choose || "").toLowerCase());
    var pile = $("#tpPile" + cap);
    if (!pile.length) return;

    var transient = !!(options && options.transient);
    var ambient   = !!(options && options.ambient);

    var chip = $('<div class="tp-small-chip"></div>');
    var targetTop = (Math.random() * 34 + 6).toFixed(2) + "%";

    // Randomised physics CSS variables — give each chip unique rotation / speed
    var rotSign   = Math.random() > 0.5 ? 1 : -1;
    var rotStart  = (rotSign * (Math.random() * 55 + 25)).toFixed(1) + "deg";
    var rotMid    = (-rotSign * (Math.random() * 20 + 5)).toFixed(1) + "deg";
    var rotEnd    = (rotSign * (Math.random() * 8 + 2)).toFixed(1) + "deg";
    var fallDur   = (ambient ? (Math.random() * 0.18 + 0.42) : (Math.random() * 0.12 + 0.5)).toFixed(3) + "s";
    var skew      = ((Math.random() * 10 - 5)).toFixed(1) + "deg";

    chip.addClass(chipClassByAmount(amount));
    chip.text(chipLabel(amount));
    chip.css({
        left: (Math.random() * 72 + 14) + "%",
        top: "-18%",
        "--tp-target-top": targetTop,
        "--rot-start": rotStart,
        "--rot-mid":   rotMid,
        "--rot-end":   rotEnd,
        "--fall-dur":  fallDur,
        "--skew":      skew,
    });
    chip.addClass("coin-fall");
    if (ambient)   chip.addClass("ambient");
    if (transient) chip.addClass("transient");

    pile.append(chip);

    // Fade & remove transient chips
    if (transient) {
        var lifetime = ambient ? 900 : 1300;
        setTimeout(function () { chip.addClass("fade-out"); }, lifetime);
        setTimeout(function () { chip.remove(); }, lifetime + 350);
    } else {
        trimPile("#tpPile" + cap, 80);
    }
}

function trimPile(selector, maxCount) {
    var pile = $(selector);
    var chips = pile.find(".tp-small-chip:not(.transient)");
    if (chips.length <= maxCount) return;
    chips.slice(0, chips.length - maxCount).remove();
}

function chipClassByAmount(amount) {
    var value = safeAmount(amount);
    if (value >= 40000) return "c-40k";
    if (value >= 20000) return "c-20k";
    if (value >= 4000) return "c-4k";
    if (value >= 2000) return "c-2k";
    return "c-400";
}

function chipLabel(amount) {
    var value = safeAmount(amount);
    if (value >= 1000) {
        var k = value / 1000;
        return (Math.round(k * 10) / 10) + "K";
    }
    return String(Math.round(value));
}

function formatBetAmount(amount) {
    var value = safeAmount(amount);
    return value.toLocaleString("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/* ============================================================
   CONFETTI BURST — called on winner reveal
   ============================================================ */
var CONF_PALETTES = {
    silver:  ["#c0c0c0", "#e0e0e0", "#ffffff", "#a8a8a8", "#d8d8d8"],
    gold:    ["#ffd700", "#e9d01b", "#ffaa00", "#ffe066", "#fff5a0"],
    diamond: ["#b9f2ff", "#00d4ff", "#80eeff", "#ffffff", "#aaddff"]
};

function triggerWinConfetti(side) {
    var wrapper = document.querySelector(".tp-game-wrapper");
    if (!wrapper) return;
    var palette = CONF_PALETTES[side] || CONF_PALETTES.gold;
    var shapes  = ["", "circle", "strip", "chip"]; // Added chip shape

    for (var i = 0; i < 120; i++) { // Increased particle count
        (function (delay) {
            setTimeout(function () {
                var p = document.createElement("div");
                var shape = shapes[Math.floor(Math.random() * shapes.length)];
                p.className = "tp-confetti " + shape;
                var color = palette[Math.floor(Math.random() * palette.length)];
                
                if (shape === "chip") {
                    p.style.backgroundColor = color;
                    p.style.border = "2px dashed rgba(255,255,255,0.8)";
                    p.style.boxShadow = "inset 0 0 5px rgba(0,0,0,0.5)";
                } else {
                    p.style.background = color;
                }
                
                var cx    = (Math.random() * 300 - 150).toFixed(1); // Wider spread
                var cy    = (Math.random() * 340 + 80).toFixed(1);  // Deeper fall
                var cr    = (Math.random() * 720 - 360).toFixed(1);
                var dur   = (Math.random() * 1.5 + 1.5).toFixed(3); // Make it last exactly up to 3s
                
                p.style.left = (Math.random() * 92 + 4) + "%";
                p.style.top = "-5%"; // Start from above the screen for rain effect
                
                p.style.setProperty("--cx", cx + "px");
                p.style.setProperty("--cy", cy + "px");
                p.style.setProperty("--cr", cr + "deg");
                p.style.setProperty("--confetti-dur", dur + "s");
                
                wrapper.appendChild(p);
                setTimeout(function () { p.remove(); }, parseFloat(dur) * 1000);
            }, delay);
        })(Math.random() * 1500); // Spread the burst over 1.5s so it continues to rain
    }
}

/* triggerFantasyWin — hooked from showWinnerModal when user wins */
function triggerFantasyWin() {
    var side = window.tpLastWinnerSide || "gold";
    triggerWinConfetti(side);
}

/* ============================================================
   HISTORY PANEL
   ============================================================ */
function openHistoryPanel() {
    $("#tpHistBackdrop, #tpHistPanel").addClass("open");
    loadHistoryPanel();
}

function closeHistoryPanel() {
    $("#tpHistBackdrop, #tpHistPanel").removeClass("open");
}

function loadHistoryPanel() {
    var url = resolveHistoryUrl();
    if (!url) {
        $("#tpHistBody").html('<div class="tp-hist-empty">History URL not configured.</div>');
        return;
    }

    $("#tpHistBody").html('<div class="tp-hist-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');

    $.ajax({
        url: url,
        type: "GET",
        cache: false,
        success: function (data) {
            var rawRows = Array.isArray(data && data.history) ? data.history : [];
            var rowsMap = {};
            rawRows.forEach(function (item) {
                var normalized = normalizeHistoryEntry(item);
                if (!normalized) {
                    return;
                }
                rowsMap[normalized.round] = normalized;
            });

            var rows = Object.keys(rowsMap)
                .map(function (round) { return rowsMap[round]; })
                .sort(function (a, b) { return b.round - a.round; })
                .slice(0, MAX_VISIBLE_HISTORY);

            if (!rows.length) {
                $("#tpHistBody").html('<div class="tp-hist-empty">No rounds recorded yet.</div>');
                return;
            }

            var html = '<div class="tp-hist-legend">' +
                '<span>#</span><span>Winner</span>' +
                '<span style="text-align:right">Pool</span>' +
                '<span style="text-align:right">Players</span>' +
                '<span style="text-align:right">Time</span></div>';

            rows.forEach(function (r) {
                var winner = String(r.winner || "").toLowerCase();
                var time   = r.time || "--";
                var pool   = (parseFloat(r.pool) || 0).toLocaleString("en-IN", { maximumFractionDigits: 0 });

                html += '<div class="tp-hist-row winner-' + winner + '">' +
                    '<span class="tp-hist-col round-num">#' + (r.round || "--") + '</span>' +
                    '<span class="tp-hist-col"><span class="tp-hist-winner-badge ' + winner + '">' + (winner || "?") + '</span></span>' +
                    '<span class="tp-hist-pool" style="text-align:right">' + pool + '</span>' +
                    '<span class="tp-hist-col" style="text-align:right">' + (r.players || 0) + '</span>' +
                    '<span class="tp-hist-col" style="font-size:10px;text-align:right">' + time + '</span>' +
                    '</div>';
            });

            $("#tpHistBody").html(html);
        },
        error: function () {
            $("#tpHistBody").html('<div class="tp-hist-empty">Failed to load history.</div>');
        }
    });
}
