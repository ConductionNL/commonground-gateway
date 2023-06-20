#!/bin/sh
set -e

# First arg is `-f` or `--some-option`.
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-production"
	if [ "$APP_ENV" != 'prod' ]; then
		PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-development"
		ln -sf "$PHP_INI_RECOMMENDED" "$PHP_INI_DIR/php.ini"
	fi

	mkdir -p var/cache var/log

	if [ "$READ_ONLY" != 'true' ]; then
		composer install --prefer-dist --no-progress --no-suggest --no-interaction
	fi

	echo "Waiting for db to be ready..."
	until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
		sleep 1
	done

	if [ "$APP_ENV" != 'prod' ]; then
		# If you want to retain data in your dev environment comment this command out.
		echo "Clearing the database"
		bin/console doctrine:database:drop --force --no-interaction
	fi

	# Create the database if no databse exists.
	echo "Creating the database"
	bin/console doctrine:database:create --if-not-exists --no-interaction

	# Get the database inline with the newest version.
	echo "Migrating the database to the currently used version"
	bin/console doctrine:migrations:migrate --no-interaction

	echo "Clearing doctrine caches"
	bin/console cache:clear
	# Lets check if we have al the data that we need

	if [ "$APP_INIT" != 'false' ]; then
		echo "Initializing the gateway"
		bin/console commongateway:initialize
	fi

fi
exec docker-php-entrypoint "$@"
