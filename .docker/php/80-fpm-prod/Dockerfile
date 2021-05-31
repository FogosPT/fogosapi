FROM php:8.0-fpm

LABEL description="Fogos.pt API Production PHP 8.0 image"

RUN apt-get update

RUN apt-get install -y --no-install-recommends locales
RUN cp /usr/share/i18n/SUPPORTED /etc/locale.gen && locale-gen

RUN curl -sL https://deb.nodesource.com/setup_12.x | bash -

RUN apt-get install -y --no-install-recommends \
    git \
    libmcrypt-dev \
    libzip-dev \
    libssl-dev \
    libpq-dev \
    nodejs \
    openssh-client \
    unzip

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    bcmath \
    pcntl \
    zip

RUN pecl channel-update pecl.php.net \
    && printf "\n" | pecl install mcrypt-1.0.4 \
    && printf "\n" | pecl install mongodb-1.9.1 \
    && printf "\n" | pecl upgrade timezonedb-2021.1 \
    && printf "\n" | pecl install redis-5.3.4

RUN docker-php-ext-enable \
    mcrypt \
    mongodb \
    opcache \
    redis \
    timezonedb

COPY zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

RUN npm install --global chrome-remote-interface minimist
