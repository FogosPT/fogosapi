ARG PROD_TAG
FROM ghcr.io/fogospt/fogosapi:${PROD_TAG}
LABEL description="Fogos.pt API Development PHP 8.0 image"
LABEL org.opencontainers.image.source https://github.com/FogosPT/fogosapi
ENV COMPOSER_HOME /tmp

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN printf "\n" | pecl install xdebug-3.0.4

COPY xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
