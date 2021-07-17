#!/usr/bin/env bash
docker build 80-fpm-prod -t ghcr.io/fogospt/fogosapi
docker build 80-fpm-dev -t ghcr.io/fogospt/fogosapi:dev
