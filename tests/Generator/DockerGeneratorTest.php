<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Tests\Generator;

use MarekSkopal\Typo3Install\Generator\DatabaseSetupGenerator;
use MarekSkopal\Typo3Install\Generator\DockerGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DockerGenerator::class)]
#[CoversClass(DatabaseSetupGenerator::class)]
final class DockerGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/typo3-install-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }

    public function testComposeFileKeepsItsIndentation(): void
    {
        $this->generate([]);

        $compose = file_get_contents($this->tempDir . '/docker-compose.yml');
        self::assertIsString($compose);
        // every line inside a service is indented; a key at column 0 breaks the YAML
        foreach (explode("\n", $compose) as $line) {
            if ($line === '' || in_array($line, ['services:', 'networks:', 'volumes:'], true)) {
                continue;
            }
            self::assertStringStartsWith('    ', $line, 'Unindented line: ' . $line);
        }
        self::assertStringContainsString("            context: ./proxy\n", $compose);
        self::assertStringContainsString('test_web_web_fileadmin:/var/www/html/public/fileadmin', $compose);
        self::assertStringNotContainsString('/var/www/html/public' . "\n", $compose);
    }

    public function testEnvHoldsTheSecretsQuoted(): void
    {
        $this->generate([]);

        $env = file_get_contents($this->tempDir . '/.env');
        self::assertIsString($env);
        self::assertMatchesRegularExpression('/^TYPO3_ENCRYPTION_KEY=[0-9a-f]{96}$/m', $env);
        self::assertMatchesRegularExpression('/^TYPO3_INSTALL_TOOL_PASSWORD=\'\$argon2i\$[^\']+\'$/m', $env);
        self::assertStringContainsString('TYPO3_BASE_URL=https://localhost:4200/', $env);
        self::assertStringNotContainsString('MYSQL_ROOT_PASSWORD', $env);
    }

    public function testSkipDockerLeavesOutTheStack(): void
    {
        $this->generate(['skip_docker' => true]);

        self::assertFileDoesNotExist($this->tempDir . '/docker-compose.yml');
        self::assertDirectoryDoesNotExist($this->tempDir . '/proxy');
        self::assertFileExists($this->tempDir . '/Dockerfile');
        self::assertFileExists($this->tempDir . '/docker/entrypoint.sh');
        self::assertFileExists($this->tempDir . '/.dockerignore');
        self::assertFileExists($this->tempDir . '/.env');
    }

    public function testContainerHost(): void
    {
        self::assertSame('host.docker.internal', DatabaseSetupGenerator::containerHost('127.0.0.1'));
        self::assertSame('host.docker.internal', DatabaseSetupGenerator::containerHost('localhost'));
        self::assertSame('db', DatabaseSetupGenerator::containerHost('db'));
    }

    /** @param array<string, mixed> $config */
    private function generate(array $config): void
    {
        (new DockerGenerator())->generate([
            'machine_name' => 'test-web',
            'hostname' => 'testweb.cz',
            'dev_http_port' => '4100',
            'dev_ssl_port' => '4200',
            'languages' => ['cs', 'en'],
            ...$config,
        ], $this->tempDir);
    }
}
