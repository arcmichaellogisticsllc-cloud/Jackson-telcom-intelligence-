<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\ProductionReadinessService;

session_start();
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Admin',
    'email' => 'admin@jacksontelcom.com',
    'role' => 'Admin',
    'region_id' => null,
];

$db = Database::connection();
$service = new ProductionReadinessService();
$checks = [];
$failures = 0;

$check = function (string $label, bool $ok, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = ($ok ? 'PASS ' : 'FAIL ') . $label . ($detail !== '' ? ' - ' . $detail : '');
    if (!$ok) {
        $failures++;
    }
};

$started = !$db->inTransaction();
if ($started) {
    $db->beginTransaction();
}

try {
    $regionId = (int)$db->query('SELECT id FROM regions WHERE name = "Southeast" LIMIT 1')->fetchColumn();
    if (!$regionId) {
        throw new RuntimeException('Southeast region missing. Run production seed baseline first.');
    }

    $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, status) VALUES ("Workflow Check Org", "Operational Check", ?, "GA", "Atlanta", "Active")')
        ->execute([$regionId]);
    $orgId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO contacts (first_name, last_name, title, organization_id, region_id, relationship_owner, relationship_strength) VALUES ("Workflow", "Check", "Project Manager", ?, ?, "Admin", "Developing")')
        ->execute([$orgId, $regionId]);
    $contactId = (int)$db->lastInsertId();

    $issueId = $service->createDataQualityIssue([
        'issue_type' => 'Missing Contact Info',
        'linked_record_type' => 'contact',
        'linked_record_id' => $contactId,
        'region_id' => $regionId,
        'title' => 'Workflow check contact correction',
        'description' => 'Temporary workflow check row.',
        'severity' => 'Medium',
        'assigned_owner' => 'Admin',
    ]);
    $service->updateDataQualityIssue($issueId, 'Resolved', 'Workflow check resolved.', "email=workflow-check@example.com\nphone=555-0100\nowner=Mike\nnotes=Operational workflow correction applied.");
    $contact = $db->query('SELECT email, phone, relationship_owner, notes FROM contacts WHERE id = ' . $contactId)->fetch();
    $check('data quality correction writeback', ($contact['email'] ?? '') === 'workflow-check@example.com' && ($contact['phone'] ?? '') === '555-0100' && ($contact['relationship_owner'] ?? '') === 'Mike');

    $db->prepare('INSERT INTO connectors (connector_name, source_type, run_mode, source_url, status, notes, region_id) VALUES ("Workflow Check Connector", "Industry News", "Manual", "https://broadbandusa.ntia.gov/", "Ready", "Georgia workflow check connector.", ?)')
        ->execute([$regionId]);
    $connectorId = (int)$db->lastInsertId();
    $service->runConnector($connectorId);
    $run = $db->query('SELECT id, region_id, review_status FROM connector_run_logs WHERE connector_id = ' . $connectorId . ' ORDER BY id DESC LIMIT 1')->fetch();
    $check('connector run creates region-scoped review log', $run && (int)$run['region_id'] === $regionId && $run['review_status'] === 'Needs Data Review');
    $review = $db->query('SELECT id FROM data_review_items WHERE linked_record_type = "connector_run_log" AND linked_record_id = ' . (int)$run['id'] . ' LIMIT 1')->fetch();
    $check('connector run creates data review item', (bool)$review);

    $db->prepare('INSERT INTO recommended_actions (title, category, priority, region_id, assigned_owner, reason, recommended_next_action, status, source_module, priority_score) VALUES ("Workflow check tuning promotion", "Capacity", "High", ?, "Admin", "Workflow check.", "Review action tuning.", "Open", "Workflow Check", 90)')
        ->execute([$regionId]);
    $recId = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES ("Workflow Check Tuning Rule", "Workflow Check", "Capacity", "Admin", ?, 80, 1, 1, 1, "Temporary workflow check.")')
        ->execute([$regionId]);
    $service->applyTuningRules();
    $promoted = (int)$db->query('SELECT COUNT(*) FROM daily_actions WHERE linked_record_type = "recommended_action" AND linked_record_id = ' . $recId . ' AND generated_by = "production_readiness"')->fetchColumn();
    $check('explicit action tuning promotes qualified recommendation', $promoted === 1);

    $db->prepare('INSERT INTO audit_logs (user_name, role, action, record_type, record_id, outcome, details) VALUES ("Workflow Admin", "Admin", "workflow_check_event", "contact", ?, "Success", "Operational workflow audit filter check.")')
        ->execute([$contactId]);
    $filtered = $service->dashboardData(['audit_action' => 'workflow_check_event'])['auditLogs'] ?? [];
    $check('audit filtering finds matching event', count($filtered) >= 1);

    if ($started) {
        $db->rollBack();
    }
} catch (Throwable $e) {
    if ($started && $db->inTransaction()) {
        $db->rollBack();
    }
    $check('operational workflow check exception', false, $e->getMessage());
}

foreach ($checks as $line) {
    echo $line . PHP_EOL;
}
echo "\nOperational workflow summary: " . ($failures ? 'FAIL' : 'PASS') . "\n";
exit($failures ? 1 : 0);
