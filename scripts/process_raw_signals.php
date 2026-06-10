<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Services\SignalProcessingService;

$limit = isset($argv[1]) ? (int)$argv[1] : null;
$result = (new SignalProcessingService())->processNew($limit);

echo "Source item processing\n";
echo "Seen: {$result['seen']}" . PHP_EOL;
echo "Converted: {$result['processed']}" . PHP_EOL;
echo "Duplicates: {$result['duplicates']}" . PHP_EOL;
