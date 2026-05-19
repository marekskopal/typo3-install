<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use PDO;
use PDOException;

class DatabaseSetupGenerator extends AbstractGenerator
{
    public function generate(array $config, string $targetDir): void
    {
        /** @var string $machineName */
        $machineName = $config['machine_name'];
        /** @var string $projectName */
        $projectName = $config['project_name'];

        $defaultDbName = str_replace('-', '_', $machineName);

        $envVars = $this->loadEnvFile($targetDir . '/.env');

        $dbName = $this->stringFromConfig($config, 'db_name')
            ?? ($envVars['MYSQL_DATABASE'] ?? $defaultDbName);
        $dbHost = $this->stringFromConfig($config, 'db_host')
            ?? ($envVars['MYSQL_HOST'] ?? $this->getEnvString('MYSQL_HOST', '127.0.0.1'));
        $dbUser = $this->stringFromConfig($config, 'db_user')
            ?? ($envVars['MYSQL_USER'] ?? $this->getEnvString('MYSQL_USER', 'root'));
        $dbPassword = $this->stringFromConfig($config, 'db_password')
            ?? ($envVars['MYSQL_PASSWORD'] ?? $this->getEnvString('MYSQL_PASSWORD', ''));

        if ($dbHost === 'host.docker.internal') {
            $dbHost = '127.0.0.1';
        }

        echo "  Database: {$dbName}\n";
        echo "  Host: {$dbHost}\n";
        echo "  User: {$dbUser}\n";

        $pdo = $this->connectAndCreateDatabase($dbHost, $dbUser, $dbPassword, $dbName);

        $this->updateEnvFile($targetDir . '/.env', [
            'MYSQL_DATABASE' => $dbName,
            'MYSQL_USER' => $dbUser,
            'MYSQL_PASSWORD' => $dbPassword,
        ]);

        $this->runSchemaUpdate($targetDir, $dbHost, $dbName, $dbUser, $dbPassword, $pdo);
        $this->insertPageTree($pdo, $projectName);

        echo "\n  To create an admin user, run:\n";
        echo "  cd {$targetDir} && php vendor/bin/typo3 backend:createadmin\n";
        echo "\n  Database setup complete!\n";
    }

    /** @param array<string, mixed> $config */
    private function stringFromConfig(array $config, string $key): ?string
    {
        if (!array_key_exists($key, $config)) {
            return null;
        }
        $value = $config[$key];

        return is_string($value) ? $value : null;
    }

    /** @param array<string, string> $values */
    private function updateEnvFile(string $path, array $values): void
    {
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        foreach ($values as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $replacement = $key . '=' . $value;
            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace($pattern, $replacement, $contents) ?? $contents;
            } else {
                $contents = rtrim($contents, "\n") . "\n" . $replacement . "\n";
            }
        }

