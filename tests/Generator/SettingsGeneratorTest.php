<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Tests\Generator;

use MarekSkopal\Typo3Install\Generator\SettingsGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingsGenerator::class)]
final class SettingsGeneratorTest extends TestCase
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

    public function testSettingsHoldNoSecrets(): void
    {
        $settings = $this->generate([]);

        self::assertStringContainsString("'encryptionKey' => '',", $settings);
        self::assertStringContainsString("'installToolPassword' => '',", $settings);
        self::assertStringContainsString("'systemMaintainers' => [],", $settings);
        self::assertStringContainsString("'reverseProxySSL' => '',", $settings);
        self::assertStringContainsString("'defaultMailFromAddress' => 'noreply@testweb.cz',", $settings);

        $additional = file_get_contents($this->tempDir . '/config/system/additional.php');
        self::assertIsString($additional);
        self::assertStringContainsString("getenv('TYPO3_ENCRYPTION_KEY')", $additional);
        self::assertStringContainsString("getenv('MYSQL_PORT')", $additional);
    }

    public function testMcpBasePathIsSetWithTheMcpServer(): void
    {
        $extension = ['name' => 'marekskopal/typo3-mcp-server', 'version' => '^1.4'];

        self::assertStringContainsString(
            "'mcpBasePath' => '/typo3-web/mcp',",
            $this->generate(['extensions' => [$extension], 'mcp_base_path' => '/typo3-web/mcp']),
        );
        self::assertStringContainsString("'mcpBasePath' => '/mcp',", $this->generate(['extensions' => [$extension]]));
        self::assertStringNotContainsString('ms_mcp_server', $this->generate([]));
    }

    public function testMailDomainDropsWww(): void
    {
        self::assertSame('izbik.com', SettingsGenerator::mailDomain('www.izbik.com'));
        self::assertSame('testweb.cz', SettingsGenerator::mailDomain('testweb.cz'));
    }

    /** @param array<string, mixed> $config */
    private function generate(array $config): string
    {
        (new SettingsGenerator())->generate([
            'project_name' => 'Test Web',
            'hostname' => 'www.testweb.cz',
            'languages' => ['cs', 'en'],
            ...$config,
        ], $this->tempDir);

        $settings = file_get_contents($this->tempDir . '/config/system/settings.php');
        self::assertIsString($settings);

        return $settings;
    }
}
