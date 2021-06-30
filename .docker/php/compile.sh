#!/usr/bin/env bash
docker build 80-fpm-prod -t ghcr.io/fogospt/fogosapi-php
docker build 80-fpm-dev -t ghcr.io/fogospt/fogosapi-php:dev
