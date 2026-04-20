<!DOCTYPE html>
<html lang="{{ $tenantSession->lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tenant->name }} - {{ $game->name }}</title>

    {{-- Universal Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Master SaaS Shell CSS */
        :root {
            --primary: #7c3aed;
            --surface: rgba(255,255,255,.04);
            --border: rgba(255,255,255,.08);
            --text-muted: rgba(255,255,255,.4);
        }
        body {
            margin: 0; padding: 0; background: #060612;
            color: #fff; font-family: 'Rajdhani', sans-serif;
            overflow: hidden; height: 100vh;
        }
        .saas-shell { display: flex; flex-direction: column; height: 100vh; }
        .saas-topbar {
            padding: 8px 16px; background: rgba(0,0,0,0.5);
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border); font-size: 13px;
        }
        .saas-header {
            padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(180deg, rgba(255,255,255,0.05) 0%, transparent 100%);
        }
        .saas-actions { display: flex; align-items: center; gap: 8px; }
        .game-container { flex: 1; position: relative; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .bal-val { color: #22c55e; font-weight: 800; }
        .btn-icon { background: none; border: none; color: #fff; font-size: 18px; cursor: pointer; padding: 5px; }
        .btn-topup {
            border: 1px solid rgba(255,255,255,.16);
            background: linear-gradient(180deg, rgba(36, 153, 122, .95), rgba(16, 96, 73, .95));
            color: #fff;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        
        /* Generic Loader */
        #loader {
            position: fixed; inset: 0; z-index: 9999; background: #060612;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .ring { width: 50px; height: 50px; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    @stack('game_style')

</head>
<body>

    <div id="loader">
        <div class="ring"></div>
        <div style="margin-top: 15px; font-size: 12px; letter-spacing: 2px;">LOADING {{ strtoupper($game->name) }}...</div>
    </div>

    <div class="saas-shell">
        <div class="saas-topbar">
            <span>{{ $tenantSession->player_name }}</span>
            <span><i class="fas fa-wallet" style="margin-right: 5px;"></i> <span class="bal-val">{{ number_format($tenantSession->balance_cache, 2) }}</span></span>
            <span style="opacity: 0.5; font-size: 10px;">{{ $tenant->name }}</span>
        </div>

        <div class="saas-header">
            <button class="btn-icon" onclick="exitGame()"><i class="fas fa-chevron-left"></i></button>
            <div style="font-family: 'Orbitron', sans-serif; font-weight: 900; font-size: 14px; letter-spacing: 1px;">{{ strtoupper($game->name) }}</div>
            <div class="saas-actions">
                @if(!empty($walletTopupUrl))
                    <button class="btn-topup" type="button" onclick="openWalletTopup()">Top Up</button>
                @endif
                <button class="btn-icon" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>

        <div class="game-container">
            @yield('game_content')
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        var walletTopupUrl = @json($walletTopupUrl ?? '');
        var walletRefreshUrl = @json($walletRefreshUrl ?? '');
        var walletContext = @json($walletContext ?? (object) []);
        var walletRefreshInFlight = false;
        var walletRefreshBurst = null;

        function exitGame() {
            // Callback to tenant if provided
            window.location.href = "{{ $tenant->callback_url }}";
        }

        function buildWalletTopupUrl() {
            if (!walletTopupUrl) return '';

            try {
                var targetUrl = new URL(walletTopupUrl, window.location.origin);

                if (walletContext.playerId) targetUrl.searchParams.set('player_id', walletContext.playerId);
                if (walletContext.playerName) targetUrl.searchParams.set('player_name', walletContext.playerName);
                if (walletContext.sessionToken) targetUrl.searchParams.set('session_token', walletContext.sessionToken);
                if (walletContext.gameId) targetUrl.searchParams.set('game_id', walletContext.gameId);
                if (walletContext.currency) targetUrl.searchParams.set('currency', walletContext.currency);
                if (walletContext.tenantId) targetUrl.searchParams.set('tenant_id', walletContext.tenantId);

                targetUrl.searchParams.set('source', 'game_shell');
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
            if (!walletRefreshUrl) return;

            stopWalletRefreshBurst();
            refreshWalletBalance();

            var attempts = 0;
            walletRefreshBurst = window.setInterval(function () {
                attempts += 1;
                refreshWalletBalance();

                if (attempts >= 8) {
                    stopWalletRefreshBurst();
                }
            }, 2500);
        }

        function openWalletTopup() {
            var targetUrl = buildWalletTopupUrl();
            if (!targetUrl) return;

            startWalletRefreshBurst();

            if (window.AndroidBridge && typeof window.AndroidBridge.openExternal === 'function') {
                window.AndroidBridge.openExternal(targetUrl);
                return;
            }

            window.location.href = targetUrl;
        }

        function refreshWalletBalance() {
            if (!walletRefreshUrl || walletRefreshInFlight) return;

            walletRefreshInFlight = true;

            $.ajax({
                url: walletRefreshUrl,
                type: 'GET',
                cache: false,
                timeout: 5000
            }).done(function(data) {
                if (data && typeof data.balance !== 'undefined') {
                    window.updateBalance(data.balance);
                }
            }).always(function() {
                walletRefreshInFlight = false;
            });
        }

        window.addEventListener('load', () => {
            setTimeout(() => {
                $('#loader').fadeOut();
            }, 800);
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                startWalletRefreshBurst();
            }
        });

        window.addEventListener('focus', startWalletRefreshBurst);

        // Global balance updater
        window.updateBalance = function(newBal) {
            $('.bal-val').text(parseFloat(newBal).toFixed(2));
        };
    </script>

    @stack('game_script')

</body>
</html>
