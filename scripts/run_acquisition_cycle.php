<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\RecommendationEngine;
use App\Services\AcquisitionCommandService;
use App\Services\CapacityGapService;
use App\Services\DecisionSupportService;
use App\Services\IntelligenceWarehouseService;
use App\Services\OutreachIntelligenceService;
use App\Services\OpportunityPursuitService;
use App\Services\PreconstructionIntelligenceService;

echo "Jackson Intelligence Platform acquisition cycle\n";
echo "SQLite mode: running database-writing jobs sequentially. Do not run these jobs in parallel.\n\n";

$steps = [
    'Harvest active signal sources' => __DIR__ . '/run_harvesters.php',
    'Process raw signals' => __DIR__ . '/process_raw_signals.php',
    'Build acquisition targets' => __DIR__ . '/build_acquisition_targets.php',
];

foreach ($steps as $label => $script) {
    echo "== {$label} ==\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $code);
    if ($code !== 0) {
        echo "FAIL {$label} exited with code {$code}\n";
        exit($code);
    }
    echo "PASS {$label}\n\n";
}

echo "== Rebuild Capacity Radar trust scores ==\n";
(new CapacityGapService())->recalculateTrustScores();
echo "PASS Capacity Radar trust scores rebuilt\n\n";

echo "== Rebuild recommendations and Decision Support ==\n";
RecommendationEngine::regenerate();
(new OpportunityPursuitService())->rebuild();
(new PreconstructionIntelligenceService())->rebuild();
(new IntelligenceWarehouseService())->rebuild();
(new AcquisitionCommandService())->rebuild();
(new DecisionSupportService())->rebuild();
echo "PASS Decision Support rebuilt\n\n";

echo "== Rebuild Outreach Intelligence ==\n";
(new OutreachIntelligenceService())->rebuild();
echo "PASS Outreach Intelligence rebuilt\n";

echo "\nAcquisition cycle complete.\n";