        file_put_contents($path, $contents);
    }

    /** @return array<string, string> */
    private function loadEnvFile(string $path): array
    {
        $envVars = [];
        if (!file_exists($path)) {
            return $envVars;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $envVars;
        }

        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $envVars[trim($key)] = trim($value);
            }
        }

        return $envVars;
    }

    private function getEnvString(string $name, string $default): string
    {
        $value = getenv($name);

        return $value !== false ? $value : $default;
    }

    private function connectAndCreateDatabase(string $host, string $user, string $password, string $dbName): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=3306;charset=utf8mb4', $host);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $dbName,
            ));
            echo "  Database '{$dbName}' created (or already exists)\n";

            $pdo->exec(sprintf('USE `%s`', $dbName));

            return $pdo;
        } catch (PDOException $e) {
            echo '  ERROR: Could not connect to database: ' . $e->getMessage() . "\n";
            echo "  Make sure MySQL is running and credentials in .env are correct.\n";
            exit(1);
        }
    }

    private function runSchemaUpdate(
        string $targetDir,
        string $dbHost,
        string $dbName,
        string $dbUser,
        string $dbPassword,
        PDO $pdo,
    ): void {
        echo "  Running TYPO3 database schema update...\n";

        putenv('MYSQL_HOST=' . $dbHost);
        putenv('MYSQL_DATABASE=' . $dbName);
        putenv('MYSQL_USER=' . $dbUser);
        putenv('MYSQL_PASSWORD=' . $dbPassword);
        putenv('TYPO3_CONTEXT=Development');

        $typo3Cli = $targetDir . '/vendor/bin/typo3';

        if (!file_exists($typo3Cli)) {
            echo "  WARNING: TYPO3 CLI not found at {$typo3Cli}\n";
            echo "  Skipping schema update - run 'vendor/bin/typo3 database:updateschema' manually\n";
            return;
        }

        $output = [];
        $returnCode = 0;
        exec('cd ' . escapeshellarg($targetDir) . ' && php ' . $typo3Cli . ' database:updateschema 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            echo "  WARNING: Schema update returned code {$returnCode}\n";
            echo '  ' . implode("\n  ", $output) . "\n";
            $this->createMinimalPagesTable($pdo);
        } else {
            echo "  Database schema updated successfully\n";
        }
    }

    private function createMinimalPagesTable(PDO $pdo): void
    {
        echo "  Attempting to create pages table manually...\n";

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `pages` (
                `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `pid` int(11) DEFAULT '0' NOT NULL,
                `tstamp` int(11) unsigned DEFAULT '0' NOT NULL,
                `crdate` int(11) unsigned DEFAULT '0' NOT NULL,
                `deleted` tinyint(4) unsigned DEFAULT '0' NOT NULL,
                `hidden` tinyint(4) unsigned DEFAULT '0' NOT NULL,
                `sorting` int(11) DEFAULT '0' NOT NULL,
                `title` varchar(255) DEFAULT '' NOT NULL,
                `doktype` int(11) unsigned DEFAULT '0' NOT NULL,
                `is_siteroot` tinyint(4) unsigned DEFAULT '0' NOT NULL,
                `slug` varchar(2048) DEFAULT '' NOT NULL,
                `sys_language_uid` int(11) DEFAULT '0' NOT NULL,
                `l10n_parent` int(11) unsigned DEFAULT '0' NOT NULL,
                PRIMARY KEY (`uid`),
                KEY `parent` (`pid`,`deleted`,`sorting`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function insertPageTree(PDO $pdo, string $projectName): void
    {
        echo "  Creating initial page tree...\n";

        $stmt = $pdo->query('SELECT COUNT(*) FROM `pages` WHERE `uid` IN (1, 2, 3)');
        if ($stmt === false) {
            return;
        }
        $existingCount = (int) $stmt->fetchColumn();

        if ($existingCount > 0) {
            echo "  Pages already exist, skipping page creation\n";
            return;
        }

        $now = time();

        $stmt = $pdo->prepare('
            INSERT INTO `pages` (`uid`, `pid`, `tstamp`, `crdate`, `sorting`, `title`, `doktype`, `is_siteroot`, `slug`, `hidden`)
            VALUES (:uid, :pid, :tstamp, :crdate, :sorting, :title, :doktype, :is_siteroot, :slug, :hidden)
        ');

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => $projectName, 'doktype' => 1, 'is_siteroot' => 1, 'slug' => '/'],
            ['uid' => 2, 'pid' => 1, 'title' => 'Information', 'doktype' => 254, 'is_siteroot' => 0, 'slug' => '/information'],
            ['uid' => 3, 'pid' => 2, 'title' => '404', 'doktype' => 1, 'is_siteroot' => 0, 'slug' => '/information/404'],
        ];

        foreach ($pages as $page) {
            $stmt->execute([
                'uid' => $page['uid'],
                'pid' => $page['pid'],
                'tstamp' => $now,
                'crdate' => $now,
                'sorting' => 256,
                'title' => $page['title'],
                'doktype' => $page['doktype'],
                'is_siteroot' => $page['is_siteroot'],
                'slug' => $page['slug'],
                'hidden' => 0,
            ]);
            echo "  Page uid={$page['uid']}: '{$page['title']}' created\n";
        }
    }
}
