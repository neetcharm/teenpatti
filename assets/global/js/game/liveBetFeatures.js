"use strict";

(function ($) {
    if (!$) {
        return;
    }

    const config = window.liveGameConfig || {};
    if (!config.isLiveGame) {
        return;
    }

    const state = {
        autoBetEnabled: false,
        lastPayload: null,
        autoBetTimer: null,
        statsTimer: null,
        historyBuffer: [],
    };

    function normalizeUrl(url) {
        if (!url) {
            return "";
        }

        return String(url).split("?")[0].replace(/\/+$/, "");
    }

    function isMatchingUrl(requestUrl, expectedUrl) {
        return normalizeUrl(requestUrl) === normalizeUrl(expectedUrl);
    }

    function getInvestUrl() {
        if (typeof investUrl !== "undefined") {
            return investUrl;
        }

        return window.investUrl;
    }

    function getGameEndUrl() {
        if (typeof gameEndUrl !== "undefined") {
            return gameEndUrl;
        }

        return window.gameEndUrl;
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function statusClass(status) {
        if (status === "win") return "text-success";
        if (status === "loss") return "text-danger";
        return "text-warning";
    }

    function statusText(status) {
        if (!status) return "RUNNING";
        return String(status).toUpperCase();
    }

    function appendStyle() {
        if (document.getElementById("liveFeatureStyle")) {
            return;
        }

        const style = document.createElement("style");
        style.id = "liveFeatureStyle";
        style.textContent = `
            .live-feature-shell{margin-top:18px;padding:14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.25)}
            .live-feature-head{display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;margin-bottom:10px}
            .live-feature-section-title{font-size:13px;font-weight:700;margin:10px 0 8px}
            .live-feature-list{max-height:200px;overflow:auto;margin:0;padding:0;list-style:none}
            .live-feature-list li{border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px 10px;margin-bottom:8px;font-size:12px}
            .live-feature-list li:last-child{margin-bottom:0}
            .live-feature-row{display:flex;justify-content:space-between;gap:8px}
            .live-autobet-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
            .live-autobet-tools input[type=number]{max-width:95px}
            .live-autobet-note{font-size:11px;opacity:.75}
        `;

        document.head.appendChild(style);
    }

    function findWidgetAnchor() {
        return document.querySelector(".game-details-right, .headtail-wrapper");
    }

    function buildWidget() {
        const anchor = findWidgetAnchor();
        if (!anchor || document.getElementById("liveFeatureWidget")) {
            return;
        }

        const canAutoBet = !!config.enableExternalAutoBet;
        const autoBetMarkup = canAutoBet ? `
            <div class="live-autobet-tools">
                <label class="mb-0">
                    <input type="checkbox" id="liveAutoBetToggle">
                    Auto Bet
                </label>
                <label class="mb-0">Delay
                    <input type="number" min="5" value="${Number(config.roundInterval || 15)}" id="liveAutoBetDelay" class="form-control form-control-sm d-inline-block">
                </label>
            </div>
            <div id="liveAutoBetStatus" class="live-autobet-note">Auto bet is OFF</div>
        ` : `
            <div class="live-autobet-note mb-2">Auto bet is controlled by this game panel.</div>
        `;

        const widget = document.createElement("div");
        widget.id = "liveFeatureWidget";
        widget.className = "live-feature-shell";
        widget.innerHTML = `
            <div class="live-feature-head">
                <strong>Live Global Stats</strong>
                <small id="liveFeatureRefresh">updating...</small>
            </div>
            ${autoBetMarkup}
            <div class="live-feature-section-title">Highest Wins</div>
            <ul id="liveFeatureHighestList" class="live-feature-list"></ul>
            <div class="live-feature-section-title">Recent Bet History</div>
            <ul id="liveFeatureStatsList" class="live-feature-list"></ul>
        `;

        anchor.insertAdjacentElement("afterend", widget);
    }

    function updateAutoBetStatus(text) {
        const node = document.getElementById("liveAutoBetStatus");
        if (node) {
            node.textContent = text;
        }
    }

    function snapshotPayload() {
        const form = document.getElementById("game");
        if (!form) {
            return;
        }

        state.lastPayload = $(form).serialize();
    }

    function clearAutoBetTimer() {
        if (state.autoBetTimer) {
            clearTimeout(state.autoBetTimer);
            state.autoBetTimer = null;
        }
    }

    function placeAutoBet() {
        if (!config.enableExternalAutoBet || !state.autoBetEnabled || !state.lastPayload) {
            return false;
        }

        if (typeof window.isRequest !== "undefined" && window.isRequest) {
            return false;
        }

        if (typeof window.playGame === "function") {
            window.playGame(state.lastPayload, config.autoBetMusic || "coin.mp3");
            return true;
        }

        if (typeof window.game === "function") {
            if (typeof window.beforeProcess === "function") {
                window.beforeProcess();
            }
            window.game(state.lastPayload);
            return true;
        }

        return false;
    }

    function scheduleAutoBet(overrideDelay) {
        if (!config.enableExternalAutoBet || !state.autoBetEnabled || !state.lastPayload) {
            return;
        }

        clearAutoBetTimer();

        const configuredDelay = Number($("#liveAutoBetDelay").val() || config.roundInterval || 15);
        const delay = Math.max(2, Number(overrideDelay || configuredDelay));
        updateAutoBetStatus("Next auto bet in " + delay + "s");

        state.autoBetTimer = setTimeout(function () {
            if (!state.autoBetEnabled) {
                return;
            }

            const placed = placeAutoBet();
            if (placed) {
                updateAutoBetStatus("Auto bet placed");
            } else {
                updateAutoBetStatus("Waiting for round to close...");
                scheduleAutoBet(2);
            }
        }, delay * 1000);
    }

    function mergeHistory(incomingItems) {
        const incoming = Array.isArray(incomingItems) ? incomingItems : [];
        const merged = incoming.concat(state.historyBuffer);
        const unique = [];
        const seen = {};

        merged.forEach(function (item) {
            if (!item || typeof item.id === "undefined" || seen[item.id]) {
                return;
            }

            seen[item.id] = true;
            unique.push(item);
        });

        state.historyBuffer = unique.slice(0, 35);
        return state.historyBuffer;
    }

    function renderRecentStats(stats) {
        const list = document.getElementById("liveFeatureStatsList");
        if (!list) {
            return;
        }

        const items = mergeHistory(stats);
        if (!items.length) {
            list.innerHTML = `<li class="text-muted">No history yet</li>`;
            return;
        }

        const html = items.map(function (item) {
            return `
                <li>
                    <div class="live-feature-row">
                        <strong>${escapeHtml(item.user)}</strong>
                        <span class="${statusClass(item.status)}">${escapeHtml(statusText(item.status))}</span>
                    </div>
                    <div class="live-feature-row mt-1">
                        <span>${escapeHtml(item.game || "-")}</span>
                        <span>${escapeHtml(item.invest || "0")} ${escapeHtml(config.currencyText || "")}</span>
                    </div>
                    <div class="live-feature-row mt-1 text-muted">
                        <span>${escapeHtml(item.result || "-")}</span>
                        <span>${escapeHtml(item.created_human || "")}</span>
                    </div>
                </li>
            `;
        }).join("");

        list.innerHTML = html;
    }

    function renderHighestStats(highestStats) {
        const list = document.getElementById("liveFeatureHighestList");
        if (!list) {
            return;
        }

        const items = Array.isArray(highestStats) ? highestStats : [];
        if (!items.length) {
            list.innerHTML = `<li class="text-muted">No win records yet</li>`;
            return;
        }

        const html = items.map(function (item) {
            return `
                <li>
                    <div class="live-feature-row">
                        <strong>${escapeHtml(item.user)}</strong>
                        <span class="text-success">${escapeHtml(item.win_amount || "0")} ${escapeHtml(config.currencyText || "")}</span>
                    </div>
                    <div class="live-feature-row mt-1">
                        <span>${escapeHtml(item.game || "-")}</span>
                        <span>${escapeHtml(Number(item.multiplier || 0).toFixed(2))}x</span>
                    </div>
                    <div class="live-feature-row mt-1 text-muted">
                        <span>Bet: ${escapeHtml(item.invest || "0")} ${escapeHtml(config.currencyText || "")}</span>
                        <span>${escapeHtml(item.created_human || "")}</span>
                    </div>
                </li>
            `;
        }).join("");

        list.innerHTML = html;
    }

    function buildStatsEndpoint() {
        if (!config.statsEndpoint) {
            return "";
        }

        try {
            const endpoint = new URL(config.statsEndpoint, window.location.origin);
            endpoint.searchParams.set("history_limit", "25");
            endpoint.searchParams.set("highest_limit", "12");
            return endpoint.toString();
        } catch (_e) {
            return config.statsEndpoint;
        }
    }

    function fetchStats() {
        const endpoint = buildStatsEndpoint();
        if (!endpoint) {
            return;
        }

        $.getJSON(endpoint, function (response) {
            renderHighestStats(response.highest_stats || []);
            renderRecentStats(response.stats || []);

            const refresh = document.getElementById("liveFeatureRefresh");
            if (refresh) {
                refresh.textContent = response.refreshed_at || "updated";
            }
        });
    }

    function bindEvents() {
        $(document).on("submit", "#game", function () {
            state.lastPayload = $(this).serialize();
            clearAutoBetTimer();
        });

        $(document).on("input change", "#game input, #game select", function () {
            snapshotPayload();
        });

        $(document).on("change", "#liveAutoBetToggle", function () {
            state.autoBetEnabled = $(this).is(":checked");

            if (state.autoBetEnabled) {
                snapshotPayload();
                updateAutoBetStatus("Auto bet ON");
                scheduleAutoBet(1);
            } else {
                clearAutoBetTimer();
                updateAutoBetStatus("Auto bet is OFF");
            }
        });

        $(document).ajaxSuccess(function (_event, _xhr, settings, response) {
            if (isMatchingUrl(settings.url, getInvestUrl())) {
                if (response && response.game_log_id) {
                    state.lastPayload = settings.data || state.lastPayload;
                } else if (response && response.error && config.enableExternalAutoBet && state.autoBetEnabled) {
                    const errorMessage = String(response.error).toLowerCase();
                    if (errorMessage.indexOf("in-complete") !== -1 || errorMessage.indexOf("incomplete") !== -1) {
                        scheduleAutoBet(2);
                    }
                }
            }

            if (isMatchingUrl(settings.url, getGameEndUrl()) && response && typeof response === "object") {
                if (config.enableExternalAutoBet && state.autoBetEnabled) {
                    scheduleAutoBet();
                }
            }
        });
    }

    appendStyle();
    buildWidget();
    bindEvents();
    snapshotPayload();
    fetchStats();
    state.statsTimer = setInterval(fetchStats, 3000);
})(window.jQuery);
