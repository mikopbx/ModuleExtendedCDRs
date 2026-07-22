<?php

declare(strict_types=1);

function assertDependencyPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
assertDependencyPolicy(is_array($composer), 'composer.json must decode');
assertDependencyPolicy(is_array($lock), 'composer.lock must decode');
assertDependencyPolicy(($composer['config']['platform']['php'] ?? null) === '7.4.6', 'PHP platform must remain 7.4.6');

$versions = [];
foreach (($lock['packages'] ?? []) as $package) {
    if (isset($package['name'], $package['version'])) {
        $versions[$package['name']] = ltrim((string) $package['version'], 'v');
    }
}

assertDependencyPolicy(isset($versions['phpoffice/phpspreadsheet']), 'PhpSpreadsheet must be locked');
assertDependencyPolicy(
    version_compare($versions['phpoffice/phpspreadsheet'], '1.30.5', '>='),
    'PhpSpreadsheet must be at least 1.30.5; locked ' . $versions['phpoffice/phpspreadsheet']
);
assertDependencyPolicy(isset($versions['setasign/fpdi']), 'FPDI must be locked');
assertDependencyPolicy(
    version_compare($versions['setasign/fpdi'], '2.6.7', '>='),
    'FPDI must be at least 2.6.7; locked ' . $versions['setasign/fpdi']
);

echo "DependencyPolicyTest: OK\n";
