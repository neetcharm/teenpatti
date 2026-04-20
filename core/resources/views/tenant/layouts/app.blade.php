<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tenant Panel') — {{ $authTenant->name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxst.icons8.com/vue-static/landings/line-awesome/line-awesome/1.3.0/css/line-awesome.min.css">
    <style>
        :root { --primary: #5a67d8; --sidebar-w: 240px; }
        body { background: #f4f6fb; font-size: 14px; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); height: 100vh; background: #1e2139;
                   position: fixed; top: 0; left: 0; overflow-y: auto; z-index: 100; }
        .sidebar-brand { padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,.08); }
        .sidebar-brand h5 { color: #fff; font-weight: 700; margin: 0; font-size: 16px; }
        .sidebar-brand small { color: rgba(255,255,255,.45); font-size: 11px; }
        .sidebar-nav { padding: 12px 0; }
        .sidebar-nav .nav-label { color: rgba(255,255,255,.35); font-size: 10px; text-transform: uppercase;
                                   letter-spacing: 1px; padding: 16px 24px 6px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 9px 24px;
                          color: rgba(255,255,255,.65); text-decoration: none; font-size: 13.5px;
                          transition: all .15s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { color: #fff; background: rgba(255,255,255,.07);
                                                        border-left-color: var(--primary); }
        .sidebar-nav a i { font-size: 18px; width: 20px; text-align: center; }

        /* Topbar */
        .topbar { margin-left: var(--sidebar-w); background: #fff; border-bottom: 1px solid #e8ecf4;
                   padding: 0 24px; height: 60px; display: flex; align-items: center;
                   justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .topbar .page-title { font-weight: 600; font-size: 15px; color: #1e2139; margin: 0; }

        /* Content */
        .content { margin-left: var(--sidebar-w); padding: 24px; min-height: calc(100vh - 60px); }

        /* Cards */
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .stat-card .icon-box { width: 48px; height: 48px; border-radius: 12px;
                                display: flex; align-items: center; justify-content: center; font-size: 22px; }

        /* Table */
        .table th { background: #f8fafc; font-weight: 600; font-size: 12px;
                     text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .badge-mode { font-size: 11px; }

        /* Balance badge */
        .balance-pill { background: #eef2ff; color: var(--primary); border-radius: 20px;
                         padding: 3px 12px; font-weight: 600; font-size: 13px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .topbar, .content { margin-left: 0; }
        }
    </style>
    @stack('style')
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h5><i class="las la-gamepad"></i> Game Panel</h5>
        <small>{{ $authTenant->name }}</small>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Overview</div>
        <a href="{{ route('tenant.dashboard') }}" class="{{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
            <i class="las la-tachometer-alt"></i> Dashboard
        </a>

        <div class="nav-label">Players</div>
        <a href="{{ route('tenant.players.index') }}" class="{{ request()->routeIs('tenant.players*') ? 'active' : '' }}">
            <i class="las la-users"></i> Players & Tokens
        </a>

        <div class="nav-label">Game Activity</div>
        <a href="{{ route('tenant.sessions.index') }}" class="{{ request()->routeIs('tenant.sessions*') ? 'active' : '' }}">
            <i class="las la-play-circle"></i> Sessions
        </a>
        <a href="{{ route('tenant.transactions.index') }}" class="{{ request()->routeIs('tenant.transactions*') ? 'active' : '' }}">
            <i class="las la-exchange-alt"></i> Transactions
        </a>

        <div class="nav-label">Account</div>
        <a href="{{ route('tenant.settings') }}" class="{{ request()->routeIs('tenant.settings') ? 'active' : '' }}">
            <i class="las la-cog"></i> Settings & API
        </a>

        <div style="padding: 20px 24px 8px;">
            <form method="POST" action="{{ route('tenant.logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="las la-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </nav>
</div>

<div class="topbar">
    <h6 class="page-title">@yield('page-title', 'Dashboard')</h6>
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-{{ $authTenant->balance_mode === 'internal' ? 'success' : 'info' }} badge-mode">
            {{ $authTenant->balance_mode === 'internal' ? 'Internal Tokens' : 'Webhook Mode' }}
        </span>
        <span class="text-muted small">{{ $authTenant->currency }}</span>
    </div>
</div>

<div class="content">
    @if(session('topup_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="las la-check-circle"></i> {{ session('topup_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('script')
</body>
</html>
