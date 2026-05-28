FROM php:8.3-fpm-bookworm

LABEL description="Fogos.pt API Production PHP 8.3 image"
LABEL org.opencontainers.image.source="https://github.com/FogosPT/fogosapi"

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update && \
    apt-get install -y --no-install-recommends locales && \
    cp /usr/share/i18n/SUPPORTED /etc/locale.gen && locale-gen && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
    git \
    libc-client-dev \
    libfreetype6-dev \
    libjpeg-dev \
    libkrb5-dev \
    libmcrypt-dev \
    libpng-dev \
    libpq-dev \
    libssl-dev \
    libwebp-dev \
    libzip-dev \
    nodejs \
    openssh-client \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && npm install --global chrome-remote-interface minimist

RUN docker-php-ext-configure \
    imap --with-kerberos --with-imap-ssl && \
    docker-php-ext-configure \
    gd --with-freetype --with-jpeg --with-webp

RUN docker-php-ext-install \
    bcmath \
    gd \
    imap \
    pcntl \
    zip && \
    pecl channel-update pecl.php.net \
    && printf "\n" | pecl install mcrypt \
    && printf "\n" | pecl install mongodb \
    && printf "\n" | pecl install timezonedb \
    && printf "\n" | pecl install redis && \
    docker-php-ext-enable \
    imap \
    mcrypt \
    mongodb \
    opcache \
    redis \
    timezonedb

COPY zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
