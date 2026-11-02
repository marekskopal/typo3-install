<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use const PASSWORD_ARGON2I;

class SettingsGenerator extends AbstractGenerator
{
    public function generate(array $config, string $targetDir): void
    {
        /** @var string $projectName */
        $projectName = $config['project_name'];
        /** @var string $hostname */
        $hostname = $config['hostname'];
        /** @var list<string> $languages */
        $languages = $config['languages'];

        $encryptionKey = bin2hex(random_bytes(48));
        $installToolPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2I);

        $availableLanguages = array_filter($languages, static fn(string $lang): bool => $lang !== 'en');
        $availableLangsStr = $this->buildAvailableLanguagesString(array_values($availableLanguages));

        $projectNameEscaped = addcslashes($projectName, "'");
        $installToolPasswordEscaped = addcslashes($installToolPassword, "'\\");

        $content = $this->renderTemplate('config/system/settings.php', [
            'installToolPassword' => $installToolPasswordEscaped,
            'availableLanguages' => $availableLangsStr,
            'hostname' => $hostname,
            'encryptionKey' => $encryptionKey,
            'projectName' => $projectNameEscaped,
        ]);

        $settingsDir = $targetDir . '/config/system';
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        file_put_contents($settingsDir . '/settings.php', $content);
        file_put_contents($settingsDir . '/additional.php', $this->renderTemplate('config/system/additional.php'));
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
