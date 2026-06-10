<?php

require __DIR__ . '/../vendor_autoload.php';

$backup = $argv[1] ?? '';
if ($backup === '') {
    $backups = glob(__DIR__ . '/../storage/backups/*.sqlite') ?: [];
    rsort($backups);
    $backup = $backups[0] ?? '';
}

if ($backup === '' || !is_file($backup)) {
    fwrite(STDERR, "FAIL usage: php scripts/verify_backup_restore.php [backup.sqlite]\n");
    exit(1);
}

$tmpDir = __DIR__ . '/../storage/restore-tests';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}
$copy = $tmpDir . '/restore_test_' . date('Ymd_His') . '.sqlite';
if (!copy($backup, $copy)) {
    fwrite(STDERR, "FAIL unable to copy backup for restore test\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $copy);
$integrity = (string)$pdo->query('PRAGMA integrity_check')->fetchColumn();
$tableCount = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
$pdo = null;

if ($integrity !== 'ok' || $tableCount < 1) {
    fwrite(STDERR, "FAIL backup restore verification failed: integrity={$integrity}, tables={$tableCount}\n");
    exit(1);
}

$marker = __DIR__ . '/../storage/restore-tests/last_restore_verification';
file_put_contents($marker, json_encode([
    'verified_at' => date('c'),
    'backup' => realpath($backup),
    'test_copy' => realpath($copy),
    'table_count' => $tableCount,
], JSON_PRETTY_PRINT) . PHP_EOL);

echo "PASS backup restore verified: {$backup}\n";
echo "PASS restore test copy: {$copy}\n";
