<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class SiteConfigGenerator extends AbstractGenerator
{
    /** @var array<string, array{title: string, locale: string, hreflang: string, flag: string, navigationTitle: string}> */
    private const LanguageMap = [
        'cs' => ['title' => 'Czech', 'locale' => 'cs_CZ', 'hreflang' => 'cs', 'flag' => 'cz', 'navigationTitle' => 'CS'],
        'en' => ['title' => 'English', 'locale' => 'en_US', 'hreflang' => 'en', 'flag' => 'en-us-gb', 'navigationTitle' => 'EN'],
        'de' => ['title' => 'German', 'locale' => 'de_DE', 'hreflang' => 'de', 'flag' => 'de', 'navigationTitle' => 'DE'],
    ];

    public function generate(array $config, string $targetDir): void
    {
        /** @var string $machineName */
        $machineName = $config['machine_name'];
        /** @var string $hostname */
        $hostname = $config['hostname'];
        /** @var string $sslPort */
        $sslPort = $config['dev_ssl_port'];
        /** @var string $projectName */
        $projectName = $config['project_name'];
        /** @var list<string> $languages */
        $languages = $config['languages'];
        /** @var string $defaultLang */
        $defaultLang = $config['default_language'];

        $orderedLangs = [$defaultLang, ...array_filter($languages, static fn(string $lang): bool => $lang !== $defaultLang)];

        $yaml = $this->buildYaml($hostname, $sslPort, $projectName, $orderedLangs);

        $configDir = $targetDir . '/config/sites/' . $machineName;
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($configDir . '/config.yaml', $yaml);
    }

    /** @param list<string> $orderedLangs */
    private function buildYaml(string $hostname, string $sslPort, string $projectName, array $orderedLangs): string
    {
        $yaml = "base: /\n";
        $yaml .= "baseVariants:\n";
        $yaml .= "  - base: 'https://{$hostname}'\n";
        $yaml .= "    condition: 'applicationContext == \"Production\"'\n";
        $yaml .= "  - base: 'https://localhost:{$sslPort}'\n";
        $yaml .= "    condition: 'applicationContext == \"Development\"'\n";
        $yaml .= "errorHandling:\n";
        $yaml .= "  -\n";
        $yaml .= "    errorCode: 404\n";
        $yaml .= "    errorHandler: Page\n";
        $yaml .= "    errorContentSource: 't3://page?uid=3'\n";
        $yaml .= "routes:\n";
        $yaml .= "  -\n";
        $yaml .= "    route: robots.txt\n";
        $yaml .= "    type: staticText\n";
        $yaml .= "    content: \"User-agent: *\\r\\nDisallow: /typo3/\\r\\nSitemap: https://{$hostname}/sitemap.xml\"\n";
        $yaml .= "  -\n";
        $yaml .= "    route: sitemap.xml\n";
        $yaml .= "    type: uri\n";
        $yaml .= "    source: 'https://{$hostname}/?type=1533906435'\n";
        $yaml .= "languages:\n";

        foreach ($orderedLangs as $index => $langCode) {
            $lang = self::LanguageMap[$langCode];

            $yaml .= "  -\n";
            $yaml .= "    title: {$lang['title']}\n";
            $yaml .= "    enabled: true\n";
            $yaml .= "    locale: {$lang['locale']}\n";
            $yaml .= "    hreflang: {$lang['hreflang']}\n";

            if ($index === 0) {
                $yaml .= "    base: /\n";
            } else {
                $yaml .= "    base: /{$langCode}/\n";
                $yaml .= "    baseVariants:\n";
                $yaml .= "      - base: 'https://{$hostname}/{$langCode}/'\n";
                $yaml .= "        condition: 'applicationContext == \"Production\"'\n";
                $yaml .= "      - base: 'https://localhost:{$sslPort}/{$langCode}/'\n";
                $yaml .= "        condition: 'applicationContext == \"Development\"'\n";
            }

            $yaml .= "    websiteTitle: {$projectName}\n";
            $yaml .= "    navigationTitle: {$lang['navigationTitle']}\n";
            $yaml .= $index === 0
                ? "    fallbackType: strict\n    fallbacks: ''\n"
                : "    fallbackType: fallback\n    fallbacks: '0'\n";
            $yaml .= "    flag: {$lang['flag']}\n";
            $yaml .= "    languageId: {$index}\n";
        }

        $yaml .= "rootPageId: 1\n";
        $yaml .= "websiteTitle: {$projectName}\n";

        return $yaml;
    }
}
