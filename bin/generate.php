<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MarekSkopal\Typo3Install\Generator\ComposerJsonGenerator;
use MarekSkopal\Typo3Install\Generator\DatabaseSetupGenerator;
use MarekSkopal\Typo3Install\Generator\DockerGenerator;
use MarekSkopal\Typo3Install\Generator\FrontendBuildGenerator;
use MarekSkopal\Typo3Install\Generator\MsWebGenerator;
use MarekSkopal\Typo3Install\Generator\ProjectFilesGenerator;
use MarekSkopal\Typo3Install\Generator\SettingsGenerator;
use MarekSkopal\Typo3Install\Generator\SiteConfigGenerator;

$generatorMap = [
    'ComposerJson' => ComposerJsonGenerator::class,
    'MsWeb' => MsWebGenerator::class,
    'SiteConfig' => SiteConfigGenerator::class,
    'Settings' => SettingsGenerator::class,
    'Docker' => DockerGenerator::class,
    'FrontendBuild' => FrontendBuildGenerator::class,
    'ProjectFiles' => ProjectFilesGenerator::class,
    'DatabaseSetup' => DatabaseSetupGenerator::class,
];

$generatorName = $argv[1] ?? null;
$configJson = $argv[2] ?? null;
$targetDir = $argv[3] ?? null;

if ($generatorName === null || $configJson === null || $targetDir === null) {
    echo "Usage: php bin/generate.php <GeneratorName> <config_json> <target_dir>\n";
    echo "Available generators: " . implode(', ', array_keys($generatorMap)) . "\n";
    exit(1);
}

if (!isset($generatorMap[$generatorName])) {
    echo "Unknown generator: {$generatorName}\n";
    echo "Available generators: " . implode(', ', array_keys($generatorMap)) . "\n";
    exit(1);
}

$config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
$generatorClass = $generatorMap[$generatorName];
$generator = new $generatorClass();
$generator->generate($config, $targetDir);
