<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Tests\Generator;

use MarekSkopal\Typo3Install\Generator\ComposerJsonGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerJsonGenerator::class)]
final class ComposerJsonGeneratorTest extends TestCase
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

    public function testGenerateCreatesValidComposerJson(): void
    {
        $config = [
            'project_name' => 'Test Project',
            'machine_name' => 'test-project',
            'extensions' => [
                ['name' => 'marekskopal/typo3-core', 'version' => '^14.3'],
            ],
        ];

        $generator = new ComposerJsonGenerator();
        $generator->generate($config, $this->tempDir);

        $file = $this->tempDir . '/composer.json';
        self::assertFileExists($file);

        $content = file_get_contents($file);
        self::assertIsString($content);

        /** @var array{name: string, description: string, require: array<string, string>} $json */
        $json = json_decode($content, true);
        self::assertSame('marekskopal/test-project-web', $json['name']);
        self::assertSame('Test Project', $json['description']);
        self::assertSame('^8.4', $json['require']['php']);
        self::assertSame('^14.3', $json['require']['typo3/cms-core']);
        self::assertSame('^14.3', $json['require']['marekskopal/typo3-core']);
        self::assertSame('@dev', $json['require']['marekskopal/typo3-web']);
    }

    public function testGenerateIncludesSelectedExtensions(): void
    {
        $config = [
            'project_name' => 'Test',
            'machine_name' => 'test',
            'extensions' => [
                ['name' => 'marekskopal/typo3-core', 'version' => '^14.3'],
                ['name' => 'marekskopal/typo3-google-font', 'version' => '^1.0'],
                ['name' => 'marekskopal/typo3-mcp-server', 'version' => '^0.3.0'],
            ],
        ];

        $generator = new ComposerJsonGenerator();
        $generator->generate($config, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/composer.json');
        self::assertIsString($content);

        /** @var array{require: array<string, string>} $json */
        $json = json_decode($content, true);
        self::assertSame('^1.0', $json['require']['marekskopal/typo3-google-font']);
        self::assertSame('^0.3.0', $json['require']['marekskopal/typo3-mcp-server']);
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
