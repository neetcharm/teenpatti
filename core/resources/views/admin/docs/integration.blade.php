@extends('admin.layouts.app')

@push('style')
<style>
/* ── Documentation Styles ──────────────────────────────────────── */
.doc-sidebar {
    position: sticky; top: 80px; max-height: calc(100vh - 100px);
    overflow-y: auto; padding-right: 6px;
}
.doc-sidebar::-webkit-scrollbar { width: 4px; }
.doc-sidebar::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

.doc-nav .nav-link {
    font-size: .82rem; padding: 5px 12px; color: #64748b;
    border-left: 2px solid transparent; border-radius: 0;
    transition: color .15s, border-color .15s;
}
.doc-nav .nav-link:hover, .doc-nav .nav-link.active {
    color: #4f46e5; border-left-color: #4f46e5;
    background: rgba(79,70,229,.05);
}
.doc-nav .nav-section {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #94a3b8; padding: 12px 12px 4px;
}

.doc-section { scroll-margin-top: 80px; margin-bottom: 48px; }
.doc-section h2 {
    font-size: 1.35rem; font-weight: 800; padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
}
.doc-section h3 { font-size: 1.05rem; font-weight: 700; margin: 24px 0 10px; color: #1e293b; }
.doc-section h4 { font-size: .9rem; font-weight: 700; margin: 16px 0 6px; color: #374151; }

/* Code blocks */
pre.code-block {
    background: #0f172a; border-radius: 10px; padding: 18px 20px;
    font-size: .8rem; line-height: 1.7; overflow-x: auto;
    margin: 10px 0; border: 1px solid #1e293b;
    position: relative;
}
pre.code-block code { color: #e2e8f0; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; }

/* Syntax highlight helpers */
.kw  { color: #c084fc; } /* keyword */
.st  { color: #86efac; } /* string */
.cm  { color: #64748b; font-style: italic; } /* comment */
.nm  { color: #7dd3fc; } /* name/variable */
.nb  { color: #fda4af; } /* number */
.fn  { color: #fbbf24; } /* function */
.op  { color: #94a3b8; } /* operator */

.copy-btn {
    position: absolute; top: 10px; right: 10px;
    padding: 3px 10px; border-radius: 5px; font-size: .7rem;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: rgba(255,255,255,.7); cursor: pointer; transition: background .15s;
}
.copy-btn:hover { background: rgba(255,255,255,.2); }

/* Endpoint badge */
.ep-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 16px; border-radius: 8px;
    font-family: monospace; font-size: .85rem;
    background: #f8fafc; border: 1px solid #e2e8f0;
    margin-bottom: 12px;
}
.method-post { background: #ecfdf5; color: #059669; font-weight: 800; padding: 2px 8px; border-radius: 4px; font-size: .8rem; }
.method-get  { background: #eff6ff; color: #2563eb; font-weight: 800; padding: 2px 8px; border-radius: 4px; font-size: .8rem; }

/* Table */
.doc-table { font-size: .82rem; }
.doc-table th { background: #f8fafc; font-weight: 700; white-space: nowrap; }
.doc-table td code { background: #f1f5f9; padding: 2px 5px; border-radius: 3px; font-size: .78rem; color: #4f46e5; }

/* Alert boxes */
.doc-note {
    padding: 12px 16px; border-radius: 8px; font-size: .83rem; margin: 12px 0;
    border-left: 4px solid;
}
.doc-note.info    { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
.doc-note.success { background: #f0fdf4; border-color: #22c55e; color: #166534; }
.doc-note.warning { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
.doc-note.danger  { background: #fef2f2; border-color: #ef4444; color: #991b1b; }

/* Step cards */
.step-card {
    display: flex; gap: 16px; padding: 16px; border-radius: 10px;
    background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 12px;
}
.step-num {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    background: #4f46e5; color: #fff; font-weight: 800;
    display: flex; align-items: center; justify-content: center; font-size: .85rem;
}
.step-body h5 { font-size: .9rem; font-weight: 700; margin: 0 0 4px; }
.step-body p  { font-size: .82rem; color: #64748b; margin: 0; }

/* Language tabs */
.lang-tabs .nav-link { font-size: .78rem; padding: 5px 12px; }
</style>
@endpush

@section('panel')

<div class="row">

    {{-- Sidebar Navigation --}}
    <div class="col-lg-2 d-none d-lg-block">
        <nav class="doc-sidebar">
            <ul class="nav flex-column doc-nav">
                <li class="nav-section">Overview</li>
                <li><a class="nav-link" href="#intro">Introduction</a></li>
                <li><a class="nav-link" href="#architecture">Architecture</a></li>
                <li><a class="nav-link" href="#quickstart">Quick Start</a></li>

                <li class="nav-section">Authentication</li>
                <li><a class="nav-link" href="#credentials">API Credentials</a></li>
                <li><a class="nav-link" href="#signing">Request Signing</a></li>

                <li class="nav-section">API Reference</li>
                <li><a class="nav-link" href="#session-create">Create Session</a></li>
                <li><a class="nav-link" href="#session-close">Close Session</a></li>
                <li><a class="nav-link" href="#session-status">Session Status</a></li>
                <li><a class="nav-link" href="#balance-refresh">Refresh Balance</a></li>

                <li class="nav-section">Integration</li>
                <li><a class="nav-link" href="#android">Android WebView</a></li>
                <li><a class="nav-link" href="#ios">iOS WKWebView</a></li>
                <li><a class="nav-link" href="#web">Web iFrame</a></li>
                <li><a class="nav-link" href="#webhook">Webhook Setup</a></li>

                <li class="nav-section">Balance Modes</li>
                <li><a class="nav-link" href="#internal-mode">Internal Mode</a></li>
                <li><a class="nav-link" href="#webhook-mode">Webhook Mode</a></li>

                <li class="nav-section">Database</li>
                <li><a class="nav-link" href="#separate-db">Separate DB Setup</a></li>

                <li class="nav-section">Security</li>
                <li><a class="nav-link" href="#security">Security Guide</a></li>
                <li><a class="nav-link" href="#errors">Error Codes</a></li>
            </ul>
        </nav>
    </div>

    {{-- Main Content --}}
    <div class="col-lg-10">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="mb-1">Integration Documentation</h3>
                <p class="text-muted mb-0">Complete guide to integrating our gaming platform into your app or website</p>
            </div>
            <span class="badge bg--primary px-3 py-2">API v1</span>
        </div>

        {{-- ── INTRODUCTION ──────────────────────────────────────────────── --}}
        <div class="doc-section" id="intro">
            <h2><i class="las la-book-open text-primary"></i> Introduction</h2>

            <p>This platform allows you to embed premium casino games (Teen Patti, Roulette, Crash, Spin, and 25+ more)
            directly into your mobile app or website via a simple API. Your players play inside a <strong>WebView</strong>
            hosted on our servers — you only manage authentication and balances.</p>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <div class="fs-2 mb-2">🔑</div>
                            <h6 class="fw-bold">API Key Auth</h6>
                            <p class="text-muted small mb-0">HMAC-SHA256 signed requests prevent replay attacks</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <div class="fs-2 mb-2">📱</div>
                            <h6 class="fw-bold">WebView Ready</h6>
                            <p class="text-muted small mb-0">One URL to open in Android, iOS, or web iFrame</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body text-center">
                            <div class="fs-2 mb-2">⚡</div>
                            <h6 class="fw-bold">Two Balance Modes</h6>
                            <p class="text-muted small mb-0">Internal DB (instant) or your own wallet via webhook</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── ARCHITECTURE ──────────────────────────────────────────────── --}}
        <div class="doc-section" id="architecture">
            <h2><i class="las la-project-diagram text-primary"></i> Architecture</h2>

            <pre class="code-block"><code><span class="cm">┌─────────────────┐         ┌──────────────────────────────────────────┐
│  Your App/Server│         │           Our Game Platform               │
│                 │         │                                            │
│  1. POST        │────────▶│  /api/v1/session/create                   │
│     X-API-Key   │         │  → Validates tenant + HMAC signature      │
│     X-Signature │         │  → Creates TenantSession record            │
│     JSON body   │◀────────│  ← Returns { data: { game_url, token } }  │
│                 │         │                                            │
│  2. Open        │         │                                            │
│     game_url    │────────▶│  /play?token={session_token}              │
│     in WebView  │         │  → Authenticates player                    │
│                 │◀────────│  ← Renders game UI (full-screen WebView)  │
│                 │         │                                            │
│  3. Webhook     │◀────────│  POST your_webhook_url  (webhook mode)    │
│     (optional)  │         │  → Notifies balance/debit/credit events   │
└─────────────────┘         └──────────────────────────────────────────┘</span></code></pre>

            <h3>Balance Modes</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-header bg-success bg-opacity-10 fw-bold">
                            <i class="las la-database"></i> Internal Mode <span class="badge bg-success ms-1">Recommended</span>
                        </div>
                        <div class="card-body small">
                            <ul class="mb-0 ps-3">
                                <li>Balances stored in our DB</li>
                                <li>You top-up/deduct via Tenant Panel</li>
                                <li>Zero latency — no HTTP round-trips</li>
                                <li>Works offline (no webhook needed)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-header bg-primary bg-opacity-10 fw-bold">
                            <i class="las la-exchange-alt"></i> Webhook Mode
                        </div>
                        <div class="card-body small">
                            <ul class="mb-0 ps-3">
                                <li>Balances live in your wallet system</li>
                                <li>We call your webhook for every bet/win</li>
                                <li>Full control over player funds</li>
                                <li>Requires a publicly reachable webhook URL</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── QUICK START ───────────────────────────────────────────────── --}}
        <div class="doc-section" id="quickstart">
            <h2><i class="las la-rocket text-primary"></i> Quick Start (5 Minutes)</h2>

            <div class="step-card">
                <div class="step-num">1</div>
                <div class="step-body">
                    <h5>Get your API credentials from Admin</h5>
                    <p>Admin → Game Provider → Tenants → Create Tenant. Copy the <strong>API Key</strong> and <strong>Webhook Secret</strong>.</p>
                </div>
            </div>
            <div class="step-card">
                <div class="step-num">2</div>
                <div class="step-body">
                    <h5>Create a game session from your server</h5>
                    <p>Call <code>POST /api/v1/session/create</code> with signed headers. Get back a <code>game_url</code>.</p>
                </div>
            </div>
            <div class="step-card">
                <div class="step-num">3</div>
                <div class="step-body">
                    <h5>Open game_url in your WebView</h5>
                    <p>One line of code in Android/iOS/Web. The player sees the full-screen game UI.</p>
                </div>
            </div>
            <div class="step-card">
                <div class="step-num">4</div>
                <div class="step-body">
                    <h5>Manage player tokens (Internal mode)</h5>
                    <p>Top up player tokens from the Tenant Panel. No webhook needed.</p>
                </div>
            </div>
        </div>

        {{-- ── CREDENTIALS ───────────────────────────────────────────────── --}}
        <div class="doc-section" id="credentials">
            <h2><i class="las la-key text-primary"></i> API Credentials</h2>

            <table class="table doc-table">
                <thead><tr><th>Field</th><th>Description</th><th>Where to find</th></tr></thead>
                <tbody>
                    <tr><td><code>api_key</code></td><td>Your public identifier (starts with <code>tp_</code>)</td><td>Admin → Tenants list → API Key column</td></tr>
                    <tr><td><code>api_secret</code> / <code>webhook_secret</code></td><td>Same signing secret used for HMAC — <strong>keep server-side only</strong></td><td>Tenant Panel → Settings &amp; API (after tenant login)</td></tr>
                    <tr><td><code>panel_email</code></td><td>Tenant panel login email</td><td>Shown once at creation</td></tr>
                    <tr><td><code>panel_password</code></td><td>Tenant panel login password</td><td>Shown once at creation</td></tr>
                </tbody>
            </table>

            <div class="doc-note warning">
                <strong>Security:</strong> Never expose <code>api_secret</code> / <code>webhook_secret</code> in client-side code, Android APK, or iOS binary.
                All session creation must happen on your <strong>backend server</strong>.
            </div>
        </div>

        {{-- ── SIGNING ───────────────────────────────────────────────────── --}}
        <div class="doc-section" id="signing">
            <h2><i class="las la-signature text-primary"></i> Request Signing</h2>
            <p>Every session creation request must include an HMAC-SHA256 signature to prevent forgery.</p>

            <h3>Signature Formula</h3>
            <div class="doc-note info">
                Required auth headers: <code>X-API-Key</code> and <code>X-Signature</code>.<br>
                <code>X-Signature = HMAC-SHA256(api_secret, raw_json_body)</code>
            </div>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">// PHP example (recommended)</span>
<span class="nm">$apiKey</span>    <span class="op">=</span> <span class="st">'tp_your_api_key_here'</span>;
<span class="nm">$apiSecret</span> <span class="op">=</span> <span class="st">'your_api_secret_here'</span>;

<span class="nm">$payload</span> <span class="op">=</span> [
    <span class="st">'player_id'</span>   <span class="op">=></span> <span class="st">'user_12345'</span>,
    <span class="st">'player_name'</span> <span class="op">=></span> <span class="st">'Rahul Kumar'</span>,
    <span class="st">'game_id'</span>     <span class="op">=></span> <span class="st">'teen_patti'</span>,
    <span class="st">'currency'</span>    <span class="op">=></span> <span class="st">'INR'</span>,
];

<span class="nm">$rawJson</span>   <span class="op">=</span> <span class="fn">json_encode</span>(<span class="nm">$payload</span>, <span class="nm">JSON_UNESCAPED_SLASHES</span>);
<span class="nm">$signature</span> <span class="op">=</span> <span class="fn">hash_hmac</span>(<span class="st">'sha256'</span>, <span class="nm">$rawJson</span>, <span class="nm">$apiSecret</span>);

<span class="cm">// Optional anti-replay headers (canonical mode)</span>
<span class="nm">$timestamp</span> <span class="op">=</span> <span class="fn">time</span>();
<span class="nm">$nonce</span>     <span class="op">=</span> <span class="fn">bin2hex</span>(<span class="fn">random_bytes</span>(<span class="nb">16</span>));</code></pre>
        </div>

        {{-- ── SESSION CREATE ────────────────────────────────────────────── --}}
        <div class="doc-section" id="session-create">
            <h2><i class="las la-play-circle text-primary"></i> Create Game Session</h2>

            <div class="ep-badge"><span class="method-post">POST</span> {{ url('api/v1/session/create') }}</div>
            <div class="doc-note info">Legacy alias: <code>{{ url('api/v1/game/session') }}</code> maps to the same handler.</div>

            <h3>Request Headers</h3>
            <table class="table doc-table">
                <thead><tr><th>Header</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>X-API-Key</code></td><td>✓</td><td>Your tenant API key</td></tr>
                    <tr><td><code>X-Signature</code></td><td>✓</td><td><code>HMAC-SHA256(api_secret, raw_json_body)</code></td></tr>
                    <tr><td><code>X-Timestamp</code></td><td>Optional</td><td>Unix timestamp (required only for canonical anti-replay mode)</td></tr>
                    <tr><td><code>X-Nonce</code></td><td>Optional</td><td>Unique request nonce (required with <code>X-Timestamp</code>)</td></tr>
                </tbody>
            </table>

            <h3>Request Body</h3>
            <table class="table doc-table">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>player_id</code></td><td>string</td><td>✓</td><td>Your unique player identifier (max 100 chars)</td></tr>
                    <tr><td><code>player_name</code></td><td>string</td><td>✓</td><td>Display name shown in game (max 100 chars)</td></tr>
                    <tr><td><code>game_id</code></td><td>string</td><td>✓</td><td>Game alias (example: <code>teen_patti</code>). See game list below.</td></tr>
                    <tr><td><code>currency</code></td><td>string</td><td>✓</td><td>3-letter currency code (example: <code>INR</code>)</td></tr>
                </tbody>
            </table>

            <h3>Response</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code>{
  <span class="st">"success"</span>: <span class="kw">true</span>,
  <span class="st">"data"</span>: {
    <span class="st">"session_token"</span>: <span class="st">"abc123...64chartoken"</span>,
    <span class="st">"game_url"</span>: <span class="st">"{{ url('play') }}?token=abc123...64chartoken"</span>,
    <span class="st">"expires_at"</span>: <span class="st">"2026-04-10T15:30:00+05:30"</span>
  }
}</code></pre>

            <h3>Full Example (PHP)</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="kw">use</span> <span class="nm">Illuminate\Support\Facades\Http</span>;

<span class="nm">$apiKey</span>    <span class="op">=</span> <span class="st">'tp_your_api_key_here'</span>;
<span class="nm">$apiSecret</span> <span class="op">=</span> <span class="st">'your_api_secret_here'</span>;

<span class="nm">$payload</span> <span class="op">=</span> [
    <span class="st">'player_id'</span>   <span class="op">=></span> <span class="st">'user_12345'</span>,
    <span class="st">'player_name'</span> <span class="op">=></span> <span class="st">'Rahul Kumar'</span>,
    <span class="st">'game_id'</span>     <span class="op">=></span> <span class="st">'teen_patti'</span>,
    <span class="st">'currency'</span>    <span class="op">=></span> <span class="st">'INR'</span>,
];

<span class="nm">$rawJson</span>   <span class="op">=</span> <span class="fn">json_encode</span>(<span class="nm">$payload</span>, <span class="nm">JSON_UNESCAPED_SLASHES</span>);
<span class="nm">$signature</span> <span class="op">=</span> <span class="fn">hash_hmac</span>(<span class="st">'sha256'</span>, <span class="nm">$rawJson</span>, <span class="nm">$apiSecret</span>);

<span class="nm">$response</span> <span class="op">=</span> <span class="fn">Http</span>::<span class="fn">withHeaders</span>([
    <span class="st">'X-API-Key'</span>   <span class="op">=></span> <span class="nm">$apiKey</span>,
    <span class="st">'X-Signature'</span> <span class="op">=></span> <span class="nm">$signature</span>,
    <span class="st">'Content-Type'</span><span class="op">=></span> <span class="st">'application/json'</span>,
])-><span class="fn">withBody</span>(<span class="nm">$rawJson</span>, <span class="st">'application/json'</span>)
  -><span class="fn">post</span>(<span class="st">'{{ url("api/v1/session/create") }}'</span>);

<span class="nm">$gameUrl</span> <span class="op">=</span> <span class="nm">$response</span>-&gt;<span class="fn">json</span>(<span class="st">'data.game_url'</span>);</code></pre>

            <h3>Available Game IDs</h3>
            <div class="row g-2">
                @php
                    $games = \App\Models\Game::active()->orderBy('name')->get(['alias','name']);
                @endphp
                @foreach($games as $g)
                <div class="col-md-4 col-6">
                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                        <i class="las la-gamepad text-primary"></i>
                        <div>
                            <div class="fw-bold" style="font-size:.8rem">{{ $g->name }}</div>
                            <code style="font-size:.72rem">{{ $g->alias }}</code>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ── SESSION CLOSE ─────────────────────────────────────────────── --}}
        <div class="doc-section" id="session-close">
            <h2><i class="las la-stop-circle text-primary"></i> Close Session</h2>
            <div class="doc-note warning">
                <strong>Not available in current API v1:</strong> there is no public <code>/api/v1/session/close</code> endpoint in the active route set.
                Expiry is controlled by tenant session TTL and normal gameplay flow.
            </div>
        </div>

        {{-- ── SESSION STATUS ────────────────────────────────────────────── --}}
        <div class="doc-section" id="session-status">
            <h2><i class="las la-info-circle text-primary"></i> Session Status</h2>
            <div class="doc-note warning">
                <strong>Not available in current API v1:</strong> there is no public <code>/api/v1/session/status</code> endpoint in the active route set.
                Track session state on your backend using the session token returned from <code>/api/v1/session/create</code>.
            </div>
        </div>

        {{-- ── BALANCE REFRESH ───────────────────────────────────────────── --}}
        <div class="doc-section" id="balance-refresh">
            <h2><i class="las la-sync text-primary"></i> Refresh Balance</h2>
            <div class="doc-note warning">
                <strong>Not available in current API v1:</strong> there is no public <code>/api/v1/player/balance</code> endpoint in the active route set.
                In webhook mode, balance sync is handled automatically during game events.
            </div>
        </div>

        {{-- ── ANDROID ───────────────────────────────────────────────────── --}}
        <div class="doc-section" id="android">
            <h2><i class="lab la-android text-primary"></i> Android WebView Integration</h2>

            <h3>1. Add Internet Permission</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">&lt;!-- AndroidManifest.xml --&gt;</span>
<span class="op">&lt;</span><span class="kw">uses-permission</span> <span class="nm">android:name</span><span class="op">=</span><span class="st">"android.permission.INTERNET"</span> <span class="op">/&gt;</span></code></pre>

            <h3>2. Activity Layout</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">&lt;!-- activity_game.xml --&gt;</span>
<span class="op">&lt;</span><span class="kw">WebView</span>
    <span class="nm">android:id</span><span class="op">=</span><span class="st">"@+id/gameWebView"</span>
    <span class="nm">android:layout_width</span><span class="op">=</span><span class="st">"match_parent"</span>
    <span class="nm">android:layout_height</span><span class="op">=</span><span class="st">"match_parent"</span> <span class="op">/&gt;</span></code></pre>

            <h3>3. Activity Code (Kotlin)</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="kw">class</span> <span class="nm">GameActivity</span> : <span class="nm">AppCompatActivity</span>() {

    <span class="kw">override fun</span> <span class="fn">onCreate</span>(savedInstanceState: <span class="nm">Bundle</span>?) {
        <span class="fn">super</span>.<span class="fn">onCreate</span>(savedInstanceState)
        <span class="fn">setContentView</span>(<span class="nm">R.layout.activity_game</span>)

        <span class="kw">val</span> <span class="nm">gameUrl</span> = intent.<span class="fn">getStringExtra</span>(<span class="st">"game_url"</span>) ?: <span class="kw">return</span>
        <span class="kw">val</span> <span class="nm">webView</span> = <span class="fn">findViewById</span>&lt;<span class="nm">WebView</span>&gt;(<span class="nm">R.id.gameWebView</span>)

        <span class="nm">webView</span>.<span class="fn">apply</span> {
            <span class="cm">// Required settings</span>
            settings.<span class="nm">javaScriptEnabled</span> = <span class="kw">true</span>
            settings.<span class="nm">domStorageEnabled</span> = <span class="kw">true</span>
            settings.<span class="nm">mixedContentMode</span> = <span class="nm">WebSettings.MIXED_CONTENT_ALWAYS_ALLOW</span>
            settings.<span class="nm">mediaPlaybackRequiresUserGesture</span> = <span class="kw">false</span>

            <span class="cm">// Performance</span>
            settings.<span class="nm">cacheMode</span> = <span class="nm">WebSettings.LOAD_DEFAULT</span>
            settings.<span class="nm">setRenderPriority</span>(<span class="nm">WebSettings.RenderPriority.HIGH</span>)

            <span class="cm">// Android bridge so the game can call closeGame()</span>
            <span class="fn">addJavascriptInterface</span>(<span class="nm">AndroidBridge</span>(<span class="kw">this</span>@<span class="nm">GameActivity</span>), <span class="st">"AndroidBridge"</span>)

            webViewClient = <span class="kw">object</span> : <span class="nm">WebViewClient</span>() {
                <span class="kw">override fun</span> <span class="fn">onPageFinished</span>(view: <span class="nm">WebView</span>, url: <span class="nm">String</span>) {
                    <span class="cm">// Page loaded</span>
                }
            }
        }

        <span class="nm">webView</span>.<span class="fn">loadUrl</span>(<span class="nm">gameUrl</span>)
    }

    <span class="cm">// Override back button to handle WebView navigation</span>
    <span class="kw">override fun</span> <span class="fn">onBackPressed</span>() {
        <span class="kw">val</span> <span class="nm">webView</span> = <span class="fn">findViewById</span>&lt;<span class="nm">WebView</span>&gt;(<span class="nm">R.id.gameWebView</span>)
        <span class="kw">if</span> (<span class="nm">webView</span>.<span class="fn">canGoBack</span>()) <span class="nm">webView</span>.<span class="fn">goBack</span>()
        <span class="kw">else</span> <span class="fn">super</span>.<span class="fn">onBackPressed</span>()
    }
}

<span class="cm">// Bridge for the game to call your app</span>
<span class="kw">class</span> <span class="nm">AndroidBridge</span>(<span class="kw">private val</span> <span class="nm">activity</span>: <span class="nm">GameActivity</span>) {
    <span class="nm">@JavascriptInterface</span>
    <span class="kw">fun</span> <span class="fn">onGameClose</span>(playerId: <span class="nm">String</span>) {
        <span class="nm">activity</span>.<span class="fn">runOnUiThread</span> { <span class="nm">activity</span>.<span class="fn">finish</span>() }
    }
}</code></pre>

            <h3>4. Launch Game from your app (Kotlin)</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">// Call your backend first to get the game_url</span>
<span class="cm">// (NEVER call our API directly from the app — it would expose your secret)</span>
<span class="kw">fun</span> <span class="fn">launchGame</span>(playerId: <span class="nm">String</span>, gameId: <span class="nm">String</span>) {
    <span class="nm">yourApi</span>.<span class="fn">createGameSession</span>(playerId, gameId)
        .<span class="fn">enqueue</span>(<span class="kw">object</span> : <span class="nm">Callback</span>&lt;<span class="nm">GameSessionResponse</span>&gt; {
            <span class="kw">override fun</span> <span class="fn">onResponse</span>(call, response) {
                <span class="kw">val</span> <span class="nm">gameUrl</span> = response.body()?.gameUrl ?: <span class="kw">return</span>
                <span class="kw">val</span> <span class="nm">intent</span> = <span class="nm">Intent</span>(<span class="kw">this</span>@<span class="nm">MainActivity</span>, <span class="nm">GameActivity</span>::<span class="kw">class</span>.<span class="nm">java</span>)
                <span class="nm">intent</span>.<span class="fn">putExtra</span>(<span class="st">"game_url"</span>, <span class="nm">gameUrl</span>)
                <span class="fn">startActivity</span>(<span class="nm">intent</span>)
            }
        })
}</code></pre>
        </div>

        {{-- ── iOS ────────────────────────────────────────────────────────── --}}
        <div class="doc-section" id="ios">
            <h2><i class="lab la-apple text-primary"></i> iOS WKWebView Integration</h2>

            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="kw">import</span> <span class="nm">WebKit</span>

<span class="kw">class</span> <span class="nm">GameViewController</span>: <span class="nm">UIViewController</span>, <span class="nm">WKScriptMessageHandler</span> {

    <span class="kw">var</span> <span class="nm">gameUrl</span>: <span class="nm">String</span> <span class="op">=</span> <span class="st">""</span>
    <span class="kw">private var</span> <span class="nm">webView</span>: <span class="nm">WKWebView</span>!

    <span class="kw">override func</span> <span class="fn">viewDidLoad</span>() {
        <span class="fn">super</span>.<span class="fn">viewDidLoad</span>()

        <span class="cm">// Configure WKWebView</span>
        <span class="kw">let</span> <span class="nm">config</span> = <span class="nm">WKWebViewConfiguration</span>()
        config.<span class="nm">allowsInlineMediaPlayback</span> = <span class="kw">true</span>
        config.<span class="nm">mediaTypesRequiringUserActionForPlayback</span> = []

        <span class="cm">// Register bridge handler — game calls window.webkit.messageHandlers.gameClose.postMessage()</span>
        config.<span class="nm">userContentController</span>.<span class="fn">add</span>(<span class="kw">self</span>, name: <span class="st">"gameClose"</span>)

        <span class="nm">webView</span> = <span class="nm">WKWebView</span>(frame: view.<span class="nm">bounds</span>, configuration: config)
        <span class="nm">webView</span>.<span class="nm">autoresizingMask</span> = [.<span class="nm">flexibleWidth</span>, .<span class="nm">flexibleHeight</span>]
        view.<span class="fn">addSubview</span>(webView)

        <span class="kw">if let</span> <span class="nm">url</span> = <span class="nm">URL</span>(string: gameUrl) {
            webView.<span class="fn">load</span>(<span class="nm">URLRequest</span>(url: url))
        }
    }

    <span class="cm">// Handle game close message</span>
    <span class="kw">func</span> <span class="fn">userContentController</span>(<span class="nm">_</span> userContentController: <span class="nm">WKUserContentController</span>,
                                 didReceive message: <span class="nm">WKScriptMessage</span>) {
        <span class="kw">if</span> message.<span class="nm">name</span> <span class="op">==</span> <span class="st">"gameClose"</span> {
            <span class="fn">dismiss</span>(animated: <span class="kw">true</span>)
        }
    }
}</code></pre>
        </div>

        {{-- ── WEB ────────────────────────────────────────────────────────── --}}
        <div class="doc-section" id="web">
            <h2><i class="las la-globe text-primary"></i> Web / iFrame Integration</h2>

            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">&lt;!-- Embed the game in your web page --&gt;</span>
<span class="op">&lt;</span><span class="kw">iframe</span>
    <span class="nm">id</span><span class="op">=</span><span class="st">"gameFrame"</span>
    <span class="nm">src</span><span class="op">=</span><span class="st">""</span>
    <span class="nm">style</span><span class="op">=</span><span class="st">"width:100%;height:600px;border:none;border-radius:12px"</span>
    <span class="nm">allow</span><span class="op">=</span><span class="st">"autoplay; fullscreen"</span>
    <span class="nm">allowfullscreen</span>
<span class="op">&gt;&lt;/</span><span class="kw">iframe</span><span class="op">&gt;</span>

<span class="op">&lt;</span><span class="kw">script</span><span class="op">&gt;</span>
<span class="cm">// Fetch game_url from your backend (never call game API from frontend)</span>
<span class="fn">fetch</span>(<span class="st">'/api/my-backend/game-session'</span>, {
    method: <span class="st">'POST'</span>,
    body: <span class="nm">JSON</span>.<span class="fn">stringify</span>({ player_id: <span class="st">'user_123'</span>, game_id: <span class="st">'teen_patti'</span> })
}).<span class="fn">then</span>(r =&gt; r.<span class="fn">json</span>()).<span class="fn">then</span>(data =&gt; {
    document.<span class="fn">getElementById</span>(<span class="st">'gameFrame'</span>).src = data.game_url;
});
<span class="op">&lt;/</span><span class="kw">script</span><span class="op">&gt;</span></code></pre>
        </div>

        {{-- ── WEBHOOK ────────────────────────────────────────────────────── --}}
        <div class="doc-section" id="webhook">
            <h2><i class="las la-exchange-alt text-primary"></i> Webhook Setup (Webhook Mode)</h2>

            <div class="doc-note info">Only needed if your balance mode is <strong>webhook</strong>. Skip this section if using <strong>internal</strong> mode.</div>

            <p>We POST to your webhook URL for every game event. Your endpoint must respond within <strong>10 seconds</strong>.</p>

            <h3>Verify Our Requests</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">// We sign: HMAC-SHA256(webhook_secret, "action|player_id|amount|round_id|timestamp")</span>
<span class="cm">// PHP verification:</span>
<span class="nm">$expected</span> <span class="op">=</span> <span class="fn">hash_hmac</span>(<span class="st">'sha256'</span>,
    <span class="nm">$action</span><span class="op">.</span><span class="st">'|'</span><span class="op">.</span><span class="nm">$playerId</span><span class="op">.</span><span class="st">'|'</span><span class="op">.</span><span class="fn">number_format</span>(<span class="nm">$amount</span>,<span class="nb">2</span>,<span class="st">'.'</span>,<span class="st">''</span>)<span class="op">.</span><span class="st">'|'</span><span class="op">.</span><span class="nm">$roundId</span><span class="op">.</span><span class="st">'|'</span><span class="op">.</span><span class="nm">$timestamp</span>,
    <span class="nm">$webhookSecret</span>
);
<span class="kw">if</span> (<span class="op">!</span><span class="fn">hash_equals</span>(<span class="nm">$expected</span>, <span class="nm">$request</span>[<span class="st">'signature'</span>])) {
    <span class="kw">return</span> <span class="fn">response</span>()-&gt;<span class="fn">json</span>([<span class="st">'status'</span> =&gt; <span class="st">'error'</span>, <span class="st">'message'</span> =&gt; <span class="st">'Invalid signature'</span>], <span class="nb">401</span>);
}</code></pre>

            <h3>Webhook Events</h3>
            <table class="table doc-table">
                <thead><tr><th>Action</th><th>When</th><th>Expected Response</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>balance</code></td>
                        <td>Session start, after each round</td>
                        <td><code>{"status":"ok","balance":5000.00}</code></td>
                    </tr>
                    <tr>
                        <td><code>debit</code></td>
                        <td>Player places a bet</td>
                        <td><code>{"status":"ok","balance":4500.00,"transaction_id":"your_txn_1"}</code></td>
                    </tr>
                    <tr>
                        <td><code>credit</code></td>
                        <td>Player wins</td>
                        <td><code>{"status":"ok","balance":5400.00,"transaction_id":"your_txn_2"}</code></td>
                    </tr>
                    <tr>
                        <td><code>rollback</code></td>
                        <td>System error — reverses a debit</td>
                        <td><code>{"status":"ok","balance":5000.00,"transaction_id":"your_txn_3"}</code></td>
                    </tr>
                </tbody>
            </table>

            <h3>Webhook Handler (Laravel Example)</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code>Route::<span class="fn">post</span>(<span class="st">'/game-webhook'</span>, <span class="kw">function</span>(<span class="nm">Request</span> <span class="nm">$request</span>) {
    <span class="nm">$secret</span> <span class="op">=</span> <span class="nm">config</span>(<span class="st">'services.game_webhook_secret'</span>);

    <span class="cm">// 1. Verify signature</span>
    <span class="nm">$sigData</span> <span class="op">=</span> <span class="fn">implode</span>(<span class="st">'|'</span>, [
        <span class="nm">$request</span>-&gt;<span class="nm">action</span>, <span class="nm">$request</span>-&gt;<span class="nm">player_id</span>,
        <span class="fn">number_format</span>(<span class="nm">$request</span>-&gt;<span class="nm">amount</span>, <span class="nb">2</span>, <span class="st">'.'</span>, <span class="st">''</span>),
        <span class="nm">$request</span>-&gt;<span class="nm">round_id</span>, <span class="nm">$request</span>-&gt;<span class="nm">timestamp</span>
    ]);
    <span class="kw">if</span> (<span class="op">!</span><span class="fn">hash_equals</span>(
        <span class="fn">hash_hmac</span>(<span class="st">'sha256'</span>, <span class="nm">$sigData</span>, <span class="nm">$secret</span>),
        <span class="nm">$request</span>-&gt;<span class="nm">signature</span>
    )) <span class="kw">return</span> <span class="fn">response</span>()-&gt;<span class="fn">json</span>([<span class="st">'status'</span>=&gt;<span class="st">'error'</span>, <span class="st">'message'</span>=&gt;<span class="st">'Bad signature'</span>], <span class="nb">401</span>);

    <span class="nm">$player</span> <span class="op">=</span> <span class="nm">Player</span>::<span class="fn">where</span>(<span class="st">'ext_id'</span>, <span class="nm">$request</span>-&gt;<span class="nm">player_id</span>)-&gt;<span class="fn">firstOrFail</span>();

    <span class="kw">return match</span>(<span class="nm">$request</span>-&gt;<span class="nm">action</span>) {
        <span class="st">'balance'</span>  <span class="op">=></span> <span class="fn">response</span>()-&gt;<span class="fn">json</span>([<span class="st">'status'</span>=&gt;<span class="st">'ok'</span>, <span class="st">'balance'</span>=&gt;<span class="nm">$player</span>-&gt;<span class="nm">balance</span>]),
        <span class="st">'debit'</span>    <span class="op">=></span> <span class="nm">WalletService</span>::<span class="fn">debit</span>(<span class="nm">$player</span>, <span class="nm">$request</span>-&gt;<span class="nm">amount</span>, <span class="nm">$request</span>-&gt;<span class="nm">round_id</span>),
        <span class="st">'credit'</span>   <span class="op">=></span> <span class="nm">WalletService</span>::<span class="fn">credit</span>(<span class="nm">$player</span>, <span class="nm">$request</span>-&gt;<span class="nm">amount</span>, <span class="nm">$request</span>-&gt;<span class="nm">round_id</span>),
        <span class="st">'rollback'</span> <span class="op">=></span> <span class="nm">WalletService</span>::<span class="fn">rollback</span>(<span class="nm">$player</span>, <span class="nm">$request</span>-&gt;<span class="nm">ref_txn_id</span>),
        <span class="kw">default</span>    <span class="op">=></span> <span class="fn">response</span>()-&gt;<span class="fn">json</span>([<span class="st">'status'</span>=&gt;<span class="st">'error'</span>, <span class="st">'message'</span>=&gt;<span class="st">'Unknown action'</span>], <span class="nb">400</span>),
    };
});</code></pre>
        </div>

        {{-- ── INTERNAL MODE ─────────────────────────────────────────────── --}}
        <div class="doc-section" id="internal-mode">
            <h2><i class="las la-coins text-primary"></i> Internal Balance Mode</h2>

            <p>In internal mode, all player balances are stored in our database. No webhook is needed.</p>

            <h3>Top Up Player Tokens — via Tenant Panel</h3>
            <ol>
                <li>Go to <strong>{{ url('/tenant/login') }}</strong></li>
                <li>Login with your panel email/password</li>
                <li>Go to <strong>Players</strong> tab</li>
                <li>Click <strong>Add Tokens</strong> next to any player</li>
            </ol>

            <h3>Top Up via API (Programmatic)</h3>
            <div class="doc-note info">Coming soon — you can currently top up via the Tenant Panel UI.</div>

            <h3>Flow Diagram</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">Admin adds tokens to player via Tenant Panel
         ↓
Our DB: users.balance = 5000
         ↓
Player opens game WebView (game_url)
         ↓
Player places bet of 500
         ↓
Our DB: users.balance = 4500  (instantly, no HTTP call)
         ↓
Player wins 800
         ↓
Our DB: users.balance = 5300  (instantly)</span></code></pre>
        </div>

        {{-- ── WEBHOOK MODE ──────────────────────────────────────────────── --}}
        <div class="doc-section" id="webhook-mode">
            <h2><i class="las la-server text-primary"></i> Webhook Balance Mode</h2>

            <p>In webhook mode, every balance change is confirmed with your server in real time.</p>

            <div class="doc-note warning">
                <strong>Latency Warning:</strong> Your webhook must respond in under 10 seconds.
                Slow responses cause the bet to fail from the player's perspective.
                Use a dedicated fast endpoint, not a general-purpose API route.
            </div>

            <h3>Idempotency</h3>
            <p>Each transaction has a unique <code>transaction_id</code> (our ID) and optionally a <code>ref_txn_id</code> for rollbacks.
            Store our <code>transaction_id</code> and check for duplicates to prevent double-crediting on retries.</p>
        </div>

        {{-- ── SEPARATE DB ───────────────────────────────────────────────── --}}
        <div class="doc-section" id="separate-db">
            <h2><i class="las la-database text-primary"></i> Separate Database Setup</h2>

            <p>Each tenant can optionally have their own MySQL database for <strong>transaction logs</strong>,
            ensuring their data is isolated from other tenants.</p>

            <div class="doc-note success">
                <strong>What is isolated:</strong> <code>tenant_transactions</code> table only.<br>
                <strong>What stays in main DB:</strong> Sessions, games config, tenant metadata (needed for routing).
            </div>

            <h3>Step 1 — Create the MySQL database</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm">-- On your MySQL server (or the tenant's server)</span>
<span class="kw">CREATE DATABASE</span> <span class="nm">tenant_acme_gamedb</span> <span class="kw">CHARACTER SET</span> utf8mb4 <span class="kw">COLLATE</span> utf8mb4_unicode_ci;
<span class="kw">CREATE USER</span> <span class="st">'acme_user'</span>@<span class="st">'%'</span> <span class="kw">IDENTIFIED BY</span> <span class="st">'strong_password_here'</span>;
<span class="kw">GRANT ALL PRIVILEGES ON</span> <span class="nm">tenant_acme_gamedb</span>.* <span class="kw">TO</span> <span class="st">'acme_user'</span>@<span class="st">'%'</span>;
<span class="kw">FLUSH PRIVILEGES</span>;</code></pre>

            <h3>Step 2 — Configure in Admin Panel</h3>
            <ol>
                <li>Admin → Tenants → Edit Tenant</li>
                <li>Scroll to <strong>"Separate Database"</strong> section</li>
                <li>Enable the toggle and fill in Host, Port, DB Name, Username, Password</li>
                <li>Click <strong>"Test Connection"</strong> to verify</li>
                <li>Save</li>
            </ol>

            <h3>Step 3 — Run the setup command</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code><span class="cm"># Replace {tenant_id} with the numeric ID from admin panel</span>
<span class="nm">php artisan tenant:db-setup</span> <span class="nb">{tenant_id}</span>

<span class="cm"># Test connection only (no changes)</span>
<span class="nm">php artisan tenant:db-setup</span> <span class="nb">{tenant_id}</span> <span class="nm">--test</span>

<span class="cm"># Drop and recreate tables (if schema changed)</span>
<span class="nm">php artisan tenant:db-setup</span> <span class="nb">{tenant_id}</span> <span class="nm">--force</span></code></pre>

            <div class="doc-note success">
                After setup, all new transactions for this tenant are automatically written
                to their dedicated database. Existing transactions stay in the main DB.
            </div>
        </div>

        {{-- ── SECURITY ──────────────────────────────────────────────────── --}}
        <div class="doc-section" id="security">
            <h2><i class="las la-shield-alt text-primary"></i> Security Guide</h2>

            <table class="table doc-table">
                <thead><tr><th>Risk</th><th>Protection</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Exposing API secret in app</td>
                        <td>Always call our API from your <strong>backend server</strong>. App calls your backend; your backend calls us.</td>
                    </tr>
                    <tr>
                        <td>Request replay attacks</td>
                        <td>Every request includes a <code>timestamp</code> — rejected if >5 min old.</td>
                    </tr>
                    <tr>
                        <td>Bet amount manipulation (Burp Suite)</td>
                        <td>Our server uses <strong>Action Tokens</strong> — bet amount is signed server-side before the round starts. Client cannot modify it.</td>
                    </tr>
                    <tr>
                        <td>Duplicate webhook calls</td>
                        <td>Store <code>transaction_id</code> in your DB. Return <code>ok</code> for duplicates without re-processing.</td>
                    </tr>
                    <tr>
                        <td>IP spoofing</td>
                        <td>Configure <strong>Allowed IPs</strong> on your tenant to restrict session creation.</td>
                    </tr>
                    <tr>
                        <td>Session token theft</td>
                        <td>Tokens are single-use per session and expire per configured TTL (default 60 min).</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ── ERROR CODES ───────────────────────────────────────────────── --}}
        <div class="doc-section" id="errors">
            <h2><i class="las la-exclamation-triangle text-primary"></i> Error Codes</h2>

            <table class="table doc-table">
                <thead><tr><th>HTTP</th><th>Message</th><th>Cause / Fix</th></tr></thead>
                <tbody>
                    <tr><td><span class="badge bg-danger">401</span></td><td>Invalid API key</td><td>Check api_key value</td></tr>
                    <tr><td><span class="badge bg-danger">401</span></td><td>Invalid signature</td><td>Verify HMAC formula and webhook_secret value</td></tr>
                    <tr><td><span class="badge bg-danger">401</span></td><td>Request timestamp expired</td><td>Sync server time (NTP). Must be within ±5 min</td></tr>
                    <tr><td><span class="badge bg-danger">403</span></td><td>Game is not enabled for your account</td><td>Admin → Tenant → Games → Enable that game</td></tr>
                    <tr><td><span class="badge bg-danger">403</span></td><td>Session expired or invalid</td><td>Session TTL exceeded. Create a new session</td></tr>
                    <tr><td><span class="badge bg-warning text-dark">422</span></td><td>Validation error</td><td>Check request fields — response body has field-level errors</td></tr>
                    <tr><td><span class="badge bg-secondary">500</span></td><td>Webhook connection failed</td><td>Your webhook URL is unreachable / timed out</td></tr>
                </tbody>
            </table>

            <h3>Error Response Shape</h3>
            <pre class="code-block"><button class="copy-btn" onclick="copyCode(this)">Copy</button><code>{
  <span class="st">"success"</span>: <span class="kw">false</span>,
  <span class="st">"message"</span>: <span class="st">"Invalid signature."</span>
}</code></pre>
        </div>

    </div>{{-- /.col --}}
</div>{{-- /.row --}}

@endsection

@push('script')
<script>
// Sticky sidebar highlighting
const sections = document.querySelectorAll('.doc-section');
const navLinks  = document.querySelectorAll('.doc-nav .nav-link');

const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            navLinks.forEach(l => l.classList.remove('active'));
            const active = document.querySelector('.doc-nav a[href="#' + e.target.id + '"]');
            if (active) active.classList.add('active');
        }
    });
}, { rootMargin: '-20% 0px -70% 0px' });

sections.forEach(s => obs.observe(s));

// Copy code blocks
function copyCode(btn) {
    const code = btn.nextElementSibling.innerText;
    navigator.clipboard.writeText(code).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
</script>
@endpush
