<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use const PASSWORD_ARGON2I;

class DockerGenerator extends AbstractGenerator
{
    private const LocaleMap = [
        'cs' => 'cs_CZ.UTF-8 UTF-8',
        'en' => 'en_US.UTF-8 UTF-8',
        'de' => 'de_DE.UTF-8 UTF-8',
    ];

    private const DefaultSslCert = './ssl/server.crt';

    private const DefaultSslKey = './ssl/server.key';

    private const StaticFiles = [
        'docker/php-typo3.ini',
        'docker/vhost.conf',
        'docker/entrypoint.sh',
    ];

    /** The standalone stack: an nginx proxy with TLS in front of the web container. */
    private const StackFiles = [
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
        $sslCert = is_string($config['dev_ssl_cert'] ?? null) ? $config['dev_ssl_cert'] : self::DefaultSslCert;
        $sslKey = is_string($config['dev_ssl_key'] ?? null) ? $config['dev_ssl_key'] : self::DefaultSslKey;

        /** @var string $hostname */
        $hostname = $config['hostname'];
        // An existing stack (--skip-docker) brings its own compose file and proxy; the image and .env are still needed.
        $skipStack = ($config['skip_docker'] ?? false) === true;

        foreach (self::StaticFiles as $file) {
            $this->copyTemplate($file, $targetDir);
        }
        $this->copyTemplate('dockerignore', $targetDir, '.dockerignore');

        $this->generateDockerfile($targetDir, $languages);
        if (!$skipStack) {
            foreach (self::StackFiles as $file) {
                $this->copyTemplate($file, $targetDir);
            }
            $this->generateDockerCompose($targetDir, $machineName);
        }
        $this->generateEnvFiles($targetDir, $machineName, $hostname, $httpPort, $sslPort, $sslCert, $sslKey);
    }

    /** @param list<string> $languages */
    private function generateDockerfile(string $targetDir, array $languages): void
    {
        $localeRunLines = [];
        $localedefLines = [];
        foreach ($languages as $lang) {
            if (!isset(self::LocaleMap[$lang])) {
                continue;
            }
            $locale = self::LocaleMap[$lang];
            $localeName = explode('.', $locale, 2)[0];
            $localeRunLines[] = sprintf('sed -i -e \'s/# %1$s/%1$s/\' /etc/locale.gen', $locale);
            $localedefLines[] = sprintf('localedef -i %1$s -f UTF-8 %1$s', $localeName);
        }
        $localeRunLines[] = 'dpkg-reconfigure --frontend=noninteractive locales';
        foreach ($localedefLines as $line) {
            $localeRunLines[] = $line;
        }
        $localeRunBlock = implode(" && \\\n    ", $localeRunLines);

        $this->writeTemplate('Dockerfile', $targetDir . '/Dockerfile', [
            'localeRunBlock' => $localeRunBlock,
        ]);
    }

    private function generateDockerCompose(string $targetDir, string $machineName): void
    {
        $this->writeTemplate('docker-compose.yml', $targetDir . '/docker-compose.yml', [
            'volumePrefix' => str_replace('-', '_', $machineName),
        ]);
    }

    private function generateEnvFiles(
        string $targetDir,
        string $machineName,
        string $hostname,
        string $httpPort,
        string $sslPort,
        string $sslCert,
        string $sslKey,
    ): void
    {
        $variables = [
            'httpPort' => $httpPort,
            'sslPort' => $sslPort,
            'hostname' => $hostname,
            'dbName' => str_replace('-', '_', $machineName),
        ];

        $this->writeTemplate('env', $targetDir . '/.env', [
            ...$variables,
            'sslCert' => $sslCert,
            'sslKey' => $sslKey,
            'encryptionKey' => bin2hex(random_bytes(48)),
            'installToolPassword' => password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2I),
        ]);
        $this->writeTemplate('env.example', $targetDir . '/.env.example', $variables);
    }
}
