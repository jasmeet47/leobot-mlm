#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
if [ -f /etc/secrets/.env ]; then
    ln -sfn /etc/secrets/.env /var/www/html/.env
fi
php artisan lumiflowon:beta-prepare --no-interaction
exec apache2-foreground
