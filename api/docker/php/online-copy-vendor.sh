#!/bin/sh
set -e
ls -la /srv/api
ls -la /tmp/vendor

if [ ! -f "/tmp/vendor/composer.json" ]; then
	echo "Copying composer.json"
	cp -f /srv/api/composer.json /tmp/vendor/composer.json
fi
if [ ! -f "/tmp/vendor/composer.lock" ]; then
	echo "Copying composer.lock"
	cp -f /srv/api/composer.lock /tmp/vendor/composer.lock
fi
if [ ! -f "/tmp/vendor/symfony.lock" ]; then
	echo "Copying symfony.lock"
	cp -f /srv/api/symfony.lock /tmp/vendor/symfony.lock
fi
if [ ! -f "/tmp/vendor/bundles.php" ]; then
	echo "Copying bundles.php"
	cp -f /srv/api/config/bundles.php /tmp/vendor/bundles.php
fi
if [ ! -d "/tmp/vendor/vendor" ]; then
	echo "Copying vendor folder"
	cp -R /srv/api/vendor /tmp/vendor/vendor
fi

ls -la /tmp/vendor
