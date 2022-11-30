#!/bin/sh
set -e

if [ ! -f "/tmp/vendor/composer.json" ]; then
	cp /srv/api/composer.json /tmp/vendor/composer.json
fi
if [ ! -f "/tmp/vendor/composer.lock" ]; then
	cp /srv/api/composer.json /tmp/vendor/composer.lock
fi
if [ ! -d "/tmp/vendor/vendor" ]; then
	cp /srv/api/vendor /tmp/vendor/vendor -R
fi
