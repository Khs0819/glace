#!/bin/sh
set -e

# ── Ensure upload directories exist inside the mounted volume ──────────────
# When Docker mounts a fresh volume over storage/app/public the subdirectories
# that Filament expects are missing. Create them on every boot so the first
# upload after a redeploy never 500s.
MEDIA=/var/www/html/storage/app/public

for dir in \
    products \
    sizes \
    items \
    containers \
    categories \
    flavors \
    hero-slides \
    events \
    event-images \
    about \
    why-glace \
    payment-accounts \
    receipts \
    livewire-tmp; do
    mkdir -p "$MEDIA/$dir"
done

# Logs directory (also a volume)
mkdir -p /var/www/html/storage/logs

# ── Fix ownership ─────────────────────────────────────────────────────────
# PHP-FPM runs as www-data; without this, storePubliclyAs() gets a
# permission-denied even though the directory exists.
chown -R www-data:www-data /var/www/html/storage

# ── Laravel boot ───────────────────────────────────────────────────────────
php artisan migrate --force
php artisan storage:link --force
php artisan config:cache
php artisan route:cache

# ── Start services ─────────────────────────────────────────────────────────
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
