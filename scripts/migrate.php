<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo 'Migrated ' . basename($file) . PHP_EOL;
}

$columns = [
    'regions' => [
        'owner_name' => 'TEXT',
        'owner_email' => 'TEXT',
        'hub_city' => 'TEXT',
        'hub_state' => 'TEXT',
        'states_covered' => 'TEXT',
        'priority_tier' => 'TEXT DEFAULT "Tier 1"',
        'operating_status' => 'TEXT DEFAULT "Active"',
        'strategic_notes' => 'TEXT',
        'coverage_score' => 'INTEGER DEFAULT 0',
        'capacity_score' => 'INTEGER DEFAULT 0',
        'relationship_score' => 'INTEGER DEFAULT 0',
        'opportunity_score' => 'INTEGER DEFAULT 0',
        'traffic_score' => 'INTEGER DEFAULT 0',
    ],
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
        'city' => 'TEXT',
        'confidence_score' => 'INTEGER DEFAULT 0',
        'impact_score' => 'INTEGER DEFAULT 0',
        'priority' => 'TEXT DEFAULT "Medium"',
        'owner' => 'TEXT DEFAULT "Unassigned"',
        'status' => 'TEXT DEFAULT "New"',
        'recommended_next_action' => 'TEXT',
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

$signalSchema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'signals'")->fetchColumn();
if ($signalSchema && (!str_contains($signalSchema, "'Content'") || !str_contains($signalSchema, "'Google Search'") || !str_contains($signalSchema, "'Admin'"))) {
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->beginTransaction();
    $db->exec('ALTER TABLE signals RENAME TO signals_old');
    $db->exec("CREATE TABLE signals (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT,
      signal_type TEXT NOT NULL CHECK(signal_type IN ('Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach')),
      source_type TEXT NOT NULL CHECK(source_type IN ('Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other')),
      source_url TEXT,
      region_id INTEGER NOT NULL,
      state TEXT,
      city TEXT,
      organization_name TEXT,
      contact_name TEXT,
      confidence_score INTEGER DEFAULT 0,
      impact_score INTEGER DEFAULT 0,
      priority TEXT NOT NULL DEFAULT 'Medium' CHECK(priority IN ('Low','Medium','High','Critical')),
      owner TEXT DEFAULT 'Unassigned' CHECK(owner IN ('Admin','Mike','Ron','Unassigned')),
      status TEXT NOT NULL DEFAULT 'New' CHECK(status IN ('New','Reviewed','Assigned','Converted','Ignored')),
      recommended_next_action TEXT,
      notes TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(region_id) REFERENCES regions(id)
    )");
    $oldColumns = array_column($db->query('PRAGMA table_info(signals_old)')->fetchAll(), 'name');
    $citySelect = in_array('city', $oldColumns, true) ? 'city' : 'NULL';
    $actionSelect = in_array('recommended_next_action', $oldColumns, true) ? 'recommended_next_action' : 'NULL';
    $db->exec("INSERT INTO signals (id, title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes, created_at, updated_at) SELECT id, title, description, signal_type, source_type, source_url, region_id, state, {$citySelect}, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, {$actionSelect}, notes, created_at, updated_at FROM signals_old");
    $db->exec('DROP TABLE signals_old');
    $db->commit();
    $db->exec('PRAGMA foreign_keys = ON');
    echo "Rebuilt signals table for expanded signal types" . PHP_EOL;
}

$intelligenceSchema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'intelligence_records'")->fetchColumn();
if ($intelligenceSchema && str_contains($intelligenceSchema, 'signals_old')) {
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->beginTransaction();
    $db->exec('ALTER TABLE intelligence_records RENAME TO intelligence_records_old');
    $db->exec('CREATE TABLE intelligence_records (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      signal_id INTEGER,
      region_id INTEGER,
      title TEXT NOT NULL,
      summary TEXT,
      market TEXT,
      state TEXT,
      owner TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(signal_id) REFERENCES signals(id),
      FOREIGN KEY(region_id) REFERENCES regions(id)
    )');
    $db->exec('INSERT INTO intelligence_records (id, signal_id, region_id, title, summary, market, state, owner, created_at, updated_at) SELECT id, signal_id, region_id, title, summary, market, state, owner, created_at, updated_at FROM intelligence_records_old');
    $db->exec('DROP TABLE intelligence_records_old');
    $db->commit();
    $db->exec('PRAGMA foreign_keys = ON');
    echo "Rebuilt intelligence_records foreign key for signals" . PHP_EOL;
}
