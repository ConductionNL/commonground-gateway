#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-production"
	if [ "$APP_ENV" != 'prod' ]; then
		PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-development"
	fi
	ln -sf "$PHP_INI_RECOMMENDED" "$PHP_INI_DIR/php.ini"

	mkdir -p var/cache var/log
	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var

	composer install --prefer-dist --no-progress --no-suggest --no-interaction

	mkdir -p /cert

	echo "Waiting for db to be ready..."
	until bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
		sleep 1
	done

	if [ "$APP_ENV" != 'prod' ]; then

		# If you want to retain data in your dev enviroment comment this command out
		echo "Clearing the database"
		bin/console doctrine:schema:drop --full-database --force --no-interaction

		echo "Updating the database"
		bin/console doctrine:schema:update --force --no-interaction

		# If you want to retain data in your dev enviroment comment this command out
		echo "Loading fixtures"
		bin/console hautelook:fixtures:load -n --no-bundles
		#if [ "$APP_URL" == 'http://localhost' ]; then
			# Lets update the docs to show the latest chages
			#echo "Creating OAS documentation"
			#bin/console api:openapi:export --output=/srv/api/public/schema/openapi.yaml --yaml --spec-version=3


			# this should only be done in an build
			#echo "Updating Helm charts"
			#bin/console app:helm:update --location=/srv/api/helm --spec-version=3

			# this should only be done in an build
			#echo "Updating publiccode charts"
			#bin/console app:publiccode:update --location=/srv/api/public/schema/ --spec-version=0.2
		#fi

    # Load PUBLICCODE from .env and create Collections
		echo "Loading publiccode collections"
		bin/console hautelook:fixtures:load -n --no-bundles
	fi
fi

exec docker-php-entrypoint "$@"
