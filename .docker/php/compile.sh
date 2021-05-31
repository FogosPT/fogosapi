#!/usr/bin/env bash
docker build 80-fpm-prod -t docker.pkg.github.com/fogospt/fogosapi/php:8.0-fpm-prod
docker build 80-fpm-dev -t docker.pkg.github.com/fogospt/fogosapi/php:8.0-fpm-dev
