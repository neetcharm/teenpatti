/**
 * game-security.js
 *
 * Client-side security helper for game bet requests.
 *
 * What this does:
 *   1. Requests a server-signed Action Token before each bet.
 *      The token locks in the exact bet amount server-side.
 *      Even if Burp Suite / proxy modifies the request, the server
 *      uses the amount from the token — not from the request body.
 *
 *   2. Attaches a one-time Nonce + Timestamp to every request.
 *      The server validates these to prevent replay attacks.
 *
 * Usage:
 *   // At game init (inject CSRF token from Blade)
 *   const game = new GameSecurity(csrfToken);
 *
 *   // Before placing a bet
 *   const betParams = await game.bet('teen_patti', 'silver', 100);
 *   // betParams is ready to send to POST /api/play/invest/teen_patti
 */

class GameSecurity {
    constructor(csrfToken, baseUrl = '') {
        this.csrf    = csrfToken;
        this.baseUrl = baseUrl;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Request an action token from server, then return params ready for invest.
     *
     * @param {string} alias   - Game alias, e.g. "teen_patti"
     * @param {string} choose  - Chosen side, e.g. "silver"
     * @param {number} amount  - Bet amount (integer, must be a valid chip)
     * @returns {Promise<Object>} - Params to merge into your invest request
     * @throws {Error} if token issue fails (insufficient balance, invalid amount, etc.)
     */
    async bet(alias, choose, amount) {
        const tokenData = await this._issueToken(alias, 'bet', amount, choose);

        return {
            invest:       amount,        // server will ignore this and use token's amount
            choose:       choose,
            action_token: tokenData.token,
            _nonce:       this._nonce(),
            _ts:          this._ts(),
        };
    }

    /**
     * Build security params for a non-bet request (sync, end, etc.)
     */
    secureParams(extraParams = {}) {
        return {
            ...extraParams,
            _nonce: this._nonce(),
            _ts:    this._ts(),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    async _issueToken(alias, action, amount, choose) {
        const resp = await fetch(this.baseUrl + '/api/game/action-token', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrf,
                'Accept':       'application/json',
            },
            body: JSON.stringify({ alias, action, amount, choose }),
        });

        const data = await resp.json();

        if (!resp.ok) {
            throw new Error(data.error || 'Failed to get bet authorisation from server.');
        }

        return data; // { token, expires_in, amount }
    }

    _nonce() {
        // Cryptographically random nonce
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        const arr = new Uint8Array(16);
        crypto.getRandomValues(arr);
        return Array.from(arr, b => b.toString(16).padStart(2, '0')).join('');
    }

    _ts() {
        return Math.floor(Date.now() / 1000);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// jQuery AJAX prefilter — auto-attach _nonce + _ts to every POST
// ─────────────────────────────────────────────────────────────────────────────
// This covers any existing jQuery $.ajax / $.post calls in the game JS
// that don't use GameSecurity.bet() directly.

if (typeof $ !== 'undefined') {
    $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
        if (options.type && options.type.toUpperCase() === 'POST') {
            var ts    = Math.floor(Date.now() / 1000);
            var nonce = (typeof crypto !== 'undefined' && crypto.randomUUID)
                        ? crypto.randomUUID()
                        : Math.random().toString(36).substr(2) + ts;

            if (typeof options.data === 'string') {
                options.data += '&_nonce=' + encodeURIComponent(nonce) + '&_ts=' + ts;
            } else if (typeof options.data === 'object' && options.data !== null) {
                options.data._nonce = nonce;
                options.data._ts    = ts;
            }
        }
    });
}
