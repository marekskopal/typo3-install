<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

class MsWebGenerator extends AbstractGenerator
{
    private const StaticFiles = [
        'packages/ms_web/ext_localconf.php',
        'packages/ms_web/Configuration/Services.yaml',
        'packages/ms_web/Configuration/page.tsconfig',
        'packages/ms_web/Configuration/Sets/MsWeb/config.yaml',
        'packages/ms_web/Configuration/TCA/Overrides/sys_template.php',
        'packages/ms_web/Configuration/TCA/Overrides/tt_content.php',
        'packages/ms_web/Resources/Private/Sass/_variables.scss',
        'packages/ms_web/Resources/Private/Sass/_mixins.scss',
        'packages/ms_web/Resources/Private/Sass/_reset.scss',
        'packages/ms_web/Resources/Private/Sass/_typo.scss',
        'packages/ms_web/Resources/Private/Sass/_basic.scss',
        'packages/ms_web/Resources/Private/Sass/_header.scss',
        'packages/ms_web/Resources/Private/Sass/_footer.scss',
        'packages/ms_web/Resources/Private/Sass/styles.scss',
        'packages/ms_web/Resources/Private/Sass/rte.scss',
        'packages/ms_web/Resources/Private/Sass/print.scss',
        'packages/ms_web/Resources/Private/View/Page/Layouts/Default.html',
        'packages/ms_web/Resources/Private/View/Page/Templates/Default.html',
        'packages/ms_web/Resources/Private/View/Page/Partials/Header.html',
        'packages/ms_web/Resources/Private/View/PageParts/Templates/Navigation.html',
        'packages/ms_web/Resources/Public/Javascript/main.js',
        'packages/ms_web/Resources/Private/.htaccess',
    ];

    public function generate(array $config, string $targetDir): void
    {
        /** @var string $projectName */
        $projectName = $config['project_name'];
        /** @var list<array{name: string, version: string}> $extensions */
        $extensions = $config['extensions'];

        $this->generateComposerJson($targetDir);
        $this->generateExtEmconf($targetDir, $projectName);
        $this->copyStaticFiles($targetDir);
        $this->generateSetupTyposcript($targetDir, $extensions);
        $this->generateFooterPartial($targetDir, $projectName);
        $this->generateGitkeepFiles($targetDir);
    }

    private function generateComposerJson(string $targetDir): void
    {
        $composerJson = [
            'name' => 'marekskopal/typo3-web',
            'type' => 'typo3-cms-extension',
            'require' => [
                'typo3/cms-core' => '^14.3.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'MarekSkopal\\MsWeb\\' => 'Classes/',
                ],
            ],
            'replace' => [
                'ms_web' => 'self.version',
            ],
            'extra' => [
                'typo3/cms' => [
                    'extension-key' => 'ms_web',
                ],
            ],
        ];

        $this->writeFile(
            $targetDir . '/packages/ms_web/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    private function generateExtEmconf(string $targetDir, string $projectName): void
    {
        $this->writeTemplate('packages/ms_web/ext_emconf.php', $targetDir . '/packages/ms_web/ext_emconf.php', [
            'projectName' => addcslashes($projectName, "'\\"),
        ]);
    }

    private function copyStaticFiles(string $targetDir): void
    {
        foreach (self::StaticFiles as $file) {
            $this->copyTemplate($file, $targetDir);
        }
    }

    /** @param list<array{name: string, version: string}> $extensions */
    private function generateSetupTyposcript(string $targetDir, array $extensions): void
    {
        $googleFont = $this->hasExtension($extensions, 'marekskopal/typo3-google-font')
            ? $this->renderTemplate('fragments/google-font.typoscript')
            : '';

        $this->writeTemplate(
            'packages/ms_web/Configuration/Sets/MsWeb/setup.typoscript',
            $targetDir . '/packages/ms_web/Configuration/Sets/MsWeb/setup.typoscript',
            ['googleFont' => $googleFont],
        );
    }

    private function generateFooterPartial(string $targetDir, string $projectName): void
    {
        $this->writeTemplate(
            'packages/ms_web/Resources/Private/View/Page/Partials/Footer.html',
            $targetDir . '/packages/ms_web/Resources/Private/View/Page/Partials/Footer.html',
            ['projectName' => htmlspecialchars($projectName)],
        );
    }

    private function generateGitkeepFiles(string $targetDir): void
    {
        $extDir = $targetDir . '/packages/ms_web';

        $this->writeFile($extDir . '/Resources/Public/Css/.gitignore', "*\n!.gitignore\n");
        $this->writeFile($extDir . '/Resources/Public/Fonts/.gitignore', "*\n!.gitignore\n");
        $this->writeFile($extDir . '/Resources/Public/Icons/.gitkeep', '');
        $this->writeFile($extDir . '/Classes/.gitkeep', '');
    }
}
