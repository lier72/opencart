#!/bin/sh
set -eu
: "${OPENCART_URL:?Set OPENCART_URL}"
: "${DB_PASSWORD:?Set DB_PASSWORD}"
for dir in cache download logs modification session upload; do
    mkdir -p "/var/www/storage/$dir"
    chown www-data:www-data "/var/www/storage/$dir"
done
# Dependencies belong to the image; data volumes must not pin old libraries.
if [ -e /var/www/storage/vendor ] && [ ! -L /var/www/storage/vendor ]; then
    echo 'Storage contains a vendor directory. Move it aside; this image supplies its own vendor dependencies.' >&2
    exit 1
fi
ln -sfn /opt/opencart-vendor /var/www/storage/vendor
mkdir -p /var/www/html/image/cache
chown www-data:www-data /var/www/html/image /var/www/html/image/cache
exec docker-php-entrypoint "$@"
