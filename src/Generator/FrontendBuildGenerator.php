<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class FrontendBuildGenerator extends AbstractGenerator
{
    public function generate(array $config, string $targetDir): void
    {
        $this->copyTemplate('package.json', $targetDir);
        $this->copyTemplate('gulpfile.js', $targetDir);
    }
}
