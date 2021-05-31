FROM docker.pkg.github.com/fogospt/fogosapi/php:8.0-fpm-prod

LABEL description="Fogos.pt API Development PHP 8.0 image"

RUN printf "\n" | pecl install xdebug-3.0.4

COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

ENV COMPOSER_HOME /tmp
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
