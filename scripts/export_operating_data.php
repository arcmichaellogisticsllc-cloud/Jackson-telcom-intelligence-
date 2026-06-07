<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$exportDir = __DIR__ . '/../storage/exports';
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0755, true);
}

$timestamp = date('Ymd_His');
$file = $exportDir . "/operating_export_{$timestamp}.json";
$tables = [
    'regions',
    'recommended_actions',
    'daily_actions',
    'growth_blockers',
    'work_intelligence',
    'capacity_intelligence',
    'need_intelligence',
    'influence_intelligence',
    'market_intelligence_sources',
    'market_intelligence_profiles',
    'project_packages',
    'erp_readiness_profiles',
    'platform_health_checks',
];

$payload = [
    'exported_at' => date('c'),
    'purpose' => 'V1 operating export for review, backup, and release validation. Not a SyncERP export.',
    'tables' => [],
];

foreach ($tables as $table) {
    $exists = (bool)$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn();
    if (!$exists) {
        $payload['tables'][$table] = [];
        continue;
    }
    $payload['tables'][$table] = $db->query("SELECT * FROM {$table}")->fetchAll();
}

file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "PASS operating data exported: {$file}\n";
