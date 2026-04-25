# My Games Live Server Deployment Guide (English)

This guide explains how to deploy this project to a **new live domain** and set up the complete workflow for:

- `Admin` (platform owner)
- `Tenant` (operator/integration partner)
- `User` (player)

It also explains how the full system works end-to-end, including API session creation, game launch, and wallet behavior.

---

## 1. What You Are Deploying

This project is a Laravel-based SaaS game platform with:

- Public site + user auth + game frontend
- Admin panel at `/admin`
- Tenant panel at `/tenant`
- Tenant API endpoints under `/api/v1/*`
- WebView game launch endpoint at `/play?token=...`

Core runtime notes:

- Root `index.php` bootstraps Laravel from `core/`.
- Root `.htaccess` blocks direct access to `core/`, `install/`, and sensitive files.
- Tenant runtime tables are auto-ensured by `TenantRuntimeSchema` on demand.

---

## 2. Server Requirements

Minimum:

- Linux VPS (recommended), Apache/Nginx
- PHP `8.2+`
- MySQL `8+` or MariaDB `10.4+`
- Composer 2
- HTTPS certificate (Let's Encrypt or commercial)

Required PHP extensions (recommended complete set):

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `gd`
- `json`
- `mbstring`
- `openssl`
- `pdo_mysql`
- `tokenizer`
- `xml`
- `zip`
- `filter`
- `hash`
- `session`

---

## 3. Domain and DNS

1. Point your domain A record to your server public IP.
2. Wait for DNS propagation.
3. Enable SSL (Let's Encrypt recommended).
4. Force HTTPS at web server level (and later in app settings).

Example target:

- `https://yourdomain.com`

---

## 4. Upload Project to Server

Upload/copy the full project to a directory, for example:

- `/var/www/mygames`

Important structure:

- `/var/www/mygames/index.php` (entry point)
- `/var/www/mygames/core` (Laravel app)
- `/var/www/mygames/assets`
- `/var/www/mygames/install/game.sql` (DB seed/export file in this repo)

---

## 5. Create Database and Import SQL

Create DB and user:

```sql
CREATE DATABASE mygames CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mygames_user'@'localhost' IDENTIFIED BY 'StrongPasswordHere';
GRANT ALL PRIVILEGES ON mygames.* TO 'mygames_user'@'localhost';
FLUSH PRIVILEGES;
```

Import initial data:

```bash
mysql -u mygames_user -p mygames < /var/www/mygames/install/game.sql
```

Notes:

- `install/game.sql` already contains tables and initial records.
- After import, **immediately rotate secrets/passwords** in production.

---

## 6. Configure Environment (`core/.env`)

From server shell:

```bash
cd /var/www/mygames/core
cp .env.production.example .env
```

Update at least:

- `APP_NAME`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://yourdomain.com`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SESSION_DRIVER=file` (or redis/database if you manage them)
- `CACHE_STORE=file` (or redis)
- `QUEUE_CONNECTION=database` (recommended for webhook mode; `sync` is fallback)
- `LOG_LEVEL=warning`
- `TENANT_SESSION_IDLE_TIMEOUT_MINUTES=5` (auto-close inactive tenant sessions)

Generate app key:

```bash
php artisan key:generate
```

Queue notes:

- If `QUEUE_CONNECTION=database`, ensure queue tables exist:
  - `jobs`
  - `failed_jobs`
- If these tables are missing, create them once:

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate --force
```

---

## 7. Install Dependencies and Optimize

```bash
cd /var/www/mygames/core
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If `route:cache` fails due closures, keep:

- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan view:cache`

Post-deploy quick checks:

```bash
php artisan route:list --path=api/v1
php artisan schedule:list
```

---

## 8. File Permissions

Set writable directories:

- `core/storage`
- `core/bootstrap/cache`

Example (Ubuntu):

```bash
sudo chown -R www-data:www-data /var/www/mygames
sudo find /var/www/mygames/core/storage -type d -exec chmod 775 {} \;
sudo find /var/www/mygames/core/storage -type f -exec chmod 664 {} \;
sudo chmod -R 775 /var/www/mygames/core/bootstrap/cache
```

---

## 9. Web Server Configuration

### Apache (recommended with existing `.htaccess`)

VirtualHost root should be:

- `/var/www/mygames`

Enable:

- `AllowOverride All`
- `mod_rewrite`
- SSL vhost

### Nginx sample

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    root /var/www/mygames;
    index index.php;

    # Block sensitive paths
    location ~* ^/(core|install)(/|$) { deny all; }
    location ~* ^/(composer\.(json|lock)|package\.json|phpunit\.xml|artisan)$ { deny all; }
    location ~ /\. { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

---

## 10. Cron and Scheduler Setup (Critical)

This project now uses **two scheduling systems**. In production, configure both.

### 10.1 Legacy route-based cron (existing platform jobs)

Endpoint:

- `GET /cron?key=YOUR_CRON_KEY`

Run every minute:

```bash
* * * * * curl -fsS "https://yourdomain.com/cron?key=YOUR_CRON_KEY" >/dev/null 2>&1
```

This updates `last_cron` and executes legacy DB-configured cron jobs.

### 10.2 Laravel scheduler (required for game/session automation)

Required because these commands are scheduled in `routes/console.php`:

- `teen-patti:resolve`
- `andar-bahar:resolve`
- `tenant:sessions:cleanup`

Run every minute:

```bash
* * * * * cd /var/www/mygames/core && php artisan schedule:run >/dev/null 2>&1
```

Verify:

```bash
php artisan schedule:list
```

### 10.3 Queue worker (recommended for webhook-credit reliability)

If using `QUEUE_CONNECTION=database` or `redis`, run a long-lived worker (Supervisor/systemd):

```bash
cd /var/www/mygames/core
php artisan queue:work --tries=5 --timeout=30 --sleep=2
```

Notes:

- Async win-credit jobs retry automatically.
- If dispatch fails, code falls back to synchronous credit, but worker is still recommended for stable throughput.

---

## 11. First Production Security Checklist

Do immediately after go-live:

1. Change admin password.
2. Update admin email.
3. Rotate any seeded tenant API keys/secrets.
4. Ensure `APP_DEBUG=false`.
5. Ensure HTTPS is active.
6. Remove/lock any temporary SQL or debug files not needed publicly.
7. Verify `.htaccess`/Nginx deny rules for `core`, `install`, dotfiles.
8. Set daily DB backup + file backup.

---

## 12. Admin Setup Flow

Admin URLs:

- Login: `https://yourdomain.com/admin`
- Dashboard: `https://yourdomain.com/admin/dashboard`

After login:

1. `General Settings`
2. `Logo/Icon`
3. `System Configuration`
4. `Email/SMS/Push settings`
5. `Cron settings check`
6. `Game status and availability`

Tenant management section:

- `Admin -> Tenants`
- Create/Edit/Enable/Disable tenant
- Regenerate tenant credentials
- View sessions/transactions
- Assign game access
- Test separate tenant DB connection

---

## 13. Tenant Setup Flow

Tenant login URL:

- `https://yourdomain.com/tenant/login`

### 13.1 Create Tenant (Admin side)

Fields to configure:

- Tenant name
- Tenant panel email
- Balance mode: `internal` or `webhook`
- Webhook URL (required for webhook mode)
- Callback URL (optional)
- Wallet top-up URL (optional)
- Currency
- Min/Max bet
- Commission %
- Session TTL
- Allowed IPs (optional)
- Separate DB config (optional)

On save, system auto-generates:

- API key (e.g. starts with `tp_`)
- Webhook/API signing secret (plain shown once)
- Tenant panel password (shown once)

Store these securely. They are not shown again.

### 13.2 Tenant Panel

Tenant can:

- View API key and signing secret
- Adjust commission and bet range (if allowed)
- Launch Teen Patti test session
- Manage internal player balances (internal mode)
- View sessions and transactions

---

## 14. User Setup Flow

User routes are under `/user/*`:

- Register: `/user/register`
- Login: `/user/login`
- Dashboard: `/user/dashboard`

User play routes:

- `/user/play/{alias}`
- Tenant wallet refresh endpoint exists for tenant-launched sessions.

In tenant-launched mode:

- Player is internally mapped to a system user.
- Session token controls launch/access.
- `tenant_session_id` is tracked in web session.

---

## 15. Tenant API Integration (How It Works)

Core API endpoints:

- `POST /api/v1/session/create`
- `POST /api/v1/session/end`
- `POST /api/v1/session/close` (legacy alias of `session/end`)
- `GET /api/v1/game/{alias}/start`
- `POST /api/v1/game/{alias}/play`
- Legacy alias: `POST /api/v1/game/session`

Authentication headers:

- `X-API-Key`
- `X-Signature`

Primary signature mode:

- `X-Signature = HMAC_SHA256(raw_json_body, tenant_signing_secret)`

Optional anti-replay mode supported:

- `X-Timestamp`
- `X-Nonce`
- Canonical signed string includes method/path/body hash.

Session create response returns:

- `session_token`
- `game_url` (open in WebView)
- `player_balance`
- `currency`
- `expires_at`
- `resumed` (`true` when same active player+game session is reused)

Create behavior:

- First call returns `201` (new session).
- If same tenant/player/game already has active session, returns `200` with `resumed=true`.

Launch:

- Open `game_url` in Android/iOS/WebView.
- Game endpoint: `/play?token={session_token}` validates and starts session.

Start endpoint session resume behavior:

- `GET /api/v1/game/{alias}/start` can resolve session from:
  - existing PHP session (`tenant_session_id`)
  - or `session_token` passed as query/body/header (`X-Session-Token`)

Session end behavior:

- Tenant backend can close session explicitly via:
  - `POST /api/v1/session/end`
  - or `POST /api/v1/session/close`
- This marks status as `closed` and expires the token.

---

## 16. Balance Modes Explained

### Internal Mode

- No external wallet callback required.
- Balance updates happen in platform DB.
- Tenant tops up/deducts from tenant panel.
- Fastest integration path.

### Webhook Mode

- Your wallet remains source of truth.
- Platform calls your webhook for:
  - `balance`
  - `debit`
  - `credit`
  - `rollback`
- You return updated balance + optional transaction ID.

Webhook mode runtime behavior (important):

- Launch may fetch a fresh balance from webhook.
- During gameplay, balance refresh is throttled to avoid excessive calls.
- Sync flow refreshes wallet balance after round completion (not on every sync tick).
- Rollback now carries original debit context (`ref_txn_id`) and rollback amount is derived from original debit transaction.

Tenant session inactivity behavior:

- Inactive tenant sessions are auto-closed after `TENANT_SESSION_IDLE_TIMEOUT_MINUTES` (default `5`).
- Closed sessions return `403` with a relaunch message on play/sync endpoints.

Teen Patti crowd display behavior:

- The `All` amount on each side is display-boosted with synthetic crowd volume.
- Current display range is `50,00,000` to `1,50,00,000` per side (with live drift).
- This is UI-only and does **not** change actual wallet debits/credits/payout calculations.
- Config location: `assets/global/js/game/teenPatti.js` (`CROWD_DISPLAY_*` constants).

---

## 17. Separate Tenant Database (Optional)

When `use_separate_db = true` for a tenant:

- Dynamic DB connection is created per tenant via `TenantConnectionManager`.
- Admin can run `Test DB` before saving.
- Tenant transaction data can be routed through tenant-specific connection logic.

Use this only when you need strict tenant-level DB isolation.

### 17.1 Tenant-Scoped Teen Patti Round Data (New)

Recent production updates add tenant scope to round aggregation tables to prevent cross-tenant leakage in game-side totals/history.

Migration adds tenant scope fields/indexes for:

- `teen_patti_round_bets`
- `teen_patti_round_history`

Apply on production:

```bash
cd /var/www/mygames/core
php artisan migrate --force
```

Do not skip this migration on old databases.

---

## 18. Production Verification Checklist

Run these checks after deployment:

1. Home page loads: `/`
2. Admin login works: `/admin`
3. Tenant login works: `/tenant/login`
4. User login/register works: `/user/login`, `/user/register`
5. API signed request to `/api/v1/session/create` returns `201` (new) or `200` (`resumed=true`)
6. Invalid signature returns `401`
7. `/play?token=...` launches game correctly
8. `/api/v1/session/end` closes an active session correctly
9. `/api/v1/game/teen_patti/start` works with `session_token` passed via query/header
10. Legacy cron (`/cron?key=...`) runs every minute
11. Laravel scheduler runs every minute (`php artisan schedule:list` shows due commands)
12. If queue is enabled: worker processes jobs and `failed_jobs` remains stable
13. Logs are writable (`core/storage/logs`)
14. SSL + redirects working

---

## 19. Role-Based Setup System (SOP)

Use this as your standard operating process for every new deployment.

### 19.1 Admin Bootstrap SOP

1. Login at `/admin`.
2. Change admin password and email.
3. Configure:
   - Site name/logo/favicon
   - Mail settings
   - Security settings
   - Cron
4. Verify game status (`Teen Patti` active).
5. Create first tenant and securely deliver credentials.

If admin password is unknown, reset with Tinker:

```bash
cd /var/www/mygames/core
php artisan tinker
```

```php
$admin = App\Models\Admin::where('username', 'admin')->first();
$admin->password = Illuminate\Support\Facades\Hash::make('NewStrongAdminPassword123!');
$admin->save();
```

### 19.2 Tenant Onboarding SOP

1. Admin creates tenant from `Admin -> Tenants -> Add Tenant`.
2. Admin sends tenant a secure onboarding packet:
   - Panel URL
   - Panel email
   - Temporary panel password
   - API key
   - Signing secret
   - API docs URL: `/admin/docs/integration`
3. Tenant logs in to `/tenant/login`, changes panel settings.
4. Tenant performs API smoke test:
   - signed `POST /api/v1/session/create`
   - receives `game_url`
   - opens in WebView
   - signed `POST /api/v1/session/end` successfully closes the session
5. Tenant confirms wallet behavior (internal or webhook mode).

### 19.3 User Onboarding SOP

1. User registers at `/user/register` or tenant creates playable session via API.
2. User login verified at `/user/login`.
3. KYC/2FA flow tested (if enabled in platform settings).
4. User can enter game and session logs appear in admin/tenant dashboards.

### 19.4 Tenant Credential Rotation SOP

Rotate every 30/60/90 days (as policy):

1. Admin uses `Regenerate Keys` for tenant.
2. Active tenant sessions are expired automatically.
3. Tenant updates backend secret immediately.
4. Re-run API smoke test after rotation.

---

## 20. Backup and Recovery Plan

Minimum:

- Daily DB backup (retain 7-30 days)
- Daily file backup (`core`, `assets`, `.env`)
- Before each update:
  - DB snapshot
  - Files snapshot

Restore drill:

1. Restore files.
2. Restore DB.
3. Restore `.env`.
4. Run `php artisan optimize:clear`.
5. Smoke test admin/tenant/user/API.

---

## 21. Common Issues and Fixes

### Issue: `Cannot call constructor`

Cause:

- Child controllers call `parent::__construct()` but base constructor missing.

Fix:

- Keep no-op constructor in `App\Http\Controllers\Controller`.

### Issue: 403 on `/play?token=...`

Check:

- Session token expired
- Tenant disabled
- Game not assigned to tenant
- Internal user inactive

### Issue: API 401 `Invalid Signature`

Check:

- Signature uses **raw JSON body**, not parsed object re-serialization mismatch
- Correct secret
- Correct header names

### Issue: Tenant webhook timeouts

Check:

- Webhook URL reachable publicly
- SSL certificate valid
- Response time under timeout window

### Issue: Session closes after 5 minutes

This is expected inactivity behavior for tenant sessions.

Options:

- Keep default for security (`TENANT_SESSION_IDLE_TIMEOUT_MINUTES=5`)
- Or increase timeout in `core/.env` if business policy requires
- Ensure app relaunches session when 403 inactivity response is returned

### Issue: Cross-tenant totals/history visible in game UI

Check:

- Run latest migrations: `php artisan migrate --force`
- Confirm `teen_patti_round_bets` and `teen_patti_round_history` contain tenant scope columns/indexes

### Issue: Wallet credits delayed or missing in webhook mode

Check:

- Queue connection is configured (`database` or `redis`)
- Queue worker is running continuously
- `jobs` and `failed_jobs` tables exist
- Review `core/storage/logs/laravel.log` for `Async Wallet Win` failures

### Issue: Commands in `schedule:list` never execute

Check:

- System cron for `php artisan schedule:run` is installed
- Correct server path to project (`/var/www/mygames/core`)
- Server time/timezone is correct

---

## 22. Suggested Go-Live Sequence

1. Deploy code + database on staging domain.
2. Validate full Admin/Tenant/User/API flow.
3. Point production domain + enable SSL.
4. Switch `APP_URL` to production domain.
5. Clear and rebuild caches.
6. Run production verification checklist.
7. Share tenant credentials securely.
8. Monitor logs for first 24 hours.

---

## 23. Useful Commands (Cheat Sheet)

```bash
cd /var/www/mygames/core

# Required after updates
php artisan migrate --force

# Laravel cleanup
php artisan optimize:clear

# Cache for production
php artisan config:cache
php artisan view:cache

# Optional route cache (only if no closure route conflicts)
php artisan route:cache

# Validate routing/scheduler
php artisan route:list --path=api/v1
php artisan schedule:list

# Manually test scheduled commands
php artisan teen-patti:resolve
php artisan tenant:sessions:cleanup

# Queue worker (if QUEUE_CONNECTION != sync)
php artisan queue:work --tries=5 --timeout=30 --sleep=2
```

---

