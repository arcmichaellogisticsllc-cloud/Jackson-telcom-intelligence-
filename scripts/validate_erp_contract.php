<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$checks = [];
$add = function (string $level, string $label, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = [$level, $label, $ok, $detail];
};

$requiredTables = [
    'project_packages',
    'erp_readiness_profiles',
    'capacity_allocation_snapshots',
    'relationship_context_snapshots',
    'preconstruction_snapshots',
    'integration_statuses',
    'erp_contract_validation_items',
];

foreach ($requiredTables as $table) {
    $exists = (bool)$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn();
    $add('FAIL', "table exists: {$table}", $exists);
}

$packageColumns = array_column($db->query('PRAGMA table_info(project_packages)')->fetchAll(), 'name');
foreach (['opportunity_id','pursuit_decision_id','preconstruction_profile_id','package_name','customer_name','region_id','market','state','estimated_value','estimated_margin','package_status','package_owner'] as $column) {
    $add('FAIL', "project package field mapped: {$column}", in_array($column, $packageColumns, true));
}

$missingReadiness = (int)$db->query('SELECT COUNT(*) FROM project_packages pp LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.id IS NULL')->fetchColumn();
$missingCapacity = (int)$db->query('SELECT COUNT(*) FROM project_packages pp LEFT JOIN capacity_allocation_snapshots cas ON cas.project_package_id = pp.id WHERE cas.id IS NULL')->fetchColumn();
$missingRelationship = (int)$db->query('SELECT COUNT(*) FROM project_packages pp LEFT JOIN relationship_context_snapshots rcs ON rcs.project_package_id = pp.id WHERE rcs.id IS NULL')->fetchColumn();
$missingPreconstruction = (int)$db->query('SELECT COUNT(*) FROM project_packages pp LEFT JOIN preconstruction_snapshots ps ON ps.project_package_id = pp.id WHERE ps.id IS NULL')->fetchColumn();
$missingStatus = (int)$db->query('SELECT COUNT(*) FROM project_packages pp LEFT JOIN integration_statuses ist ON ist.project_package_id = pp.id WHERE ist.id IS NULL')->fetchColumn();

$add('FAIL', 'all packages have readiness profile', $missingReadiness === 0, (string)$missingReadiness);
$add('FAIL', 'all packages have capacity snapshot', $missingCapacity === 0, (string)$missingCapacity);
$add('FAIL', 'all packages have relationship snapshot', $missingRelationship === 0, (string)$missingRelationship);
$add('FAIL', 'all packages have preconstruction snapshot', $missingPreconstruction === 0, (string)$missingPreconstruction);
$add('FAIL', 'all packages have integration status', $missingStatus === 0, (string)$missingStatus);

$missingContractSource = (int)$db->query("SELECT COUNT(*) FROM erp_contract_validation_items WHERE required_for_handoff = 1 AND (source_record_type IS NULL OR source_record_type = '' OR source_field IS NULL OR source_field = '')")->fetchColumn();
$add('FAIL', 'required contract fields have source mapping', $missingContractSource === 0, (string)$missingContractSource);

foreach ($db->query("SELECT * FROM erp_contract_validation_items WHERE required_for_handoff = 1 AND source_record_type IS NOT NULL AND source_record_type != '' AND source_field IS NOT NULL AND source_field != ''")->fetchAll() as $item) {
    $table = (string)$item['source_record_type'];
    $field = (string)$item['source_field'];
    $tableExists = (bool)$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $db->quote($table))->fetchColumn();
    if (!$tableExists) {
        $add('FAIL', "contract source table exists: {$table}", false);
        continue;
    }
    $columns = array_column($db->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
    $add('FAIL', "contract source field exists: {$table}.{$field}", in_array($field, $columns, true));
}

$fails = 0;
$warns = 0;
foreach ($checks as [$level, $label, $ok, $detail]) {
    if ($ok) {
        echo "PASS {$label}\n";
        continue;
    }
    if ($level === 'FAIL') {
        $fails++;
    } else {
        $warns++;
    }
    echo "{$level} {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
}

echo "\nERP contract validation summary: " . ($fails ? 'FAIL' : ($warns ? 'WARN' : 'PASS')) . " ({$fails} fail, {$warns} warn)\n";
exit($fails ? 1 : 0);
