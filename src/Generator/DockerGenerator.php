<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class DockerGenerator extends AbstractGenerator
{
    /** @var array<string, string> */
    private const LocaleMap = [
        'cs' => 'cs_CZ.UTF-8 UTF-8',
        'en' => 'en_US.UTF-8 UTF-8',
        'de' => 'de_DE.UTF-8 UTF-8',
    ];

    private const StaticFiles = [
        'docker/php-typo3.ini',
        'docker/vhost.conf',
        'proxy/Dockerfile',
        'proxy/conf/default.conf.template',
    ];

    public function generate(array $config, string $targetDir): void
    {
        /** @var string $machineName */
        $machineName = $config['machine_name'];
        /** @var string $httpPort */
        $httpPort = $config['dev_http_port'];
        /** @var string $sslPort */
        $sslPort = $config['dev_ssl_port'];
        /** @var list<string> $languages */
        $languages = $config['languages'];

        foreach (self::StaticFiles as $file) {
            $this->copyTemplate($file, $targetDir);
        }

        $this->generateDockerfile($targetDir, $languages);
        $this->generateDockerCompose($targetDir, $machineName);
        $this->generateEnvFiles($targetDir, $machineName, $httpPort, $sslPort);
    }

    /** @param list<string> $languages */
    private function generateDockerfile(string $targetDir, array $languages): void
    {
        $localeSedCommands = [];
        foreach ($languages as $lang) {
            if (isset(self::LocaleMap[$lang])) {
                $locale = self::LocaleMap[$lang];
                $localeSedCommands[] = "    sed -i -e 's/# " . $locale . '/' . $locale . "/' /etc/locale.gen";
            }
        }
        $localeSedStr = implode(" && \\\n", $localeSedCommands);

        $content = <<<DOCKERFILE
            FROM mlocati/php-extension-installer:2.10.16 AS php-extension-installer
            FROM composer:2.9.7 AS composer
            FROM node:24.15.0 AS node
            FROM php:8.4-apache AS php

            COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

            RUN install-php-extensions \\
                curl \\
                pdo_mysql \\
                opcache \\
                sockets \\
                mbstring \\
                zip \\
                gd \\
                intl \\
                exif

            COPY --from=composer /usr/bin/composer /usr/local/bin/composer

            COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

            RUN \\
                a2enmod ssl && \\
                a2enmod rewrite && \\
                a2enmod headers && \\
                a2enmod expires && \\
                a2enmod mime && \\
                apt-get update && \\
                apt-get install -y --no-install-recommends \\
                    locales \\
                    graphicsmagick \\
                    ghostscript && \\
                rm -r /var/lib/apt/lists/*

            RUN {$localeSedStr} && \\
                dpkg-reconfigure --frontend=noninteractive locales

            COPY ./docker/php-typo3.ini /usr/local/etc/php/conf.d/php-typo3.ini

            COPY ./config /var/www/html/config
            COPY ./packages /var/www/html/packages
            COPY ./composer.json ./composer.lock /var/www/html/

            WORKDIR /var/www/html/

            RUN rm -rf /var/www/html/vendor \\
                && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-progress --classmap-authoritative -d /var/www/html/ \\
                && composer clear-cache

            COPY ./public/.htaccess /var/www/html/public/

            FROM node AS build_frontend

            RUN npm install -g pnpm

            COPY ./packages/ms_web/Resources/Private/Sass/ /var/www/html/packages/ms_web/Resources/Private/Sass/
            COPY ./package.json ./pnpm-lock.yaml ./gulpfile.js /var/www/html/

            WORKDIR /var/www/html/

            RUN \\
            	pnpm install --reporter=silent && \\
                pnpm build

            FROM php AS final

            COPY --from=build_frontend /var/www/html/packages/ms_web/Resources/Public /var/www/html/packages/ms_web/Resources/Public

            RUN \\
                mkdir var && \\
                chown -R www-data:www-data /var/www/html/

            RUN service apache2 restart
            DOCKERFILE;

        $this->writeFile($targetDir . '/Dockerfile', preg_replace('/^            /m', '', $content) . "\n");
    }

    private function generateDockerCompose(string $targetDir, string $machineName): void
    {
        $volumePrefix = str_replace('-', '_', $machineName);

        $content = <<<YAML
            services:
                proxy:
                    build:
                        context: ./proxy
                        dockerfile: Dockerfile
                    environment:
                        PROXY_HOST: \${PROXY_HOST}
                        PROXY_PORT_SSL: \${PROXY_PORT_SSL}
                    ports:
                        - "\${PROXY_PORT}:80"
                        - "\${PROXY_PORT_SSL}:443"
                    restart: unless-stopped
                    networks:
                        - frontend
                    volumes:
                        - \${PROXY_SSL_CERT}:/etc/nginx/ssl/server.pem:ro
                        - \${PROXY_SSL_KEY}:/etc/nginx/ssl/server.key:ro

                web:
                    build:
                        context: .
                        dockerfile: Dockerfile
                    environment:
                        MYSQL_HOST: \${MYSQL_HOST}
                        MYSQL_DATABASE: \${MYSQL_DATABASE}
                        MYSQL_USER: \${MYSQL_USER}
                        MYSQL_PASSWORD: \${MYSQL_PASSWORD}
                        TYPO3_CONTEXT: \${TYPO3_CONTEXT}
                        TYPO3_SMTP_USERNAME: \${TYPO3_SMTP_USERNAME}
                        TYPO3_SMTP_PASSWORD: \${TYPO3_SMTP_PASSWORD}
                        TYPO3_SMTP_SERVER: \${TYPO3_SMTP_SERVER}
                        REVERSE_PROXY_IP: \${REVERSE_PROXY_IP}
                    restart: unless-stopped
                    networks:
                        - frontend
                    volumes:
                        - {$volumePrefix}_web_public:/var/www/html/public
                        - {$volumePrefix}_web_var:/var/www/html/var
                        - ./log:/var/www/html/var/log

            networks:
                frontend:
                    name: frontend

            volumes:
                {$volumePrefix}_web_public:
                    name: {$volumePrefix}_web_public
                {$volumePrefix}_web_var:
                    name: {$volumePrefix}_web_var
            YAML;

        $this->writeFile($targetDir . '/docker-compose.yml', preg_replace('/^            /m', '', $content) . "\n");
    }

    private function generateEnvFiles(string $targetDir, string $machineName, string $httpPort, string $sslPort): void
    {
        $dbName = str_replace('-', '_', $machineName);

        $env = <<<ENV
            PROXY_HOST=localhost
            PROXY_PORT={$httpPort}
            PROXY_PORT_SSL={$sslPort}
            PROXY_SSL_CERT=/Users/marek/web/www/legito/public_html/dev_ssl/server.crt
            PROXY_SSL_KEY=/Users/marek/web/www/legito/public_html/dev_ssl/server.key
            MYSQL_HOST=host.docker.internal
            MYSQL_DATABASE={$dbName}
            MYSQL_USER=marek
            MYSQL_ROOT_PASSWORD={$dbName}
            MYSQL_PASSWORD=
            TYPO3_CONTEXT=Development
            TYPO3_SMTP_USERNAME=
            TYPO3_SMTP_PASSWORD=
            TYPO3_SMTP_SERVER=
            REVERSE_PROXY_IP=172.0.0.0/8,192.168.0.0/16
            ENV;

        $this->writeFile($targetDir . '/.env', preg_replace('/^            /m', '', $env) . "\n");

        $envExample = <<<ENV
            PROXY_HOST=localhost
            PROXY_PORT={$httpPort}
            PROXY_PORT_SSL={$sslPort}
            PROXY_SSL_CERT=/path/to/server.crt
            PROXY_SSL_KEY=/path/to/server.key
            MYSQL_HOST=host.docker.internal
            MYSQL_DATABASE={$dbName}
            MYSQL_USER=
            MYSQL_ROOT_PASSWORD=
            MYSQL_PASSWORD=
            TYPO3_CONTEXT=Development
            TYPO3_SMTP_USERNAME=
            TYPO3_SMTP_PASSWORD=
            TYPO3_SMTP_SERVER=email-smtp.eu-west-1.amazonaws.com:465
            REVERSE_PROXY_IP=172.0.0.0/8,192.168.0.0/16
            ENV;

        $this->writeFile($targetDir . '/.env.example', preg_replace('/^            /m', '', $envExample) . "\n");
    }
}
