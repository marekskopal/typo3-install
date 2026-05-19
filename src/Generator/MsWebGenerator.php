<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

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
        $content = <<<PHP
            <?php

            \$EM_CONF[\$_EXTKEY] = [
                'title' => '{$projectName} - Web',
                'description' => 'Web extension.',
                'category' => 'misc',
                'shy' => '',
                'version' => '1.0.0',
                'dependencies' => '',
                'conflicts' => '',
                'priority' => '',
                'loadOrder' => '',
                'module' => '',
                'state' => 'stable',
                'uploadfolder' => 0,
                'createDirs' => '',
                'modify_tables' => '',
                'clearCacheOnLoad' => 0,
                'lockType' => '',
                'author' => 'Marek Skopal',
                'author_email' => 'skopal.marek@gmail.com',
                'CGLcompliance' => '',
                'CGLcompliance_note' => '',
                'constraints' => [
                    'depends' => [
                        'ms_core' => '1.0.0',
                    ],
                    'conflicts' =>[
                    ],
                    'suggests' => [
                    ],
                ]
            ];
            PHP;

        // Remove the leading indentation from heredoc
        $content = preg_replace('/^            /m', '', $content);

        $this->writeFile($targetDir . '/packages/ms_web/ext_emconf.php', $content . "\n");
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
        $content = <<<'TYPOSCRIPT'
            page.shortcutIcon = EXT:ms_web/Resources/Public/Icons/favicon.svg

            page.meta {
                robots = index, follow
                googlebot = snippet, archive
                description.override.field = description
            }

            page.10 {
                templateName.stdWrap.cObject = CASE
                templateName.stdWrap.cObject {
                    pagets__default = TEXT
                    pagets__default.value = Default.html
                    pagets__default.insertData = 1
                }
            }

            page.includeCSS {
                print = {$page.includePath.css}print.min.css
                print.media = print

                lightGallery >
            }

            page.includeJSFooter {
                msGallery >
                main = EXT:ms_web/Resources/Public/Javascript/main.js
                main.async = 1
            }

            page.includeJSFooterlibs {
                jquery >
                popperjs >
                bootstrap >
                lightGallery >
            }

            TYPOSCRIPT;

        $content = preg_replace('/^            /m', '', $content);

        if ($this->hasExtension($extensions, 'marekskopal/typo3-google-font')) {
            $googleFont = <<<'TYPOSCRIPT'
                plugin.tx_msgooglefont {
                    settings {
                        fontSrc {
                            1 = https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap
                        }
                    }
                }

                TYPOSCRIPT;

            $content .= preg_replace('/^                /m', '', $googleFont);
        }

        $footer = <<<'TYPOSCRIPT'
            lib.navigation {
                dataProcessing.30 = TYPO3\CMS\Frontend\DataProcessing\LanguageMenuProcessor
                dataProcessing.30 {
                    languages = auto
                    as = languageNavigation
                }
            }

            config.spamProtectEmailAddresses = 0
            config.spamProtectEmailAddresses_atSubst >
            config.spamProtectEmailAddresses_lastDotSubst >
            TYPOSCRIPT;

        $content .= preg_replace('/^            /m', '', $footer);

        $this->writeFile($targetDir . '/packages/ms_web/Configuration/Sets/MsWeb/setup.typoscript', $content . "\n");
    }

    private function generateFooterPartial(string $targetDir, string $projectName): void
    {
        $escapedProjectName = htmlspecialchars($projectName);
        $content = <<<HTML
            <footer class="site-footer">
                <span>&copy;</span> <f:format.date format="Y" date="now" /> <f:link.page pageUid="1">{$escapedProjectName}</f:link.page>
            </footer>
            HTML;

        $content = preg_replace('/^            /m', '', $content);

        $this->writeFile($targetDir . '/packages/ms_web/Resources/Private/View/Page/Partials/Footer.html', $content . "\n");
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
