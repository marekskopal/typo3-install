<?php

declare(strict_types=1);

namespace MarekSkopal\Typo3Install\Generator;

use PDO;
use PDOException;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const PASSWORD_ARGON2I;

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

        $dbPort = (int) ($this->stringFromConfig($config, 'db_port')
            ?? ($envVars['MYSQL_PORT'] ?? $this->getEnvString('MYSQL_PORT', '3306')));

        // The installer runs on the host (or in a container next to the database); the site runs in its container.
        $connectHost = $dbHost === 'host.docker.internal' ? '127.0.0.1' : $dbHost;

        echo sprintf("  Database: %s\n", $dbName);
        echo sprintf("  Host: %s:%d\n", $connectHost, $dbPort);
        echo sprintf("  User: %s\n", $dbUser);

        $pdo = $this->connectAndCreateDatabase($connectHost, $dbPort, $dbUser, $dbPassword, $dbName);

        $this->updateEnvFile($targetDir . '/.env', [
            'MYSQL_HOST' => self::containerHost($dbHost),
            'MYSQL_PORT' => (string) $dbPort,
            'MYSQL_DATABASE' => $dbName,
            'MYSQL_USER' => $dbUser,
            'MYSQL_PASSWORD' => $dbPassword,
        ]);

        $schemaReady = $this->runTypo3Setup($targetDir, $projectName, $connectHost, $dbPort, $dbName, $dbUser, $dbPassword);

        if (!$schemaReady) {
            echo "  ERROR: Database schema could not be created. Skipping page tree creation.\n";
            echo "  To finish setup manually, run:\n";
            echo sprintf("  cd %s && php vendor/bin/typo3 setup --no-interaction --force\n", $targetDir);
            return;
        }

        // The admin first, so the pages can be owned by it.
        $adminUid = 0;
        $adminUsername = $this->stringFromConfig($config, 'admin_username');
        $adminEmail = $this->stringFromConfig($config, 'admin_email');
        $adminPassword = $this->stringFromConfig($config, 'admin_password');
        if ($adminUsername !== null && $adminUsername !== '' && $adminPassword !== null && $adminPassword !== '') {
            $adminUid = $this->insertBeUser($pdo, $adminUsername, $adminEmail ?? '', $adminPassword);
        }

        /** @var list<string> $languages */
        $languages = is_array($config['languages'] ?? null) ? $config['languages'] : [];
        $defaultLanguage = $this->stringFromConfig($config, 'default_language') ?? ($languages[0] ?? '');
        $translations = count(array_filter($languages, static fn(string $lang): bool => $lang !== $defaultLanguage));

        $this->insertPageTree($pdo, $projectName, $adminUid, $translations);
        $this->insertSysFileStorage($pdo);

        echo "\n  Database setup complete!\n";
    }

    /**
     * The database host as the site's container sees it: the host machine's own address becomes
     * host.docker.internal, a service name on a shared network (e.g. "db") stays as it is.
     */
    public static function containerHost(string $host): string
    {
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true) ? 'host.docker.internal' : $host;
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
            $contents = preg_match($pattern, $contents) === 1
                ? preg_replace($pattern, $replacement, $contents) ?? $contents
                : rtrim($contents, "\n") . "\n" . $replacement . "\n";
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
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $envVars[trim($key)] = trim($value);
        }

        return $envVars;
    }

    private function getEnvString(string $name, string $default): string
    {
        $value = getenv($name);

        return $value !== false ? $value : $default;
    }

    private function connectAndCreateDatabase(string $host, int $port, string $user, string $password, string $dbName): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $dbName,
            ));
            echo sprintf("  Database '%s' created (or already exists)\n", $dbName);

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
        int $dbPort,
        string $dbName,
        string $dbUser,
        string $dbPassword,
    ): bool {
        $typo3Cli = $targetDir . '/vendor/bin/typo3';

        if (!file_exists($typo3Cli)) {
            echo sprintf("  ERROR: TYPO3 CLI not found at %s\n", $typo3Cli);
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
            'cd %s && php %s setup --no-interaction --force --server-type=other --driver=mysqli --host=%s --port=%d --dbname=%s --username=%s --password=%s --project-name=%s 2>&1',
            escapeshellarg($targetDir),
            escapeshellarg($typo3Cli),
            escapeshellarg($dbHost),
            $dbPort,
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
            echo sprintf("  ERROR: typo3 setup failed (exit code %d)\n", $returnCode);
            echo '  ' . implode("\n  ", $output) . "\n";
            return false;
        }

        echo "  Database schema created successfully\n";
        return true;
    }

    /**
     * The root page, the Information folder and its 404 page, owned by the admin with TYPO3's default permissions
     * (as a page created in the backend gets them), and their translation into each further site language, which
     * a page needs to be reachable (and the 404 handler of that language to find its error page).
     */
    private function insertPageTree(PDO $pdo, string $projectName, int $ownerUid, int $translations): void
    {
        echo "  Creating initial page tree...\n";

        try {
            $stmt = $pdo->query('SELECT COUNT(*) FROM `pages` WHERE `uid` IN (1, 2, 3)');
        } catch (PDOException $e) {
            echo '  WARNING: pages table not available, skipping page creation: ' . $e->getMessage() . "\n";
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
            INSERT INTO `pages` (`uid`, `pid`, `tstamp`, `crdate`, `sorting`, `title`, `doktype`, `is_siteroot`, `slug`,
                `hidden`, `sys_language_uid`, `l10n_parent`, `l10n_source`, `perms_userid`, `perms_groupid`, `perms_user`,
                `perms_group`, `perms_everybody`)
            VALUES (:uid, :pid, :tstamp, :crdate, :sorting, :title, :doktype, :is_siteroot, :slug,
                0, :sys_language_uid, :l10n_parent, :l10n_parent, :perms_userid, 0, 31, 27, 0)
        ');

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => $projectName, 'doktype' => 1, 'is_siteroot' => 1, 'slug' => '/'],
            ['uid' => 2, 'pid' => 1, 'title' => 'Information', 'doktype' => 254, 'is_siteroot' => 0, 'slug' => '/information'],
            ['uid' => 3, 'pid' => 2, 'title' => '404', 'doktype' => 1, 'is_siteroot' => 0, 'slug' => '/information/404'],
        ];

        $nextUid = count($pages) + 1;
        foreach ($pages as $page) {
            $rows = [[...$page, 'sys_language_uid' => 0, 'l10n_parent' => 0]];
            for ($languageId = 1; $languageId <= $translations; $languageId++) {
                $rows[] = [...$page, 'uid' => $nextUid, 'sys_language_uid' => $languageId, 'l10n_parent' => $page['uid']];
                $nextUid++;
            }

            foreach ($rows as $row) {
                $stmt->execute([
                    ...$row,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'sorting' => 256,
                    'perms_userid' => $ownerUid,
                ]);
                echo sprintf(
                    "  Page uid=%d: '%s'%s created\n",
                    $row['uid'],
                    $row['title'],
                    $row['sys_language_uid'] > 0 ? sprintf(' (language %d)', $row['sys_language_uid']) : '',
                );
            }
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

        $configuration = rtrim($this->renderTemplate('fragments/sys_file_storage_configuration.xml'), "\n");

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

    /** @return int The admin's uid (0 when it could not be created) */
    private function insertBeUser(PDO $pdo, string $username, string $email, string $password): int
    {
        echo sprintf("  Creating admin backend user '%s'...\n", $username);

        try {
            $pdo->query('SELECT 1 FROM `be_users` LIMIT 1');
        } catch (PDOException $e) {
            echo '  WARNING: be_users table not available, skipping admin user creation: ' . $e->getMessage() . "\n";
            return 0;
        }

        $stmt = $pdo->prepare('SELECT `uid` FROM `be_users` WHERE `username` = :username AND `deleted` = 0');
        $stmt->execute(['username' => $username]);
        $existingUid = (int) $stmt->fetchColumn();
        if ($existingUid > 0) {
            echo sprintf("  be_user '%s' already exists, skipping admin user creation\n", $username);
            return $existingUid;
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

        echo sprintf("  Admin be_user '%s' created\n", $username);

        return (int) $pdo->lastInsertId();
    }
}
