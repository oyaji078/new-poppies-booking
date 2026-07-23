#!/usr/bin/env bash
set -euo pipefail

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-10000}}}"
export ASSET_URL="${ASSET_URL:-$APP_URL}"
export DOKU_NOTIFICATION_URL="${DOKU_NOTIFICATION_URL:-${APP_URL%/}/api/payments/doku/notifications}"
export DOKU_CALLBACK_URL="${DOKU_CALLBACK_URL:-${APP_URL%/}/payment/callback}"

port="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
php artisan optimize

php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600 &
php artisan schedule:work &

exec apache2-foreground
