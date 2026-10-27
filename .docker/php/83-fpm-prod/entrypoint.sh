#!/usr/bin/env bash
set -e

# Only root can fix ownership. Containers started as www-data (queue
# workers) skip it; the API container (root, php-fpm master) runs it on
# every start so files created by `docker exec` as root get fixed too.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
fi

exec "$@"
