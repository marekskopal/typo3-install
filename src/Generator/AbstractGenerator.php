<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

abstract class AbstractGenerator
{
    /** @param array<string, mixed> $config */
    abstract public function generate(array $config, string $targetDir): void;

    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    protected function copyTemplate(string $relativePath, string $targetDir, ?string $targetRelativePath = null): void
    {
        $templateDir = dirname(__DIR__, 2) . '/templates';
        $source = $templateDir . '/' . $relativePath;
        $dest = $targetDir . '/' . ($targetRelativePath ?? $relativePath);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($source, $dest);
    }

    /** @param list<array{name: string, version: string}> $extensions */
    protected function hasExtension(array $extensions, string $composerName): bool
    {
        foreach ($extensions as $ext) {
            if ($ext['name'] === $composerName) {
                return true;
            }
        }

        return false;
    }
}
