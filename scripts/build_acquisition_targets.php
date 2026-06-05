<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Services\AcquisitionTargetService;

$result = (new AcquisitionTargetService())->buildFromSignals(isset($argv[1]) ? (int)$argv[1] : null);

echo "Acquisition target builder\n";
echo "Signals scanned: {$result['signals_scanned']}" . PHP_EOL;
echo "Targets created: {$result['targets_created']}" . PHP_EOL;
echo "Duplicates skipped: {$result['duplicates_skipped']}" . PHP_EOL;
echo "Recommendations created: {$result['recommendations_created']}" . PHP_EOL;
