<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Services\HarvesterService;

if (($argv[1] ?? '') === '' || ($argv[2] ?? '') === '') {
    echo "Usage: php scripts/import_csv_signals.php /path/to/file.csv <signal_source_id>" . PHP_EOL;
    exit(1);
}

$result = (new HarvesterService())->importCsv($argv[1], (int)$argv[2], 'CLI CSV');
echo "CSV import completed run_id={$result['run_id']} created={$result['created']}" . PHP_EOL;
