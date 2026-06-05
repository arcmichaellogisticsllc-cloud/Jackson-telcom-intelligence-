<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo 'Migrated ' . basename($file) . PHP_EOL;
}

$columns = [
    'subcontractors' => [
        'aerial_crew_count' => 'INTEGER DEFAULT 0',
        'underground_crew_count' => 'INTEGER DEFAULT 0',
        'fiber_splicing_crew_count' => 'INTEGER DEFAULT 0',
        'emergency_restoration_crew_count' => 'INTEGER DEFAULT 0',
        'traffic_control_crew_count' => 'INTEGER DEFAULT 0',
    ],
    'recommended_actions' => [
        'recommendation_type' => 'TEXT',
        'priority_score' => 'INTEGER DEFAULT 0',
        'trigger_detail' => 'TEXT',
        'why_it_matters' => 'TEXT',
    ],
    'signals' => [
        'confidence_score' => 'INTEGER DEFAULT 0',
        'impact_score' => 'INTEGER DEFAULT 0',
        'priority' => 'TEXT DEFAULT "Medium"',
        'owner' => 'TEXT DEFAULT "Unassigned"',
        'status' => 'TEXT DEFAULT "New"',
    ],
];

foreach ($columns as $table => $defs) {
    $existing = array_column($db->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
    foreach ($defs as $column => $definition) {
        if (!in_array($column, $existing, true)) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            echo "Added {$table}.{$column}" . PHP_EOL;
        }
    }
}
