<?php

require __DIR__ . '/../vendor_autoload.php';

echo "Jackson Intelligence Platform V1 release check\n";
echo "Runs database-writing jobs sequentially for SQLite reliability.\n\n";

$steps = [
    'Migrate schema' => [PHP_BINARY, __DIR__ . '/migrate.php'],
    'Seed data' => [PHP_BINARY, __DIR__ . '/seed.php'],
    'Run acquisition cycle' => [PHP_BINARY, __DIR__ . '/run_acquisition_cycle.php'],
    'Check data integrity' => [PHP_BINARY, __DIR__ . '/check_data_integrity.php'],
    'Smoke routes' => [PHP_BINARY, __DIR__ . '/smoke_routes.php'],
    'Backup database' => [PHP_BINARY, __DIR__ . '/backup_database.php'],
    'Verify backup restore' => [PHP_BINARY, __DIR__ . '/verify_backup_restore.php'],
    'Export operating data' => [PHP_BINARY, __DIR__ . '/export_operating_data.php'],
    'Check production launch gates' => [PHP_BINARY, __DIR__ . '/check_production_launch.php'],
];

foreach ($steps as $label => $command) {
    echo "== {$label} ==\n";
    $cmd = implode(' ', array_map('escapeshellarg', $command));
    passthru($cmd, $code);
    if ($code !== 0) {
        echo "FAIL {$label} exited with code {$code}\n";
        exit($code);
    }
    echo "PASS {$label}\n\n";
}

echo "== PHP lint ==\n";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
$failed = 0;
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        $failed++;
    }
}

if ($failed) {
    echo "FAIL PHP lint found {$failed} file(s) with errors\n";
    exit(1);
}

echo "PASS PHP lint\n\n";
echo "V1 release check complete.\n";
