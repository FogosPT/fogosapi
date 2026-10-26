#!/usr/bin/env bash
docker build 83-fpm-prod -t ghcr.io/fogospt/fogosapi
docker build 83-fpm-dev --build-arg PROD_TAG=latest -t ghcr.io/fogospt/fogosapi:dev
