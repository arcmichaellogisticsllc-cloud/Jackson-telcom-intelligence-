<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$args = $argv;
array_shift($args);
$dryRun = in_array('--dry-run', $args, true);
$confirm = in_array('--confirm', $args, true);

if (!$dryRun && !$confirm) {
    fwrite(STDERR, "Usage: php scripts/purge_demo_data.php --dry-run|--confirm\n");
    exit(1);
}

$db = Database::connection();

$purgeTables = [
    'password_reset_tokens',
    'connector_run_logs',
    'data_quality_issues',
    'data_review_items',
    'operator_pilot_feedback',
    'audit_logs',
    'onboarding_documents',
    'onboarding_reviews',
    'market_onboarding',
    'strategic_account_onboarding',
    'workforce_onboarding',
    'subcontractor_onboarding',
    'activities',
    'win_loss_intelligence',
    'competitor_forecasts',
    'competitive_pressure_indexes',
    'competitor_movements',
    'workforce_forecasts',
    'workforce_influence_relationships',
    'workforce_movements',
    'rhythm_compliance_scores',
    'review_instances',
    'operating_rhythms',
    'package_actions',
    'executive_briefs',
    'package_timeline_events',
    'decision_packages',
    'influence_packages',
    'need_packages',
    'capacity_packages',
    'work_packages',
    'executive_packages',
    'competitor_profiles',
    'workforce_profiles',
    'communication_records',
    'strategic_recommendations',
    'regional_dominance_scores',
    'strategic_accounts',
    'ownership_assignments',
    'forecast_records',
    'network_relationships',
    'integration_statuses',
    'preconstruction_snapshots',
    'relationship_context_snapshots',
    'capacity_allocation_snapshots',
    'erp_readiness_profiles',
    'project_packages',
    'market_readiness_scores',
    'market_intelligence_profiles',
    'market_intelligence_sources',
    'acquisition_watchlists',
    'acquisition_scores',
    'acquisition_classifications',
    'influence_intelligence',
    'need_intelligence',
    'capacity_intelligence',
    'work_intelligence',
    'learning_insights',
    'lessons_learned',
    'regional_learning_profiles',
    'pursuit_performance_profiles',
    'demand_performance_profiles',
    'hunt_performance_profiles',
    'subcontractor_performance_profiles',
    'relationship_performance_profiles',
    'outcome_records',
    'outreach_outcomes',
    'outreach_scripts',
    'outreach_discovery_questions',
    'outreach_intelligence',
    'daily_actions',
    'regional_strategy_scorecards',
    'growth_blockers',
    'opportunity_decisions',
    'capacity_recruitment_recommendations',
    'content_decisions',
    'relationship_decisions',
    'scenario_plans',
    'preconstruction_risks',
    'margin_forecasts',
    'subcontractor_fit_plans',
    'capacity_consumption_plans',
    'bid_decisions',
    'preconstruction_profiles',
    'opportunity_watchlists',
    'opportunity_pursuit_decisions',
    'pursuit_scores',
    'strategic_alignment_profiles',
    'recommended_actions',
    'content_attributions',
    'distribution_plans',
    'content_drafts',
    'content_opportunities',
    'demand_signals',
    'channels',
    'relationship_actions',
    'relationship_risks',
    'relationship_wins',
    'influence_roles',
    'relationship_objectives',
    'relationship_creation_signals',
    'relationship_intelligence_profiles',
    'watchlist_items',
    'source_quality_profiles',
    'signal_quality_profiles',
    'signal_accumulation_profiles',
    'hunt_tasks',
    'hunt_targets',
    'playbook_steps',
    'acquisition_playbooks',
    'hunts',
    'subcontractor_network_scores',
    'subcontractor_documents',
    'subcontractor_compliance_profiles',
    'subcontractor_qualification_scorecards',
    'capacity_trust_scores',
    'capacity_equipment',
    'capacity_discipline_counts',
    'capacity_profiles',
    'regional_capacity_targets',
    'acquisition_targets',
    'raw_signal_items',
    'harvester_runs',
    'signal_sources',
    'outreach_sequences',
    'outreach_targets',
    'content_ideas',
    'keywords',
    'intelligence_records',
    'signals',
    'opportunities',
    'subcontractors',
    'contacts',
    'organizations',
];

$preservedTables = [
    'users',
    'regions',
    'capacity_targets',
    'operator_modes',
    'platform_health_checks',
    'connectors',
    'recommendation_tuning_rules',
    'erp_contract_validation_items',
    'real_hunt_import_records',
];

echo $dryRun ? "Demo data purge dry run\n" : "Demo data purge confirm mode\n";
echo "Preserved system/config tables: " . implode(', ', $preservedTables) . "\n\n";

$realHuntRows = tableExists($db, 'real_hunt_import_records')
    ? (int)$db->query("SELECT COUNT(*) FROM real_hunt_import_records WHERE import_source = 'real_hunt'")->fetchColumn()
    : 0;
if ($realHuntRows > 0) {
    echo "Production real_hunt imports detected: {$realHuntRows}. Confirmed demo purge is disabled to protect production records.\n\n";
}

$counts = [];
foreach ($purgeTables as $table) {
    if (!tableExists($db, $table)) {
        continue;
    }
    $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

foreach ($counts as $table => $count) {
    printf("%-45s %8d\n", $table, $count);
}

$total = array_sum($counts);
echo "\nTotal demo/business rows selected for purge: {$total}\n";

if ($dryRun) {
    echo "Dry run only. No records were deleted.\n";
    exit(0);
}

if ($realHuntRows > 0) {
    fwrite(STDERR, "FAIL real_hunt production imports exist. Refusing destructive demo purge. Restore from backup or start from a clean demo database before purging.\n");
    exit(1);
}

$backupPath = createBackup();
if (!$backupPath || !file_exists($backupPath)) {
    fwrite(STDERR, "FAIL backup was not created; aborting purge.\n");
    exit(1);
}
echo "Backup verified: {$backupPath}\n";

$db->exec('PRAGMA foreign_keys = OFF');
$db->beginTransaction();
foreach ($purgeTables as $table) {
    if (!tableExists($db, $table)) {
        continue;
    }
    $db->exec("DELETE FROM {$table}");
    $db->exec("DELETE FROM sqlite_sequence WHERE name = " . $db->quote($table));
}
$db->prepare('INSERT INTO audit_logs (user_name, role, action, record_type, outcome, details) VALUES ("System", "Admin", "demo_data_purged", "database", "Success", ?)')->execute([
    'Demo business data purged after backup: ' . $backupPath,
]);
$db->commit();
$db->exec('PRAGMA foreign_keys = ON');

$marker = __DIR__ . '/../storage/production_data_mode';
file_put_contents($marker, 'production-data-transition=' . date('c') . PHP_EOL);
echo "Production data marker written: {$marker}\n";

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/check_data_integrity.php'), $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "FAIL integrity check failed after purge. Restore from backup: {$backupPath}\n");
    exit($exitCode);
}

echo "PASS demo business data purged. System/reference configuration preserved.\n";

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function createBackup(): ?string
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/backup_database.php');
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    echo implode(PHP_EOL, $output) . PHP_EOL;
    if ($exitCode !== 0) {
        return null;
    }
    foreach ($output as $line) {
        if (preg_match('/PASS database backup created: (.+)$/', $line, $matches)) {
            return trim($matches[1]);
        }
    }
    return null;
}
