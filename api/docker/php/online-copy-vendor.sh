#!/bin/sh
set -e
ls -la /srv/api

if [ ! -f "/tmp/vendor/composer.json" ]; then
	echo "Copying composer.json"
	cp /srv/api/composer.json /tmp/vendor/composer.json
fi
if [ ! -f "/tmp/vendor/composer.lock" ]; then
	echo "Copying composer.lock"
	cp /srv/api/composer.lock /tmp/vendor/composer.lock
fi
if [ ! -f "/tmp/vendor/symfony.lock" ]; then
	echo "Copying symfony.json"
	cp /srv/api/symfony.lock /tmp/vendor/symfony.lock
fi
if [ ! -d "/tmp/vendor/vendor" ]; then
	echo "Copying vendor folder"
	cp /srv/api/vendor /tmp/vendor/vendor -R
fi

ls -la /tmp/vendor
