#!/bin/sh
set -e
ls -la /srv/api

if [ ! -f "/tmp/vendor/composer.json" ]; then
	echo "Copying composer.json"
	cp /srv/api/composer.json /tmp/vendor/ -f
fi
if [ ! -f "/tmp/vendor/composer.lock" ]; then
	echo "Copying composer.lock"
	cp /srv/api/composer.lock /tmp/vendor/ -f
fi
if [ ! -f "/tmp/vendor/symfony.lock" ]; then
	echo "Copying symfony.lock"
	cp /srv/api/symfony.lock /tmp/vendor/ -f
fi
if [ ! -f "/tmp/vendor/bundles.php" ]; then
	echo "Copying bundles.php"
	cp /srv/api/config/bundles.php /tmp/vendor/ -f
fi
if [ ! -d "/tmp/vendor/vendor" ]; then
	echo "Copying vendor folder"
	cp /srv/api/vendor /tmp/vendor/vendor -R
fi

ls -la /tmp/vendor
