<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

$type = $argv[1] ?? null;
$specJson = $argv[2] ?? null;
$outFile = $argv[3] ?? null;

if ($type === null || $specJson === null || $outFile === null) {
    fwrite(STDERR, "Usage: php prompt.php <multiselect|select> <spec_json> <output_file>\n");
    exit(1);
}

$spec = json_decode($specJson, true, 512, JSON_THROW_ON_ERROR);

$result = match ($type) {
    'multiselect' => multiselect(
        label: $spec['label'] ?? 'Select options',
        options: $spec['options'] ?? [],
        default: $spec['default'] ?? [],
        required: (bool) ($spec['required'] ?? false),
        hint: $spec['hint'] ?? 'Space to toggle, Enter to confirm',
    ),
    'select' => select(
        label: $spec['label'] ?? 'Select option',
        options: $spec['options'] ?? [],
        default: $spec['default'] ?? null,
        hint: $spec['hint'] ?? '',
    ),
    default => throw new InvalidArgumentException("Unknown prompt type: {$type}"),
};

if (is_array($result)) {
    file_put_contents($outFile, implode("\n", $result));
} else {
    file_put_contents($outFile, (string) $result);
}
