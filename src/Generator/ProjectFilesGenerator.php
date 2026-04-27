<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class ProjectFilesGenerator extends AbstractGenerator
{
    public function generate(array $config, string $targetDir): void
    {
        $this->copyTemplate('gitignore', $targetDir, '.gitignore');
        $this->copyTemplate('editorconfig', $targetDir, '.editorconfig');
        $this->copyTemplate('htaccess.txt', $targetDir, 'public/.htaccess');

        $this->writeFile($targetDir . '/log/.gitkeep', '');

        $dirs = [$targetDir . '/var', $targetDir . '/log'];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
