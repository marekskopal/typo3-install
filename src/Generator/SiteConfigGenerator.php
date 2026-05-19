<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use const GLOB_ONLYDIR;

class SiteConfigGenerator extends AbstractGenerator
{
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
        /** @var list<array{name: string, version: string}> $extensions */
        $extensions = $config['extensions'] ?? [];

        $orderedLangs = [$defaultLang, ...array_filter($languages, static fn(string $lang): bool => $lang !== $defaultLang)];

        $dependencies = $this->buildDependencies($targetDir, $extensions);

        $yaml = $this->buildYaml($hostname, $sslPort, $projectName, $orderedLangs, $dependencies);

        $configDir = $targetDir . '/config/sites/' . $machineName;
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($configDir . '/config.yaml', $yaml);
    }

    /**
     * @param list<string> $orderedLangs
     * @param list<string> $dependencies
     */
    private function buildYaml(string $hostname, string $sslPort, string $projectName, array $orderedLangs, array $dependencies): string
    {
        $yaml = "base: /\n";
        $yaml .= "baseVariants:\n";
        $yaml .= "  - base: 'https://{$hostname}'\n";
        $yaml .= "    condition: 'applicationContext == \"Production\"'\n";
        $yaml .= "  - base: 'https://localhost:{$sslPort}'\n";
        $yaml .= "    condition: 'applicationContext == \"Development\"'\n";

        if ($dependencies !== []) {
            $yaml .= "dependencies:\n";
            foreach ($dependencies as $dependency) {
                $yaml .= "  - {$dependency}\n";
            }
        }

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

    /**
     * @param list<array{name: string, version: string}> $extensions
     * @return list<string>
     */
    private function buildDependencies(string $targetDir, array $extensions): array
    {
        $dependencies = ['typo3/fluid-styled-content'];

        $coreSet = null;
        $otherSets = [];

        foreach ($extensions as $ext) {
            if (!str_starts_with($ext['name'], 'marekskopal/')) {
                continue;
            }

            $setName = $this->readSetName($targetDir . '/vendor/' . $ext['name']);
            if ($setName === null) {
                continue;
            }

            if ($ext['name'] === 'marekskopal/typo3-core') {
                $coreSet = $setName;
            } else {
                $otherSets[] = $setName;
            }
        }

        foreach ($otherSets as $setName) {
            $dependencies[] = $setName;
        }
        if ($coreSet !== null) {
            $dependencies[] = $coreSet;
        }

        $msWebSet = $this->readSetName($targetDir . '/packages/ms_web');
        if ($msWebSet !== null) {
            $dependencies[] = $msWebSet;
        }

        return $dependencies;
    }

    private function readSetName(string $extDir): ?string
    {
        $setsDir = $extDir . '/Configuration/Sets';
        if (!is_dir($setsDir)) {
            return null;
        }

        $dirs = glob($setsDir . '/*', GLOB_ONLYDIR);
        if ($dirs === false || $dirs === []) {
            return null;
        }

        $configFile = $dirs[0] . '/config.yaml';
        if (!file_exists($configFile)) {
            return null;
        }

        $contents = file_get_contents($configFile);
        if ($contents === false) {
            return null;
        }

        if (preg_match('/^name:\s*(\S+)/m', $contents, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
