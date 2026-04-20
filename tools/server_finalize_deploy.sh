#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
CORE_DIR="$APP_DIR/core"
ENV_FILE="$CORE_DIR/.env"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
SKIP_COMPOSER="${DEPLOY_SKIP_COMPOSER:-0}"
SKIP_ARTISAN="${DEPLOY_SKIP_ARTISAN:-0}"

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Required command not found: $1" >&2
    exit 1
  fi
}

current_env_value() {
  local key="$1"
  local raw=""

  if [ -f "$ENV_FILE" ]; then
    raw="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- || true)"
  fi

  raw="${raw#\"}"
  raw="${raw%\"}"
  printf '%s' "$raw"
}

escape_env_value() {
  local value="${1:-}"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '%s' "$value"
}

if [ "$SKIP_ARTISAN" != "1" ]; then
  require_command "$PHP_BIN"
fi

if [ "$SKIP_COMPOSER" != "1" ]; then
  require_command "$COMPOSER_BIN"
fi

if [ ! -d "$CORE_DIR" ]; then
  echo "Core directory not found: $CORE_DIR" >&2
  exit 1
fi

APP_NAME="${LIVE_APP_NAME:-My Games}"
APP_ENV="${LIVE_APP_ENV:-production}"
APP_DEBUG="${LIVE_APP_DEBUG:-false}"
APP_TIMEZONE="${LIVE_APP_TIMEZONE:-UTC}"
APP_URL="${LIVE_APP_URL:-${DEPLOY_DEFAULT_APP_URL:-https://game.ezycry.com}}"
APP_KEY="${LIVE_APP_KEY:-$(current_env_value APP_KEY)}"
LOG_LEVEL="${LIVE_LOG_LEVEL:-warning}"

DB_HOST="${LIVE_DB_HOST:-${DEPLOY_DEFAULT_DB_HOST:-localhost}}"
DB_PORT="${LIVE_DB_PORT:-${DEPLOY_DEFAULT_DB_PORT:-3306}}"
DB_DATABASE="${LIVE_DB_DATABASE:-${DEPLOY_DEFAULT_DB_DATABASE:-u898978846_prakash}}"
DB_USERNAME="${LIVE_DB_USERNAME:-}"
if [ -z "$DB_USERNAME" ] && [ -n "$DB_DATABASE" ]; then
  DB_USERNAME="$DB_DATABASE"
fi
DB_PASSWORD="${LIVE_DB_PASSWORD:-${DEPLOY_DEFAULT_DB_PASSWORD:-Kanishk@123#}}"
PURCHASECODE="${LIVE_PURCHASECODE:-$(current_env_value PURCHASECODE)}"

if [ -z "$APP_KEY" ]; then
  APP_KEY="$("$PHP_BIN" -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

if [ -z "$APP_URL" ]; then
  echo "LIVE_APP_URL is required for deployment." >&2
  exit 1
fi

if [ -z "$DB_DATABASE" ]; then
  echo "LIVE_DB_DATABASE is required for deployment." >&2
  exit 1
fi

if [ -z "$DB_USERNAME" ]; then
  echo "LIVE_DB_USERNAME is required for deployment." >&2
  exit 1
fi

mkdir -p \
  "$CORE_DIR/bootstrap/cache" \
  "$CORE_DIR/storage/app" \
  "$CORE_DIR/storage/framework/cache" \
  "$CORE_DIR/storage/framework/sessions" \
  "$CORE_DIR/storage/framework/views" \
  "$CORE_DIR/storage/logs"

cat > "$ENV_FILE" <<EOF
APP_NAME="$(escape_env_value "$APP_NAME")"
APP_ENV=$APP_ENV
APP_KEY=$(escape_env_value "$APP_KEY")
APP_DEBUG=$APP_DEBUG
APP_TIMEZONE="$(escape_env_value "$APP_TIMEZONE")"
APP_URL="$(escape_env_value "$APP_URL")"

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=$LOG_LEVEL

DB_CONNECTION=mysql
DB_HOST="$(escape_env_value "$DB_HOST")"
DB_PORT="$(escape_env_value "$DB_PORT")"
DB_DATABASE="$(escape_env_value "$DB_DATABASE")"
DB_USERNAME="$(escape_env_value "$DB_USERNAME")"
DB_PASSWORD="$(escape_env_value "$DB_PASSWORD")"

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=

PURCHASECODE="$(escape_env_value "$PURCHASECODE")"
EOF

chmod 600 "$ENV_FILE" || true
chmod -R ug+rw "$CORE_DIR/storage" "$CORE_DIR/bootstrap/cache" || true

cd "$CORE_DIR"

if [ "$SKIP_COMPOSER" != "1" ]; then
  "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

if [ "$SKIP_ARTISAN" != "1" ]; then
  "$PHP_BIN" artisan optimize:clear

  if [ "$RUN_MIGRATIONS" = "1" ]; then
    "$PHP_BIN" artisan migrate --force
  fi

  "$PHP_BIN" artisan config:cache
fi

# ── Setup cron jobs for game round resolvers ────────────────
CRON_MARKER="# game-round-resolvers"
CRON_CMD_TP="* * * * * cd $CORE_DIR && $PHP_BIN artisan teen-patti:resolve >> /dev/null 2>&1"
CRON_CMD_AB="* * * * * cd $CORE_DIR && $PHP_BIN artisan andar-bahar:resolve >> /dev/null 2>&1"
CRON_CMD_SCHED="* * * * * cd $CORE_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"

# Only add if not already present
if command -v crontab >/dev/null 2>&1; then
  EXISTING_CRON=$(crontab -l 2>/dev/null || true)
  if ! echo "$EXISTING_CRON" | grep -q "teen-patti:resolve"; then
    echo "Setting up game resolver cron jobs..."
    {
      echo "$EXISTING_CRON"
      echo ""
      echo "$CRON_MARKER"
      echo "$CRON_CMD_TP"
      echo "$CRON_CMD_AB"
      echo "$CRON_CMD_SCHED"
    } | crontab - 2>/dev/null || echo "Warning: Could not set crontab (shared hosting may not allow this)"
  fi
fi

echo "Deploy finalized successfully."
