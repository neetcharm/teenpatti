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
let selectedChip = 400;
let isBettingEnabled = true;
let resultShownForRound = null;
let countdownSeconds = 20;
let hasSyncedOnce = false;
let lastCoinSoundAt = 0;
let isDealingInProgress = false;
let dealingStartedAt = 0;       // Timestamp when dealing began (for watchdog)
let dealingForRound = null;     // Which round the current animation is for
let summaryCountdownTimer = null;
let animationTimers = [];       // Track all setTimeout IDs for abort
let countdownTargetTime = 0;

let lastTotals = { silver: 0, gold: 0, diamond: 0 };
let latestMyBets = { silver: 0, gold: 0, diamond: 0 };
let lastResultData = null;

const SIDES = ["silver", "gold", "diamond"];
const SIDE_LABELS = { silver: "Silver", gold: "Gold", diamond: "Diamond" };
const SIDE_COLORS = { silver: "#c0c0c0", gold: "#e9d01b", diamond: "#b9f2ff" };

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

    setCountdownRemaining(countdownSeconds);
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

        let placed = 0;
        SIDES.forEach(function (side) {
            const amount = safeAmount(latestMyBets[side]);
            if (amount > 0) {
                const unit = selectedChip > 0 ? selectedChip : 400;
                const clicks = Math.max(1, Math.round(amount / unit));
                for (let i = 0; i < clicks; i++) {
                    placeGlobalBet(side);
                }
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
                refreshTenantWalletBalance();
            }
            syncGlobalState();
        }
    });

    $(window).on("focus", function () {
        if (typeof refreshTenantWalletBalance === "function") {
            refreshTenantWalletBalance();
        }
    });
});

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
    latestMyBets = data.my_bets;

    syncCountdown(data.remaining);
    updateBalanceDisplay(data.balance);
    updateTotals(data);
    updateHistory(data.history);

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
   Phase 4: Winner highlight + summary (1.4s)
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
                        cardInner.find(".tp-card-front").html(
                            code ? createCardFaceHTML(code) : '<div class="tp-cf blk"></div>'
                        );
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

                // Show winner modal immediately
                showWinnerModal(result);

                // After 1.4s, transition to summary
                animationTimers.push(setTimeout(function () {
                    closeWinnerModal();
                    hideNarration();
                    showRoundSummary(result);
                    // Animation is "done" — summary stays until round changes
                    isDealingInProgress = false;
                    dealingStartedAt = 0;
                }, 1400));

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
    if (summaryCountdownTimer) {
        clearInterval(summaryCountdownTimer);
        summaryCountdownTimer = null;
    }
    isDealingInProgress = false;
    dealingStartedAt = 0;
    dealingForRound = null;
    resultShownForRound = null;

    // Reset all UI elements immediately
    $("#tpRoundSummary").removeClass("show").css("display", "");
    $(".tp-timer-section").removeClass("hide-timer");
    hideNarration();
    closeWinnerModal();

    $(".tp-col").removeClass("round-winner receiving");
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
    return imagePath + code + ".png";
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
    $(".tp-col").removeClass("round-winner");
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
        createSparkles(rect.left + rect.width / 2, rect.top + 30, 12);
    }
}

