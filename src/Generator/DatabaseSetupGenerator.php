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

        $schemaReady = $this->runTypo3Setup($targetDir, $projectName, $dbHost, $dbName, $dbUser, $dbPassword);

        if (!$schemaReady) {
            echo "  ERROR: Database schema could not be created. Skipping page tree creation.\n";
            echo "  To finish setup manually, run:\n";
            echo "  cd {$targetDir} && php vendor/bin/typo3 setup --no-interaction --force\n";
            return;
        }

        $this->insertPageTree($pdo, $projectName);
        $this->insertSysFileStorage($pdo);

        $adminUsername = $this->stringFromConfig($config, 'admin_username');
        $adminEmail = $this->stringFromConfig($config, 'admin_email');
        $adminPassword = $this->stringFromConfig($config, 'admin_password');
        if ($adminUsername !== null && $adminUsername !== '' && $adminPassword !== null && $adminPassword !== '') {
            $this->insertBeUser($pdo, $adminUsername, $adminEmail ?? '', $adminPassword);
        }

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

    private function runTypo3Setup(
        string $targetDir,
        string $projectName,
        string $dbHost,
        string $dbName,
        string $dbUser,
        string $dbPassword,
    ): bool {
        $typo3Cli = $targetDir . '/vendor/bin/typo3';

        if (!file_exists($typo3Cli)) {
            echo "  ERROR: TYPO3 CLI not found at {$typo3Cli}\n";
            echo "  Composer install may have failed - check the output above.\n";
            return false;
        }

        echo "  Running TYPO3 setup (creates database schema)...\n";

        $settingsPath = $targetDir . '/config/system/settings.php';
        $backupPath = $settingsPath . '.installer-backup';
        $hasBackup = false;
        if (file_exists($settingsPath)) {
            copy($settingsPath, $backupPath);
            $hasBackup = true;
        }

        putenv('TYPO3_CONTEXT=Development');

        $cmd = sprintf(
            'cd %s && php %s setup --no-interaction --force --server-type=other --driver=mysqli --host=%s --port=3306 --dbname=%s --username=%s --password=%s --project-name=%s 2>&1',
            escapeshellarg($targetDir),
            escapeshellarg($typo3Cli),
            escapeshellarg($dbHost),
            escapeshellarg($dbName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($projectName),
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($hasBackup) {
            copy($backupPath, $settingsPath);
            unlink($backupPath);
        }

        if ($returnCode !== 0) {
            echo "  ERROR: typo3 setup failed (exit code {$returnCode})\n";
            echo '  ' . implode("\n  ", $output) . "\n";
            return false;
        }

        echo "  Database schema created successfully\n";
        return true;
    }

    private function insertPageTree(PDO $pdo, string $projectName): void
    {
        echo "  Creating initial page tree...\n";

        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `pages` WHERE `uid` IN (1, 2, 3)');
        } catch (PDOException $e) {
            echo "  WARNING: pages table not available, skipping page creation: " . $e->getMessage() . "\n";
            return;
        }

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

    private function insertSysFileStorage(PDO $pdo): void
    {
        echo "  Creating default sys_file_storage (fileadmin)...\n";

        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `sys_file_storage` WHERE `name` = \'fileadmin\' AND `deleted` = 0');
        } catch (PDOException $e) {
            echo '  WARNING: sys_file_storage table not available, skipping storage creation: ' . $e->getMessage() . "\n";
            return;
        }

        if ($stmt === false) {
            return;
        }

        if ((int) $stmt->fetchColumn() > 0) {
            echo "  sys_file_storage 'fileadmin' already exists, skipping storage creation\n";
            return;
        }

        $configuration = <<<'XML'
            <?xml version="1.0" encoding="utf-8" standalone="yes" ?>
            <T3FlexForms>
                <data>
                    <sheet index="sDEF">
                        <language index="lDEF">
                            <field index="basePath">
                                <value index="vDEF">fileadmin/</value>
                            </field>
                            <field index="pathType">
                                <value index="vDEF">relative</value>
                            </field>
                            <field index="caseSensitive">
                                <value index="vDEF">1</value>
                            </field>
                            <field index="baseUri">
                                <value index="vDEF"></value>
                            </field>
                        </language>
                    </sheet>
                </data>
            </T3FlexForms>
            XML;
        $configuration = preg_replace('/^            /m', '', $configuration) ?? $configuration;

        $now = time();
        $stmt = $pdo->prepare('
            INSERT INTO `sys_file_storage`
                (`pid`, `tstamp`, `crdate`, `description`, `name`, `driver`, `configuration`,
                 `is_default`, `is_browsable`, `is_public`, `is_writable`, `is_online`, `auto_extract_metadata`)
            VALUES
                (:pid, :tstamp, :crdate, :description, :name, :driver, :configuration,
                 :is_default, :is_browsable, :is_public, :is_writable, :is_online, :auto_extract_metadata)
        ');
        $stmt->execute([
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'description' => 'This is the local fileadmin/ directory. This storage mount has been created automatically by TYPO3.',
            'name' => 'fileadmin',
            'driver' => 'Local',
            'configuration' => $configuration,
            'is_default' => 1,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 1,
            'is_online' => 1,
            'auto_extract_metadata' => 1,
        ]);

        echo "  sys_file_storage 'fileadmin' created\n";
    }

    private function insertBeUser(PDO $pdo, string $username, string $email, string $password): void
    {
        echo "  Creating admin backend user '{$username}'...\n";

        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `be_users` WHERE `username` = :username AND `deleted` = 0');
        } catch (PDOException $e) {
            echo '  WARNING: be_users table not available, skipping admin user creation: ' . $e->getMessage() . "\n";
            return;
        }

        $stmt->execute(['username' => $username]);
        if ((int) $stmt->fetchColumn() > 0) {
            echo "  be_user '{$username}' already exists, skipping admin user creation\n";
            return;
        }

        $hash = password_hash($password, PASSWORD_ARGON2I);

        $now = time();
        $stmt = $pdo->prepare('
            INSERT INTO `be_users` (`pid`, `tstamp`, `crdate`, `username`, `password`, `admin`, `lang`, `email`, `options`, `workspace_perms`)
            VALUES (:pid, :tstamp, :crdate, :username, :password, :admin, :lang, :email, :options, :workspace_perms)
        ');
        $stmt->execute([
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'username' => $username,
            'password' => $hash,
            'admin' => 1,
            'lang' => 'en',
            'email' => $email,
            'options' => 0,
            'workspace_perms' => 1,
        ]);

        echo "  Admin be_user '{$username}' created\n";
    }
}
