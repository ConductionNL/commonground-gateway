#!/bin/sh
set -e

if [! -f "/tmp/vendor/composer.json" ];
else
	cp /srv/api/composer.json /tmp/vendor/composer.json
fi
if [! -f "/tmp/vendor/composer.lock" ];
	cp /srv/api/composer.json /tmp/vendor/composer.lock
fi
if [! -d "/tmp/vendor/vendor" ];
else
	cp /srv/api/vendor /tmp/vendor/vendor -R
fi
