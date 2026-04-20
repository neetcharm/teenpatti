<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6fb; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 420px; border: none; border-radius: 16px;
                      box-shadow: 0 8px 32px rgba(0,0,0,.10); }
        .login-header { background: #1e2139; border-radius: 16px 16px 0 0; padding: 32px 32px 24px;
                        text-align: center; color: #fff; }
        .login-header h4 { font-weight: 700; margin-bottom: 4px; }
        .login-header p  { color: rgba(255,255,255,.55); font-size: 13px; margin: 0; }
        .login-body { padding: 32px; }
        .btn-login { background: #5a67d8; border: none; padding: 10px; font-weight: 600; letter-spacing: .3px; }
        .btn-login:hover { background: #4c56c0; }
    </style>
</head>
<body>
<div class="login-card card">
    <div class="login-header">
        <h4>&#127918; Tenant Panel</h4>
        <p>Sign in to manage your game integration</p>
    </div>
    <div class="login-body">
        @if($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach($errors->all() as $e){{ $e }}@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.login.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email') }}" placeholder="your@email.com" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-login btn-primary w-100">Sign In</button>
        </form>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">
            Contact your admin if you've lost access to your account.
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
