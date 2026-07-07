#!/bin/sh
set -e
cd /var/www/html

if [ ! -f .env ]; then
    echo ">> XƏTA: .env tapılmadı. compose /var/www/html/.env mount etməlidir." >&2
    exit 1
fi

echo ">> Verilənlər bazası gözlənilir..."
until php -r '$h=getenv("DB_HOST")?:"lifehub_pgsql";$p=getenv("DB_PORT")?:5432;exit(@fsockopen($h,(int)$p,$e,$s,2)?0:1);' 2>/dev/null; do
    echo "   ... hazır deyil, 2s"
    sleep 2
done
echo ">> Baza hazırdır."

php artisan package:discover --ansi || true
php artisan migrate --force
php artisan storage:link 2>/dev/null || true

php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

chown -R www-data:www-data storage bootstrap/cache

echo ">> Başladılır..."
exec "$@"
