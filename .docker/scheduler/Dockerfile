ARG PROD_TAG
FROM ghcr.io/fogospt/fogosapi-php:${PROD_TAG}

LABEL description="Fogos.pt API Scheduler image"
LABEL org.opencontainers.image.source https://github.com/FogosPT/fogosapi

RUN touch /var/log/cron.log

RUN apt-get update && \
    apt-get install -y --no-install-recommends cron vim && \
    apt-get clean && rm -rf /var/lib/apt/lists/*