/* ===== ROUND SUMMARY PANEL (7 second countdown) ===== */
function showRoundSummary(result) {
    var winner = String(result.winner || "").toLowerCase();
    var hands = result.hands || {};
    var ranks = result.ranks || {};
    var payouts = result.user_payouts || {};
    var payoutEntry = payouts[String(currentUserId)] || null;

    var roundNum = result.round || currentRound;
    var compactSummaryTitle = window.matchMedia("(max-width: 360px), (max-height: 620px)").matches;
    $("#tpSummaryTitle").text(
        compactSummaryTitle
            ? "Round #" + roundNum + " Summary"
            : "Round #" + roundNum + " - Complete Summary"
    );

    // Sort: winner first
    var sortedSides = SIDES.slice().sort(function (a, b) {
        if (a === winner) return -1;
        if (b === winner) return 1;
        return 0;
    });

    var html = "";

    // Header row
    html += '<div class="tp-summary-header">';
    html += '<div class="tp-summary-header-text">Who got what? Let\'s see...</div>';
    html += '</div>';

    sortedSides.forEach(function (side) {
        var cap = sideCap(side);
        var isWinner = side === winner;
        var sideCards = Array.isArray(hands[side]) ? hands[side] : [];
        var sideRank = ranks[side] || "--";
        var handClass = isWinner ? "winner-hand" : "loser-hand";

        html += '<div class="tp-summary-hand ' + handClass + '">';

        // Avatar
        html += '<div class="tp-summary-avatar s-' + side + '">';
        html += '<img src="' + avatarAsset(side + '_character.png') + '" alt="' + cap + '">';
        html += '</div>';

        // Cards — CSS-drawn faces, no image loading
        html += '<div class="tp-summary-cards">';
        for (var c = 0; c < 3; c++) {
            var card = sideCards[c] || "BACK";
            var code = normalizeCardCode(card);
            if (code) {
                html += '<div class="tp-summary-card-css">' + createCardFaceHTML(code) + '</div>';
            } else {
                html += '<div class="tp-summary-card-css"><div class="tp-cf blk"></div></div>';
            }
        }
        html += '</div>';

        // Info
        html += '<div class="tp-summary-info">';
        html += '<div class="tp-summary-side-name" style="color:' + SIDE_COLORS[side] + '">' + cap + '</div>';
        html += '<div class="tp-summary-rank">' + sideRank + '</div>';
        html += '<div class="tp-summary-card-names">' + buildHandText(sideCards) + '</div>';
        html += '</div>';

        html += '</div>';
    });

    // Winner announcement
    html += '<div class="tp-summary-winner-announce">';
    html += '<div class="announce-icon">&#127942;</div>';
    html += '<div class="announce-text">' + SIDE_LABELS[winner] + ' WINS with ' + (ranks[winner] || "High Card") + '!</div>';
    html += '</div>';

    // User payout info
    var hasOwnBet = SIDES.some(function (side) { return safeAmount(latestMyBets[side]) > 0; });
    if (payoutEntry) {
        var payout = safeAmount(payoutEntry.payout);
        html += '<div class="tp-summary-payout win">';
        html += '<div class="payout-label">Your Winnings</div>';
        html += '<div class="payout-amount">+' + formatBetAmount(payout) + '</div>';
        html += '</div>';
    } else if (hasOwnBet) {
        html += '<div class="tp-summary-payout loss">';
        html += '<div class="payout-label">Result</div>';
        html += '<div class="payout-amount loss-text">Better luck next round!</div>';
        html += '</div>';
    }

    $("#tpSummaryHands").html(html);
    // CRITICAL FIX: Remove any stale display:none before adding show class
    $("#tpRoundSummary").css("display", "").addClass("show");

    // Show time remaining until next round (server-driven, not fixed 7s)
    var serverRemaining = Math.max(0, countdownSeconds);
    $("#tpNextRoundCountdown").text(serverRemaining);

    if (summaryCountdownTimer) clearInterval(summaryCountdownTimer);
    summaryCountdownTimer = setInterval(function () {
        var remaining = Math.max(0, countdownSeconds);
        $("#tpNextRoundCountdown").text(remaining);
        // Don't auto-close here — let updateUI handle the round transition
        // This just updates the displayed countdown number
        if (remaining <= 0) {
            clearInterval(summaryCountdownTimer);
            summaryCountdownTimer = null;
        }
    }, 500);
}

