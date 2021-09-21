FROM php:7.4-fpm-alpine AS api_platform_php

RUN apk add --no-cache \
		acl \
		file \
		gettext \
		git \
		bash \
	;

ARG APCU_VERSION=5.1.17

RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		libzip-dev \
		libpng-dev \
		postgresql-dev \
		zlib-dev \
		gmp-dev \
	; \
	\
	docker-php-ext-install -j$(nproc) \
		intl \
		pdo_pgsql \
		zip \
		mysqli \
		pdo_mysql \
		pcntl \
		gd \
		gmp \
	; \
	pecl install \
		apcu-${APCU_VERSION} \
		redis \
	; \
	rm -rf /tmp/pear \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		opcache \
		mysqli \
		redis \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .api-phpexts-rundeps $runDeps; \
	\
	apk del .build-deps

RUN apk add --no-cache \
		acl \
		file \
		gettext \
		git \
	;

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/conf.d/api-platform.ini $PHP_INI_DIR/conf.d/api-platform.ini

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

# install Symfony Flex globally to speed up download of Composer packages (parallelized prefetching)
RUN set -eux; \
	composer global require "symfony/flex" --prefer-dist --no-progress --no-suggest --classmap-authoritative --no-scripts; \
	composer clear-cache
ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /srv/api

# build for production
ARG APP_ENV=prod

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.json composer.lock symfony.lock ./

RUN set -eux; \
	composer install --prefer-dist --no-dev --no-scripts --no-progress --no-suggest --no-scripts; \
	composer clear-cache

# copy only specifically what we need
COPY .env ./
COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/
COPY migrations migrations/
COPY fixtures fixtures/

RUN apk add --no-cache $PHPIZE_DEPS \
	&& pecl install xdebug-2.9.2 \
	&& docker-php-ext-enable xdebug;\
	echo -e "xdebug.overload_var_dump=off" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	chmod +x bin/console; sync

VOLUME /srv/api/var

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
