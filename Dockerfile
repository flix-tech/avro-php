ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-cli-alpine

ARG XDEBUG_VERSION=3.2.0
RUN apk add --update linux-headers \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache --virtual .runtime-deps git libzip-dev gmp-dev \
    && docker-php-ext-install zip gmp \
    && pecl install xdebug-$XDEBUG_VERSION \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.max_nesting_level=15000" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
    && echo "xdebug.client_host=localhost" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
    && echo "xdebug.idekey=PHPSTORM" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
    && echo "xdebug.remote_handler=dbgp" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
    && echo "xdebug.mode=develop" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini" \
    && git clone --recursive --depth=1 https://github.com/kjdev/php-ext-snappy.git \
    && cd php-ext-snappy \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable snappy \
    && apk del .build-deps
