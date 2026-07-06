#!/bin/sh
set -e

# Register Japanese fonts into dompdf font cache (runs once; idempotent)
if [ -f /var/www/html/artisan ]; then
    php /var/www/html/artisan dompdf:load-fonts || true
fi

exec "$@"
