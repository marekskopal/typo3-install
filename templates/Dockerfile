FROM mlocati/php-extension-installer:2.13.2 AS php-extension-installer
FROM composer:2.11.1 AS composer
FROM node:24.22.2 AS node
FROM php:8.5.12-apache AS php

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    curl \
    pdo_mysql \
    opcache \
    sockets \
    mbstring \
    zip \
    gd \
    intl \
    exif

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN \
    a2enmod ssl && \
    a2enmod rewrite && \
    a2enmod headers && \
    a2enmod expires && \
    a2enmod mime && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
        locales \
        graphicsmagick \
        ghostscript && \
    rm -r /var/lib/apt/lists/*

RUN {{localeRunBlock}}

COPY ./docker/php-typo3.ini /usr/local/etc/php/conf.d/php-typo3.ini

COPY ./config /var/www/html/config
COPY ./packages /var/www/html/packages
COPY ./composer.json ./composer.lock /var/www/html/

WORKDIR /var/www/html/

RUN rm -rf /var/www/html/vendor \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-progress --classmap-authoritative -d /var/www/html/ \
    && composer clear-cache

COPY ./public/.htaccess /var/www/html/public/
# Creates the default file storage's base path. Without it TYPO3 fails to
# initialize storage [1] on every request that touches a file reference.
# The .htaccess is TYPO3's CSP hardening for user-uploaded resources.
COPY ./public/fileadmin/.htaccess /var/www/html/public/fileadmin/

FROM node AS build_frontend

RUN npm install -g pnpm

COPY ./packages/ms_web/Resources/Private/Sass/ /var/www/html/packages/ms_web/Resources/Private/Sass/
COPY ./package.json ./pnpm-lock.yaml ./pnpm-workspace.yaml ./gulpfile.js /var/www/html/

WORKDIR /var/www/html/

RUN \
	pnpm install --reporter=silent && \
    pnpm build

FROM php AS final

COPY --from=build_frontend /var/www/html/packages/ms_web/Resources/Public /var/www/html/packages/ms_web/Resources/Public

RUN \
    mkdir -p var/log && \
    chown -R www-data:www-data /var/www/html/

RUN service apache2 restart
