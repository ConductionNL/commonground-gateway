#!/bin/sh
set -e
ls -la /srv/api

if [ ! -f "/tmp/vendor/composer.json" ]; then
	touch /tmp/vendor/composer.json
	echo "Copying composer.json"
	cp /srv/api/composer.json /tmp/vendor/ -nf
fi
if [ ! -f "/tmp/vendor/composer.lock" ]; then
	touch /tmp/vendor/composer.lock
	echo "Copying composer.lock"
	cp /srv/api/composer.lock /tmp/vendor/ -nf
fi
if [ ! -f "/tmp/vendor/symfony.lock" ]; then
	touch /tmp/vendor/symfony.lock
	echo "Copying symfony.lock"
	cp /srv/api/symfony.lock /tmp/vendor/ -nf
fi
if [ ! -d "/tmp/vendor/vendor" ]; then
	echo "Copying vendor folder"
	cp /srv/api/vendor /tmp/vendor/vendor -Rn
fi

ls -la /tmp/vendor
