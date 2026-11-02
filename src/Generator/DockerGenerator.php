<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class DockerGenerator extends AbstractGenerator
{
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

    private function generateEnvFiles(string $targetDir, string $machineName, string $httpPort, string $sslPort): void
    {
        $variables = [
            'httpPort' => $httpPort,
            'sslPort' => $sslPort,
            'dbName' => str_replace('-', '_', $machineName),
        ];

        $this->writeTemplate('env', $targetDir . '/.env', $variables);
        $this->writeTemplate('env.example', $targetDir . '/.env.example', $variables);
    }
}
