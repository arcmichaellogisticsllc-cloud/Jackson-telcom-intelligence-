<?php

require __DIR__ . '/../vendor_autoload.php';

$config = require __DIR__ . '/../config/app.php';
$database = $config['database'];
$backupDir = __DIR__ . '/../storage/backups';

if (!file_exists($database)) {
    fwrite(STDERR, "FAIL database not found: {$database}\n");
    exit(1);
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');
$backup = $backupDir . "/jackson_intelligence_{$timestamp}.sqlite";

if (!copy($database, $backup)) {
    fwrite(STDERR, "FAIL unable to create backup\n");
    exit(1);
}

echo "PASS database backup created: {$backup}\n";
