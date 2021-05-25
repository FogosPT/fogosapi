#######################################################################
#            Laravel/Lumen 5.8 Application - Dockerfile v0.5          #
#######################################################################

#------------- Setup Environment -------------------------------------------------------------

# Pull base image
FROM ubuntu:18.04

# Install common tools
RUN apt-get update
RUN apt-get install -y wget curl nano htop git unzip bzip2 software-properties-common locales

# Set evn var to enable xterm terminal
ENV TERM=xterm

# Set timezone to UTC to avoid tzdata interactive mode during build
ENV TZ=Europe/Lisbon
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Set working directory
WORKDIR /var/www/html

# Set up locales
# RUN locale-gen

#------------- Application Specific Stuff ----------------------------------------------------

# Install PHP
RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y \
    php8.0-fpm \
    php8.0-common \
    php8.0-curl \
    php8.0-mysql \
    php8.0-mbstring \
    php8.0-xml \
    php8.0-bcmath \
    php8.0-dev \
    php-pear \
    libssl-dev \
    php-xml \
    php-redis \
    cron \
    poppler-utils

RUN pecl install mongodb

RUN echo "extension=mongodb.so" > /etc/php/8.0/fpm/conf.d/20-mongodb.ini && \
	echo "extension=mongodb.so" > /etc/php/8.0/cli/conf.d/20-mongodb.ini && \
	echo "extension=mongodb.so" > /etc/php/8.0/mods-available/mongodb.ini


# Install NPM and Node.js
RUN curl -sL https://deb.nodesource.com/setup_10.x | bash -
RUN apt-get install -y nodejs

#------------- FPM & Nginx configuration ----------------------------------------------------

# Config fpm to use TCP instead of unix socket
ADD assets/www.conf /etc/php/8.0/fpm/pool.d/www.conf
RUN mkdir -p /var/run/php

#------------- Composer & laravel configuration ----------------------------------------------------

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

#------------- Supervisor Process Manager ----------------------------------------------------

# Install supervisor
RUN apt-get install -y supervisor
RUN mkdir -p /var/log/supervisor
ADD assets/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

#------------- Puppeteer & Chrome Headless  ---------------------------------------------------------------
# Puppeteer
RUN npm install --global chrome-remote-interface
RUN npm install --global minimist

#------------- Cron  ---------------------------------------------------------------
RUN touch /var/log/cron.log
RUN (crontab -l ; echo "* * * * * cd /var/www/html && php artisan schedule:run >> /var/log/cron.log") | crontab
#------------- Container Config ---------------------------------------------------------------


# Set supervisor to manage container processes
ENTRYPOINT ["/usr/bin/supervisord"]
