<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Services\HarvesterService;

$sourceId = isset($argv[1]) ? (int)$argv[1] : null;
$results = (new HarvesterService())->runActive($sourceId, 'CLI');

echo "Acquisition harvester run\n";
echo "Sources run: " . count($results) . PHP_EOL;
foreach ($results as $result) {
    echo "#{$result['run_id']} {$result['source']} {$result['status']} found={$result['found']} created={$result['created']} errors={$result['errors']}" . PHP_EOL;
}
