<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class SettingsGenerator extends AbstractGenerator
{
    private const DefaultMcpBasePath = '/mcp';

    public function generate(array $config, string $targetDir): void
    {
        /** @var string $projectName */
        $projectName = $config['project_name'];
        /** @var string $hostname */
        $hostname = $config['hostname'];
        /** @var list<string> $languages */
        $languages = $config['languages'];
        /** @var list<array{name: string, version: string}> $extensions */
        $extensions = $config['extensions'] ?? [];
        $mcpBasePath = is_string($config['mcp_base_path'] ?? null) && $config['mcp_base_path'] !== ''
            ? $config['mcp_base_path']
            : self::DefaultMcpBasePath;

        $availableLanguages = array_filter($languages, static fn(string $lang): bool => $lang !== 'en');
        $availableLangsStr = $this->buildAvailableLanguagesString(array_values($availableLanguages));

        // The encryption key and the install tool password live in .env (DockerGenerator), read by additional.php.
        $content = $this->renderTemplate('config/system/settings.php', [
            'availableLanguages' => $availableLangsStr,
            'mailDomain' => self::mailDomain($hostname),
            'mcpServer' => $this->hasExtension($extensions, 'marekskopal/typo3-mcp-server')
                ? self::mcpServerSettings($mcpBasePath)
                : '',
            'projectName' => addcslashes($projectName, "'"),
        ]);

        $settingsDir = $targetDir . '/config/system';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        file_put_contents($settingsDir . '/settings.php', $content);
        file_put_contents($settingsDir . '/additional.php', $this->renderTemplate('config/system/additional.php'));
    }

    /** The domain for noreply@: the hostname without a leading www. */
    public static function mailDomain(string $hostname): string
    {
        return preg_replace('/^www\./', '', $hostname) ?? $hostname;
    }

    private static function mcpServerSettings(string $basePath): string
    {
        return sprintf("\n        'ms_mcp_server' => [\n            'mcpBasePath' => '%s',\n        ],", addcslashes($basePath, "'"));
    }

    /** @param list<string> $languages */
    private function buildAvailableLanguagesString(array $languages): string
    {
        if ($languages === []) {
            return '[]';
        }

        $langLines = '';
        foreach ($languages as $lang) {
            $langLines .= "\n                '" . $lang . "',";
        }

        return '[' . $langLines . "\n            ]";
    }
}
