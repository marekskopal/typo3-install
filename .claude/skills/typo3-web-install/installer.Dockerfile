# The PHP the installer needs (8.5 with pdo_mysql/mysqli for the database setup, git and composer), for running it in a
# container on the database's network:
#   docker build -t typo3-installer -f installer.Dockerfile .
FROM php:8.5.12-cli
COPY --from=mlocati/php-extension-installer:2.13.2 /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions pdo_mysql mysqli intl gd zip opcache \
    && apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.11.1 /usr/bin/composer /usr/bin/composer
