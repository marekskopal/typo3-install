<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use RuntimeException;

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
        $source = $this->getTemplateDir() . '/' . $relativePath;
        $dest = $targetDir . '/' . ($targetRelativePath ?? $relativePath);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($source, $dest);
    }

    /**
     * Reads a template and replaces its {{name}} placeholders with the given values.
     *
     * @param array<string, string> $variables
     */
    protected function renderTemplate(string $relativePath, array $variables = []): string
    {
        $path = $this->getTemplateDir() . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException(sprintf('Template "%s" could not be read', $path));
        }

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{{' . $name . '}}'] = $value;
        }

        return strtr($content, $replacements);
    }

    /** @param array<string, string> $variables */
    protected function writeTemplate(string $relativePath, string $targetPath, array $variables = []): void
    {
        $this->writeFile($targetPath, $this->renderTemplate($relativePath, $variables));
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

    private function getTemplateDir(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }
}
