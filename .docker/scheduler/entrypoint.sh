#!/usr/bin/env bash
crontab /etc/cron.d/*
cron
tail -f /var/log/cron.log
