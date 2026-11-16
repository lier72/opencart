FROM php:7.4-apache-bullseye

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
       libzip-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 mysqli pdo_mysql gd mbstring zip soap xmlrpc opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock /opt/dependencies/
WORKDIR /opt/dependencies
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
      --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins \
    && mv upload/system/storage/vendor /opt/opencart-vendor \
    && rm -rf /root/.composer/cache

WORKDIR /var/www/html
COPY --chown=www-data:www-data upload/ ./
COPY docker/qnap/config.php /opt/opencart-config.php
COPY docker/qnap/entrypoint.sh /usr/local/bin/opencart-entrypoint
COPY docker/qnap/apache.conf /etc/apache2/conf-available/opencart.conf
COPY docker/qnap/php.ini /usr/local/etc/php/conf.d/opencart.ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' '<?php require "/opt/opencart-config.php";' > config.php \
    && printf '%s\n' '<?php define("OPENCART_ADMIN", true); require "/opt/opencart-config.php";' > admin/config.php \
    && cp .htaccess.txt .htaccess \
    && chmod +x /usr/local/bin/opencart-entrypoint \
    && mkdir -p /var/www/storage \
    && a2enconf opencart \
    && apache2ctl -t \
    && php -r 'foreach (["mysqli", "gd", "mbstring", "zip", "soap", "xmlrpc", "curl", "dom", "xmlwriter"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "Missing extension: $e\n"); exit(1); } } require "/opt/opencart-vendor/autoload.php"; if (!class_exists("Twig_Environment") && !class_exists("Twig\\Environment")) exit(1);'

ENTRYPOINT ["opencart-entrypoint"]
CMD ["apache2-foreground"]
