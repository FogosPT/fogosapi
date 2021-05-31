FROM docker.pkg.github.com/fogospt/fogosapi/php:8.0-fpm-prod

LABEL description="Fogos.pt API Scheduler image"

RUN apt-get update

RUN apt-get install -y --no-install-recommends cron vim

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN touch /var/log/cron.log
