<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\OwnerModelService;

$db = Database::connection();
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo 'Migrated ' . basename($file) . PHP_EOL;
}

(new OwnerModelService())->ensureBaseline($db);
echo "Seeded ownership responsibility model baseline\n";

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
        'company_name' => 'TEXT',
        'legal_name' => 'TEXT',
        'years_in_business' => 'INTEGER DEFAULT 0',
        'website' => 'TEXT',
        'phone' => 'TEXT',
        'email' => 'TEXT',
        'owner_name' => 'TEXT',
        'primary_contact' => 'TEXT',
        'contact_title' => 'TEXT',
        'states_served' => 'TEXT',
        'available_crew_count' => 'INTEGER DEFAULT 0',
        'aerial_crew_count' => 'INTEGER DEFAULT 0',
        'underground_crew_count' => 'INTEGER DEFAULT 0',
        'fiber_splicing_crew_count' => 'INTEGER DEFAULT 0',
        'directional_boring_crew_count' => 'INTEGER DEFAULT 0',
        'emergency_restoration_crew_count' => 'INTEGER DEFAULT 0',
        'traffic_control_crew_count' => 'INTEGER DEFAULT 0',
        'mowing_row_crew_count' => 'INTEGER DEFAULT 0',
        'inspection_crew_count' => 'INTEGER DEFAULT 0',
        'qc_crew_count' => 'INTEGER DEFAULT 0',
        'engineering_crew_count' => 'INTEGER DEFAULT 0',
        'make_ready_crew_count' => 'INTEGER DEFAULT 0',
        'drop_crew_count' => 'INTEGER DEFAULT 0',
        'digger_derricks' => 'INTEGER DEFAULT 0',
        'fusion_splicers' => 'INTEGER DEFAULT 0',
        'reel_trailers' => 'INTEGER DEFAULT 0',
        'vac_trucks' => 'INTEGER DEFAULT 0',
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'recommended_actions' => [
        'source_module' => 'TEXT',
        'recommendation_type' => 'TEXT',
        'priority_score' => 'INTEGER DEFAULT 0',
        'trigger_detail' => 'TEXT',
        'why_it_matters' => 'TEXT',
        'usefulness_score' => 'INTEGER DEFAULT 0',
        'not_useful_count' => 'INTEGER DEFAULT 0',
        'suppressed_at' => 'TEXT',
        'suppression_reason' => 'TEXT',
        'recommended_primary_owner' => 'TEXT',
        'recommended_secondary_owner' => 'TEXT',
        'ownership_reason' => 'TEXT',
        'shared_required' => 'INTEGER DEFAULT 0',
    ],
    'outreach_intelligence' => [
        'due_date' => 'TEXT',
    ],
    'connectors' => [
        'region_id' => 'INTEGER',
    ],
    'connector_run_logs' => [
        'region_id' => 'INTEGER',
    ],
    'onboarding_documents' => [
        'source_reference' => 'TEXT',
        'storage_path' => 'TEXT',
        'file_size' => 'INTEGER DEFAULT 0',
        'mime_type' => 'TEXT',
        'uploaded_by' => 'TEXT',
    ],
    'users' => [
        'must_change_password' => 'INTEGER DEFAULT 0',
        'password_changed_at' => 'TEXT',
    ],
    'opportunities' => [
        'opportunity_type' => 'TEXT',
        'customer_type' => 'TEXT',
        'funding_source' => 'TEXT',
        'strategic_alignment_score' => 'INTEGER DEFAULT 0',
        'relationship_score' => 'INTEGER DEFAULT 0',
        'capacity_score' => 'INTEGER DEFAULT 0',
        'demand_score' => 'INTEGER DEFAULT 0',
        'risk_score' => 'INTEGER DEFAULT 0',
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'preconstruction_profiles' => [
        'estimated_start_date' => 'TEXT',
        'estimated_duration_days' => 'INTEGER DEFAULT 0',
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
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
    'strategic_accounts' => [
        'market' => 'TEXT',
        'owner' => 'TEXT',
        'relationship_health_score' => 'INTEGER DEFAULT 0',
        'opportunity_score' => 'INTEGER DEFAULT 0',
        'account_status' => 'TEXT DEFAULT "Active"',
        'recent_signal_count' => 'INTEGER DEFAULT 0',
        'recommended_action' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'executive_packages' => [
        'source_module' => 'TEXT',
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
        'perspective_scope' => 'TEXT DEFAULT "Company Priority"',
    ],
    'contacts' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'organizations' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'capacity_profiles' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'workforce_profiles' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'opportunity_pursuit_decisions' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'project_packages' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'subcontractor_onboarding' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'workforce_onboarding' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'strategic_account_onboarding' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'market_onboarding' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
    ],
    'daily_actions' => [
        'primary_owner' => 'TEXT',
        'secondary_owner' => 'TEXT',
        'shared_owner_flag' => 'INTEGER DEFAULT 0',
        'ownership_notes' => 'TEXT',
        'action_scope' => 'TEXT DEFAULT "Company Action"',
        'generated_by' => 'TEXT DEFAULT "system"',
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

$userSchema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
if ($userSchema && !str_contains($userSchema, "'Executive'")) {
    $db->exec('PRAGMA foreign_keys = OFF');
    $db->beginTransaction();
    $db->exec('ALTER TABLE users RENAME TO users_old');
    $db->exec("CREATE TABLE users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL CHECK(role IN ('Admin','Executive','Mike','Ron','Regional Owner','Operator','Viewer','Southeast Owner','Great Lakes Owner','Southwest Owner')),
      region_id INTEGER NULL,
      must_change_password INTEGER DEFAULT 0,
      password_changed_at TEXT,
      FOREIGN KEY(region_id) REFERENCES regions(id)
    )");
    $oldColumns = array_column($db->query('PRAGMA table_info(users_old)')->fetchAll(), 'name');
    $mustChangeSelect = in_array('must_change_password', $oldColumns, true) ? 'must_change_password' : '0';
    $changedAtSelect = in_array('password_changed_at', $oldColumns, true) ? 'password_changed_at' : 'NULL';
    $db->exec('INSERT INTO users (id, name, email, password_hash, role, region_id, must_change_password, password_changed_at) SELECT id, name, email, password_hash, role, region_id, ' . $mustChangeSelect . ', ' . $changedAtSelect . ' FROM users_old');
    $db->exec('DROP TABLE users_old');
    $db->commit();
    $db->exec('PRAGMA foreign_keys = ON');
    echo "Rebuilt users table for operational role model\n";
}

$auditSchema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'audit_logs'")->fetchColumn();
if ($auditSchema && (str_contains($auditSchema, 'users_old') || str_contains($auditSchema, 'REFERENCES users'))) {
    $db->exec('DROP TABLE audit_logs');
    $db->exec("CREATE TABLE audit_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER,
      user_name TEXT,
      role TEXT,
      action TEXT NOT NULL,
      record_type TEXT,
      record_id INTEGER,
      ip_address TEXT,
      user_agent TEXT,
      outcome TEXT DEFAULT 'Success',
      details TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at)');
    echo "Rebuilt audit_logs table for operational audit model\n";
}

$resetSchema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'password_reset_tokens'")->fetchColumn();
if ($resetSchema && (str_contains($resetSchema, 'users_old') || str_contains($resetSchema, 'REFERENCES users'))) {
    $db->exec('DROP TABLE password_reset_tokens');
    $db->exec("CREATE TABLE password_reset_tokens (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      token_hash TEXT NOT NULL UNIQUE,
      expires_at TEXT NOT NULL,
      used_at TEXT,
      requested_ip TEXT,
      created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_hash ON password_reset_tokens(token_hash)');
    echo "Rebuilt password_reset_tokens table for operational reset model\n";
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

rebuildTableWithoutChecks($db, 'users', [
    "role TEXT NOT NULL CHECK(role IN ('Admin','Executive','Mike','Ron','Regional Owner','Operator','Viewer','Southeast Owner','Great Lakes Owner','Southwest Owner'))" => 'role TEXT NOT NULL',
]);
rebuildTableWithoutChecks($db, 'capacity_profiles', [
    "owner TEXT CHECK(owner IN ('Mike','Ron','Mike/Ron Shared','Future Southwest Owner','Admin'))" => 'owner TEXT',
]);
rebuildTableWithoutChecks($db, 'signals', [
    "owner TEXT DEFAULT 'Unassigned' CHECK(owner IN ('Admin','Mike','Ron','Unassigned'))" => "owner TEXT DEFAULT 'Unassigned'",
]);
rebuildTableWithoutChecks($db, 'data_review_items', [
    "review_type TEXT NOT NULL CHECK(review_type IN ('Raw Signal','Duplicate','Classification','Recommendation','Connector','Security','Data Quality','Other'))" => "review_type TEXT NOT NULL CHECK(review_type IN ('Raw Signal','Source Item','Duplicate','Classification','Recommendation','Connector','Security','Data Quality','Other'))",
]);
foreach (['capacity_discipline_counts', 'capacity_equipment', 'capacity_trust_scores', 'capacity_intelligence'] as $capacityChildTable) {
    rebuildTableWithoutChecks($db, $capacityChildTable, [
        'REFERENCES "capacity_profiles_owner_model_old"(id)' => 'REFERENCES capacity_profiles(id)',
    ]);
}
foreach ([
    'intelligence_records',
    'acquisition_targets',
    'signal_quality_profiles',
    'watchlist_items',
    'content_opportunities',
    'workforce_movements',
    'competitor_movements',
    'real_hunt_import_records',
    'intelligence_stream_import_records',
] as $signalChildTable) {
    rebuildTableWithoutChecks($db, $signalChildTable, [
        'REFERENCES "signals_owner_model_old"(id)' => 'REFERENCES signals(id)',
        'REFERENCES "signals_old"(id)' => 'REFERENCES signals(id)',
    ]);
}
foreach (['hunt_targets', 'hunt_tasks'] as $targetChildTable) {
    rebuildTableWithoutChecks($db, $targetChildTable, [
        'REFERENCES "acquisition_targets_owner_model_old"(id)' => 'REFERENCES acquisition_targets(id)',
        'REFERENCES "hunt_targets_owner_model_old"(id)' => 'REFERENCES hunt_targets(id)',
    ]);
}
foreach ([
    'content_drafts',
    'distribution_plans',
    'content_attributions',
    'content_decisions',
    'demand_performance_profiles',
] as $contentChildTable) {
    rebuildTableWithoutChecks($db, $contentChildTable, [
        'REFERENCES "content_opportunities_owner_model_old"(id)' => 'REFERENCES content_opportunities(id)',
    ]);
}

function rebuildTableWithoutChecks(PDO $db, string $table, array $replacements): void
{
    $schema = (string)$db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = '{$table}'")->fetchColumn();
    if ($schema === '') {
        return;
    }
    $newSchema = $schema;
    foreach ($replacements as $search => $replace) {
        $newSchema = str_replace($search, $replace, $newSchema);
    }
    if ($newSchema === $schema) {
        return;
    }

    $oldTable = $table . '_owner_model_old';
    $newSchema = preg_replace('/CREATE TABLE\s+' . preg_quote($table, '/') . '\s*/i', 'CREATE TABLE ' . $table . ' ', $newSchema, 1);
    $columns = array_column($db->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
    $columnList = implode(', ', array_map(fn($column) => '"' . str_replace('"', '""', $column) . '"', $columns));

    $db->exec('PRAGMA foreign_keys = OFF');
    $db->beginTransaction();
    $db->exec("DROP TABLE IF EXISTS {$oldTable}");
    $db->exec("ALTER TABLE {$table} RENAME TO {$oldTable}");
    $db->exec($newSchema);
    $db->exec("INSERT INTO {$table} ({$columnList}) SELECT {$columnList} FROM {$oldTable}");
    $db->exec("DROP TABLE {$oldTable}");
    $db->commit();
    $db->exec('PRAGMA foreign_keys = ON');
    echo "Rebuilt {$table} without hardcoded owner/role CHECK constraints\n";
}