function closeSummaryAndReset() {
    $("#tpRoundSummary").removeClass("show");
    // CRITICAL FIX: Use css("display","") instead of .hide() which sets display:none permanently
    setTimeout(function () {
        $("#tpRoundSummary").css("display", "");
    }, 500);
    $(".tp-timer-section").removeClass("hide-timer");
    hideNarration();

    if (summaryCountdownTimer) {
        clearInterval(summaryCountdownTimer);
        summaryCountdownTimer = null;
    }

    isDealingInProgress = false;
    dealingStartedAt = 0;
    dealingForRound = null;

    $(".tp-col").removeClass("round-winner receiving");
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

function createCardImg(card) {
    var code = normalizeCardCode(card);
    var img = $('<img class="tp-card-img anime-flip" alt="card">');

    if (!code) {
        img.attr("src", cardBackImage);
        return img;
    }

    var candidates = [
        imagePath + code + ".png",
        imagePath + code.replace("-", "") + ".png",
        imagePath + code.replace("-", "_") + ".png"
    ];
    var idx = 0;

    var applyNext = function () {
        if (idx >= candidates.length) {
            img.off("error", applyNext);
            img.attr("src", cardBackImage);
            img.attr("title", code);
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

    var winnerImage = avatarAsset(winner + "_character.png");
    $("#tpWinnerImg")
        .off("error")
        .on("error", function () {
            $(this).attr("src", avatarAsset("gold_character.png"));
        })
        .attr("src", winnerImage);

    $("#tpWinnerRoundTitle").text("Round #" + (result.round || currentRound) + " \u2022 " + winnerLabel + " Won!");

    var payouts = result && result.user_payouts ? result.user_payouts : {};
    var payoutEntry = payouts[String(currentUserId)] || null;
    var hasOwnBet = SIDES.some(function (side) { return safeAmount(latestMyBets[side]) > 0; });

    if (payoutEntry) {
        var payout = safeAmount(payoutEntry.payout);
        $("#tpWinnerStatusMsg")
            .removeClass("lost")
            .text("You won " + formatBetAmount(payout) + " on " + winnerLabel + "!");
        $("#tpWinVal").text(formatBetAmount(payout));
        playTpSound("win.wav");
        if (typeof triggerFantasyWin === "function") triggerFantasyWin();
    } else if (hasOwnBet) {
        $("#tpWinnerStatusMsg")
            .addClass("lost")
            .text(winnerLabel + " won this round. Better luck next round.");
        playTpSound("lose.wav");
    } else {
        $("#tpWinnerStatusMsg")
            .addClass("lost")
            .text("No bet placed. " + winnerLabel + " won this round.");
        playTpSound("lose.wav");
    }

    $("#tpWinnerModal").css("display", "flex");
}

function closeWinnerModal() {
    $("#tpWinnerModal").hide();
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

    // Close the round summary if it's still showing
    if ($("#tpRoundSummary").hasClass("show")) {
        closeSummaryAndReset();
        return; // closeSummaryAndReset already resets everything
    }

    isBettingEnabled = true;
    closeWinnerModal();
    resetRoundUI();
    startAmbientRain();
    setPhaseBadge("betting");
}

function resetRoundUI() {
    $(".tp-hand-rank").text("--").css({ background: "rgba(0,0,0,0.6)", color: "var(--tp-gold)", fontWeight: "" });
    $(".tp-col").removeClass("round-winner receiving");
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

function placeGlobalBet(choose) {
    var amount = safeAmount(selectedChip);
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
    var html = "";
    history.forEach(function (item) {
        // item can be an object {round, winner, ...} or a plain string
        var side = typeof item === "object" ? String(item.winner || "") : String(item || "");
        side = side.toLowerCase();
        var char = side.charAt(0).toUpperCase();
        var cls = "dot-" + char.toLowerCase();
        html += '<div class="tp-hist-dot ' + cls + '" title="' + (typeof item === "object" ? "Round #" + item.round : "") + '">' + char + "</div>";
    });
    $("#tpHistory").html(html);
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

    // Dense multi-chip rain: 3 tiers of intervals for layered effect
    var amounts = [400, 400, 400, 400, 2000, 2000, 4000, 10000];

    function rainTick() {
        if (!isBettingEnabled) return;
        var rnd = Math.random();
        var count = rnd < 0.55 ? 1 : (rnd < 0.82 ? 2 : 3); // 55% single, 27% double, 18% triple

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

    rainTick(); // fire immediately
    ambientRainInterval = setInterval(rainTick, 220);
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
    var targetTop = (Math.random() * 52 + 12).toFixed(2) + "%";

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
        left: (Math.random() * 68 + 16) + "%",
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
    var shapes  = ["", "circle", "strip"];

    for (var i = 0; i < 72; i++) {
        (function (delay) {
            setTimeout(function () {
                var p = document.createElement("div");
                p.className = "tp-confetti " + shapes[Math.floor(Math.random() * shapes.length)];
                var color = palette[Math.floor(Math.random() * palette.length)];
                var cx    = (Math.random() * 200 - 100).toFixed(1);
                var cy    = (Math.random() * 240 + 80).toFixed(1);
                var cr    = (Math.random() * 720 - 360).toFixed(1);
                var dur   = (Math.random() * 0.6 + 0.65).toFixed(3);
                p.style.cssText =
                    "left:" + (Math.random() * 92 + 4) + "%" +
                    ";top:" + (Math.random() * 35 + 5) + "%" +
                    ";background:" + color +
                    ";--cx:" + cx + "px" +
                    ";--cy:" + cy + "px" +
                    ";--cr:" + cr + "deg" +
                    ";--confetti-dur:" + dur + "s";
                wrapper.appendChild(p);
                setTimeout(function () { p.remove(); }, 2000);
            }, delay);
        })(Math.random() * 900);
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
var histPanelLoaded = false;

function openHistoryPanel() {
    $("#tpHistBackdrop, #tpHistPanel").addClass("open");
    if (!histPanelLoaded) loadHistoryPanel();
}

function closeHistoryPanel() {
    $("#tpHistBackdrop, #tpHistPanel").removeClass("open");
}

function loadHistoryPanel() {
    var url = (typeof historyUrl !== "undefined" && historyUrl) ? historyUrl : null;
    if (!url && typeof syncUrl !== "undefined" && syncUrl) {
        url = String(syncUrl)
            .replace("/global/sync/", "/history/")
            .replace("/global/sync", "/history");
    }
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
            histPanelLoaded = true;
            var rows = Array.isArray(data && data.history) ? data.history : [];
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
            histPanelLoaded = false;
            $("#tpHistBody").html('<div class="tp-hist-empty">Failed to load history.</div>');
        }
    });
}
