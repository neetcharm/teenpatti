# Deployment Setup

This project now supports a clean split between local and live configuration:

- Local development uses `core/.env` with your local MySQL settings.
- Live deployment writes `core/.env` on the server from GitHub Secrets during deployment.
- The Laravel database config reads only from environment variables, so there are no hardcoded live credentials in app code.
- Live boot no longer depends on DB cache for the install flag check, so temporary DB issues do not hard-crash app boot.

## Local setup

1. Copy `core/.env.example` to `core/.env` if you do not already have a local env file.
2. Use your local MySQL values in `core/.env`.
3. Default local values are:
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3307`
   - `DB_DATABASE=game`
   - `DB_USERNAME=root`
   - `DB_PASSWORD=`

## GitHub live deploy

The workflow file is:

- `.github/workflows/deploy-live.yml`

It deploys `main` to your live server over SSH, pulls the repo using a GitHub token, writes `core/.env` from GitHub Secrets, installs Composer dependencies, clears caches, and rebuilds Laravel config cache.

## Direct token deploy (Hyperlocal-style)

If you also want a direct URL-based deploy (without GitHub Actions), use:

- `tools/github_deploy.php`
- `tools/game1_deploy.php` (preconfigured for `game.ezycry.com`)

This endpoint pulls a ZIP from GitHub and deploys to the current project root.

### Required server environment variables for direct deploy

- `DEPLOY_TRIGGER_TOKEN` (secret token used in URL/header)
- `DEPLOY_GITHUB_TOKEN` (GitHub token with repo read access; can also use `DEPLOY_REPO_TOKEN`)
  - For one-time/manual runs, you can also pass `github_token` in request (or `X-GitHub-Token` header).

### Recommended server environment variables for direct deploy

- `DEPLOY_GITHUB_REPO` (for example: `neetcharm/game`)
- `DEPLOY_BRANCH` (for example: `main`)
- `DEPLOY_REQUIRE_POST=1`
- `DEPLOY_ALLOW_IPS` (comma-separated IP whitelist)
- `DEPLOY_WRITE_ENV=1`
- `DEPLOY_RUN_ARTISAN=1`
- `DEPLOY_ALLOW_GIT_PULL=1` (token-less fallback if server can run `git`)
- `LIVE_APP_URL`
- `LIVE_DB_HOST`
- `LIVE_DB_PORT`
- `LIVE_DB_DATABASE`
- `LIVE_DB_USERNAME`
- `LIVE_DB_PASSWORD`

### Direct deploy call

- `GET /tools/github_deploy.php?token=YOUR_DEPLOY_TRIGGER_TOKEN`
- or `POST /tools/github_deploy.php` with `token` in body/header.
- private repo one-time call (if env token is missing):
  - `GET /tools/github_deploy.php?token=YOUR_DEPLOY_TRIGGER_TOKEN&github_token=YOUR_GITHUB_TOKEN`
- persist GitHub token into `core/.env` for future direct deploys:
  - `GET /tools/github_deploy.php?token=YOUR_DEPLOY_TRIGGER_TOKEN&github_token=YOUR_GITHUB_TOKEN&DEPLOY_GITHUB_TOKEN=YOUR_GITHUB_TOKEN`

### Game1 preconfigured direct deploy (hardcoded token)

- Endpoint: `GET /tools/game1_deploy.php?token=G1DEPLOY_2026_LIVE`
- Optional private repo token:
  - `GET /tools/game1_deploy.php?token=G1DEPLOY_2026_LIVE&github_token=YOUR_GITHUB_TOKEN`
- Optional repo/branch override:
  - `GET /tools/game1_deploy.php?token=G1DEPLOY_2026_LIVE&repo=owner/repo&branch=main`

## Required GitHub Secrets

Set these in the repository settings before enabling the workflow:

- `DEPLOY_HOST`
- `DEPLOY_USERNAME`
- `DEPLOY_SSH_KEY`
- `DEPLOY_PATH`
- `DEPLOY_REPO_TOKEN`
- `LIVE_APP_URL`
- `LIVE_DB_DATABASE`

## Recommended GitHub Secrets

These make the deploy fully repeatable:

- `DEPLOY_PORT`
- `DEPLOY_PHP_BIN`
- `DEPLOY_COMPOSER_BIN`
- `DEPLOY_RUN_MIGRATIONS`
- `LIVE_APP_NAME`
- `LIVE_APP_KEY`
- `LIVE_APP_TIMEZONE`
- `LIVE_DB_HOST`
- `LIVE_DB_PORT`
- `LIVE_DB_USERNAME`
- `LIVE_DB_PASSWORD`
- `LIVE_PURCHASECODE`
- `LIVE_LOG_LEVEL`

## Notes

- If `LIVE_DB_USERNAME` is not provided, the deploy script uses the same value as `LIVE_DB_DATABASE`.
- If `LIVE_APP_KEY` is not provided and `core/.env` already exists on the server, the existing key is reused.
- If `LIVE_APP_KEY` is not provided and no env file exists yet, the deploy script generates a new app key automatically.
- `DEPLOY_RUN_MIGRATIONS=1` will run `php artisan migrate --force` after deploy.
- Production env defaults now use:
  - `SESSION_DRIVER=file`
  - `CACHE_STORE=file`
  - `QUEUE_CONNECTION=sync`
