<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$checks = [];

$add = function (string $level, string $label, int $count, string $detail = '') use (&$checks): void {
    $checks[] = compact('level', 'label', 'count', 'detail');
};

$count = function (string $sql) use ($db): int {
    return (int)$db->query($sql)->fetchColumn();
};

$tables = ['signals','signal_quality_profiles','acquisition_targets','capacity_profiles','capacity_trust_scores','subcontractors','subcontractor_compliance_profiles','relationship_intelligence_profiles','relationship_objectives','recommended_actions','daily_actions','outreach_intelligence','outreach_scripts','outreach_discovery_questions','outreach_outcomes','content_drafts','distribution_plans','channels','activities'];
foreach ($tables as $table) {
    $exists = (bool)$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn();
    $add($exists ? 'PASS' : 'FAIL', "table exists: {$table}", $exists ? 0 : 1);
}

$add('WARN', 'signals without quality profile', $count('SELECT COUNT(*) FROM signals s LEFT JOIN signal_quality_profiles sqp ON sqp.signal_id = s.id WHERE sqp.id IS NULL'));
$add('WARN', 'targets without region', $count('SELECT COUNT(*) FROM acquisition_targets WHERE region_id IS NULL'));
$add('WARN', 'targets without owner', $count("SELECT COUNT(*) FROM acquisition_targets WHERE owner IS NULL OR owner = '' OR owner = 'Unassigned'"));
$add('WARN', 'capacity profiles without trust score', $count('SELECT COUNT(*) FROM capacity_profiles cp LEFT JOIN capacity_trust_scores cts ON cts.capacity_profile_id = cp.id WHERE cts.id IS NULL'));
$add('WARN', 'subcontractors without compliance profile', $count('SELECT COUNT(*) FROM subcontractors s LEFT JOIN subcontractor_compliance_profiles scp ON scp.subcontractor_id = s.id WHERE scp.id IS NULL'));
$add('WARN', 'relationships without primary objective', $count("SELECT COUNT(*) FROM relationship_intelligence_profiles rip WHERE NOT EXISTS (SELECT 1 FROM relationship_objectives ro WHERE ro.relationship_profile_id = rip.id AND ro.priority = 'Primary' AND ro.status != 'Not Relevant')"));
$add('FAIL', 'recommendations without category', $count("SELECT COUNT(*) FROM recommended_actions WHERE category IS NULL OR category = ''"));
$add('WARN', 'recommendations without source module', $count("SELECT COUNT(*) FROM recommended_actions WHERE source_module IS NULL OR source_module = ''"));
$add('FAIL', 'daily actions without owner', $count("SELECT COUNT(*) FROM daily_actions WHERE owner IS NULL OR owner = ''"));
$add('FAIL', 'outreach records without owner', $count("SELECT COUNT(*) FROM outreach_intelligence WHERE owner IS NULL OR owner = ''"));
$add('WARN', 'outreach records without script', $count('SELECT COUNT(*) FROM outreach_intelligence oi LEFT JOIN outreach_scripts os ON os.outreach_intelligence_id = oi.id WHERE os.id IS NULL'));
$add('WARN', 'outreach scripts without human review flag', $count('SELECT COUNT(*) FROM outreach_scripts WHERE human_review_required != 1'));
$add('WARN', 'content drafts without review status', $count("SELECT COUNT(*) FROM content_drafts WHERE review_status IS NULL OR review_status = ''"));
$add('FAIL', 'distribution plans without channel', $count('SELECT COUNT(*) FROM distribution_plans WHERE channel_id IS NULL OR channel_id NOT IN (SELECT id FROM channels)'));

$activityMap = [
    'signal' => 'signals',
    'organization' => 'organizations',
    'contact' => 'contacts',
    'subcontractor' => 'subcontractors',
    'opportunity' => 'opportunities',
    'recommended_action' => 'recommended_actions',
    'daily_action' => 'daily_actions',
    'outreach_intelligence' => 'outreach_intelligence',
    'acquisition_target' => 'acquisition_targets',
    'hunt_task' => 'hunt_tasks',
    'relationship_action' => 'relationship_actions',
    'content_draft' => 'content_drafts',
    'distribution_plan' => 'distribution_plans',
];
$orphaned = 0;
foreach ($activityMap as $entityType => $table) {
    if (!$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn()) {
        continue;
    }
    $orphaned += $count("SELECT COUNT(*) FROM activities WHERE entity_type = " . $db->quote($entityType) . " AND entity_id NOT IN (SELECT id FROM {$table})");
}
$add('WARN', 'orphaned activity records', $orphaned);

$failures = 0;
$warnings = 0;
foreach ($checks as $check) {
    $active = $check['count'] > 0;
    $level = $active ? $check['level'] : 'PASS';
    if ($level === 'FAIL') {
        $failures++;
    } elseif ($level === 'WARN') {
        $warnings++;
    }
    echo "{$level} {$check['label']}";
    if ($active) {
        echo " ({$check['count']})";
    }
    if ($check['detail']) {
        echo " - {$check['detail']}";
    }
    echo PHP_EOL;
}

echo "\nSummary: " . ($failures ? 'FAIL' : ($warnings ? 'WARN' : 'PASS')) . " ({$failures} fail, {$warnings} warn)\n";
exit($failures ? 1 : 0);
