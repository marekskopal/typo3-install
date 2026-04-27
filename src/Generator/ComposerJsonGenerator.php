<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

class ComposerJsonGenerator extends AbstractGenerator
{
    public function generate(array $config, string $targetDir): void
    {
        /** @var string $machineName */
        $machineName = $config['machine_name'];
        /** @var string $projectName */
        $projectName = $config['project_name'];
        /** @var list<array{name: string, version: string}> $extensions */
        $extensions = $config['extensions'];

        $composerJson = [
            'name' => 'marekskopal/' . $machineName . '-web',
            'description' => $projectName,
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => './packages/*',
                ],
                [
                    'type' => 'composer',
                    'url' => 'https://composer.typo3.org/',
                ],
            ],
            'require' => [
                'php' => '^8.4',
                'typo3/cms-core' => '^14.3',
                'typo3/cms-backend' => '^14.3',
                'typo3/cms-extbase' => '^14.3',
                'typo3/cms-extensionmanager' => '^14.3',
                'typo3/cms-filelist' => '^14.3',
                'typo3/cms-fluid' => '^14.3',
                'typo3/cms-frontend' => '^14.3',
                'typo3/cms-install' => '^14.3',
                'typo3/cms-recordlist' => '^14.3',
                'typo3/cms-fluid-styled-content' => '^14.3',
                'typo3/cms-rte-ckeditor' => '^14.3',
                'typo3/cms-belog' => '^14.3',
                'typo3/cms-beuser' => '^14.3',
                'typo3/cms-seo' => '^14.3',
                'typo3/cms-t3editor' => '^14.3',
                'typo3/cms-tstemplate' => '^14.3',
                'typo3/cms-info' => '^14.3',
                'typo3/cms-redirects' => '^14.3',
                'marekskopal/typo3-web' => '@dev',
            ],
            'config' => [
                'platform' => [
                    'php' => '8.4',
                ],
                'allow-plugins' => [
                    'typo3/class-alias-loader' => true,
                    'typo3/cms-composer-installers' => true,
                    'php-http/discovery' => true,
                ],
            ],
        ];

        foreach ($extensions as $ext) {
            $composerJson['require'][$ext['name']] = $ext['version'];
        }

        $json = json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->writeFile($targetDir . '/composer.json', $json . "\n");
    }
}
