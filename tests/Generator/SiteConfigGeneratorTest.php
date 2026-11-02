<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Tests\Generator;

use MarekSkopal\Typo3Install\Generator\SiteConfigGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteConfigGenerator::class)]
final class SiteConfigGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/typo3-install-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testGenerateCreatesSiteConfig(): void
    {
        $config = [
            'project_name' => 'Test Web',
            'machine_name' => 'test-web',
            'hostname' => 'testweb.cz',
            'dev_ssl_port' => '4200',
            'languages' => ['cs', 'en'],
            'default_language' => 'cs',
        ];

        $generator = new SiteConfigGenerator();
        $generator->generate($config, $this->tempDir);

        $file = $this->tempDir . '/config/sites/test-web/config.yaml';
        self::assertFileExists($file);

        $content = file_get_contents($file);
        self::assertIsString($content);
        self::assertStringContainsString('rootPageId: 1', $content);
        self::assertStringContainsString('websiteTitle: Test Web', $content);
        self::assertStringContainsString("base: '%env(TYPO3_BASE_URL)%'", $content);
        self::assertStringNotContainsString('baseVariants', $content);
        self::assertStringContainsString('  - typo3/seo-sitemap', $content);
        self::assertStringContainsString('      sitemap.xml: 1533906435', $content);
        self::assertStringContainsString("    index: ''", $content);
        self::assertStringContainsString('    base: /en/', $content);
        self::assertStringNotContainsString('type: uri', $content);
        self::assertStringContainsString('locale: cs_CZ', $content);
        self::assertStringContainsString('locale: en_US', $content);
        self::assertStringContainsString('languageId: 0', $content);
        self::assertStringContainsString('languageId: 1', $content);
        self::assertStringContainsString("errorContentSource: 't3://page?uid=3'", $content);
    }

    public function testCspAllowsGoogleFontsOnlyWithTheExtension(): void
    {
        $config = [
            'project_name' => 'Test',
            'machine_name' => 'test',
            'hostname' => 'test.com',
            'languages' => ['en'],
            'default_language' => 'en',
            'extensions' => [['name' => 'marekskopal/typo3-google-font', 'version' => '^1.2']],
        ];

        (new SiteConfigGenerator())->generate($config, $this->tempDir);
        $csp = file_get_contents($this->tempDir . '/config/sites/test/csp.yaml');
        self::assertIsString($csp);
        self::assertStringContainsString('inheritDefault: true', $csp);
        self::assertStringContainsString('https://fonts.gstatic.com', $csp);

        (new SiteConfigGenerator())->generate([...$config, 'extensions' => []], $this->tempDir);
        $csp = file_get_contents($this->tempDir . '/config/sites/test/csp.yaml');
        self::assertIsString($csp);
        self::assertStringNotContainsString('fonts.googleapis.com', $csp);
    }

    public function testDefaultLanguageIsFirst(): void
    {
        $config = [
            'project_name' => 'Test',
            'machine_name' => 'test',
            'hostname' => 'test.com',
            'dev_ssl_port' => '4200',
            'languages' => ['cs', 'en', 'de'],
            'default_language' => 'en',
        ];

        $generator = new SiteConfigGenerator();
        $generator->generate($config, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/config/sites/test/config.yaml');
        self::assertIsString($content);

        // English should be languageId 0 (default), Czech and German should follow
        $enPos = strpos($content, 'locale: en_US');
        $csPos = strpos($content, 'locale: cs_CZ');
        self::assertIsInt($enPos);
        self::assertIsInt($csPos);
        self::assertLessThan($csPos, $enPos);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
