<?php

require __DIR__ . '/../vendor_autoload.php';

$config = require __DIR__ . '/../config/app.php';
$database = $config['database'];
$backup = $argv[1] ?? '';
$confirm = $argv[2] ?? '';

if ($backup === '' || !is_file($backup)) {
    fwrite(STDERR, "FAIL usage: php scripts/restore_database.php /path/to/backup.sqlite CONFIRM_RESTORE\n");
    exit(1);
}

if ($confirm !== 'CONFIRM_RESTORE') {
    fwrite(STDERR, "FAIL restore requires explicit CONFIRM_RESTORE argument\n");
    exit(1);
}

$preRestore = __DIR__ . '/../storage/backups/pre_restore_' . date('Ymd_His') . '.sqlite';
if (!is_dir(dirname($preRestore))) {
    mkdir(dirname($preRestore), 0755, true);
}
if (file_exists($database) && !copy($database, $preRestore)) {
    fwrite(STDERR, "FAIL unable to create pre-restore safety backup\n");
    exit(1);
}

if (!copy($backup, $database)) {
    fwrite(STDERR, "FAIL unable to restore database from {$backup}\n");
    exit(1);
}

echo "PASS database restored from: {$backup}\n";
echo "PASS pre-restore safety backup: {$preRestore}\n";
