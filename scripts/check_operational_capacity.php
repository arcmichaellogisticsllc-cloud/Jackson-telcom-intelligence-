<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\OnboardingService;
use App\Services\OperatingWorkflowService;

session_start();
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Admin',
    'email' => 'admin@jacksontelcom.com',
    'role' => 'Admin',
    'region_id' => null,
];

$db = Database::connection();
$service = new OnboardingService();
$workflow = new OperatingWorkflowService();
$checks = [];
$failures = 0;

$check = function (string $label, bool $ok, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = ($ok ? 'PASS ' : 'FAIL ') . $label . ($detail !== '' ? ' - ' . $detail : '');
    if (!$ok) {
        $failures++;
    }
};

$db->beginTransaction();
try {
    $regionId = (int)$db->query("SELECT id FROM regions WHERE name = 'Southeast' LIMIT 1")->fetchColumn();
    $check('Southeast region exists', $regionId > 0);

    $onboardingId = $service->createGroundCrewOnboarding([
        'company_name' => 'Operational Capacity Check Ground Crew',
        'region_id' => $regionId,
        'state' => 'GA',
        'market' => 'Atlanta',
        'assigned_owner' => 'Capacity Readiness',
        'primary_contact' => 'Operations Contact',
        'phone' => '555-0100',
        'email' => 'ops@example.invalid',
        'service_underground' => '1',
        'service_directional_boring' => '1',
        'crew_count' => '2',
        'available_crew_count' => '2',
        'equipment_notes' => 'Directional drill, mini excavator, trailers',
        'notes' => 'Rollback-only operational capacity workflow check.',
    ]);
    $check('ground crew onboarding creates record', $onboardingId > 0, (string)$onboardingId);

    $link = $service->createSubcontractorIntakeLink($onboardingId, 7);
    $token = '';
    if ($link) {
        parse_str((string)parse_url($link, PHP_URL_QUERY), $query);
        $token = (string)($query['token'] ?? '');
    }
    $check('intake link generated with token', $token !== '');

    $submitted = $service->submitSubcontractorIntake([
        'token' => $token,
        'company_name' => 'Operational Capacity Check Ground Crew LLC',
        'legal_name' => 'Operational Capacity Check Ground Crew LLC',
        'years_in_business' => '6',
        'website' => 'https://example.invalid',
        'phone' => '555-0100',
        'email' => 'ops@example.invalid',
        'owner_name' => 'Capacity Owner',
        'primary_contact' => 'Operations Contact',
        'contact_title' => 'Owner',
        'states_served' => 'GA',
        'markets_served' => 'Atlanta',
        'availability' => 'Available Now',
        'service_underground' => '1',
        'service_directional_boring' => '1',
        'crew_count' => '2',
        'available_crew_count' => '2',
        'underground_crew_count' => '2',
        'directional_boring_crew_count' => '1',
        'directional_drills' => '1',
        'vac_trucks' => '1',
        'equipment_notes' => 'Directional drill, vac truck, mini excavator, trailers.',
        'doc_w9' => 'Submitted',
        'doc_coi' => 'Submitted',
        'doc_msa' => 'Submitted',
        'doc_nda' => 'Submitted',
        'doc_safety_program' => 'Submitted',
        'notes' => 'All document statuses are self-reported for rollback test.',
    ]);
    $check('subcontractor can submit intake', $submitted);

    $row = $db->query('SELECT * FROM subcontractor_onboarding WHERE id = ' . (int)$onboardingId)->fetch();
    $check('intake moves record to compliance review', ($row['onboarding_status'] ?? '') === 'Compliance Review', (string)($row['onboarding_status'] ?? ''));
    $check('readiness score increases after intake', (int)($row['onboarding_score'] ?? 0) >= 70, (string)($row['onboarding_score'] ?? 0));

    foreach (['W9','COI','MSA','NDA','Safety Program'] as $documentType) {
        $result = $service->saveDocument([
            'onboarding_type' => 'Subcontractor',
            'onboarding_id' => $onboardingId,
            'document_type' => $documentType,
            'file_name' => 'verified-' . strtolower(str_replace(' ', '-', $documentType)) . '.pdf',
            'status' => 'Approved',
            'source_reference' => 'Rollback-only document verification.',
            'notes' => 'Approved for operational workflow check only.',
        ]);
        $check("{$documentType} can be approved", (bool)($result['ok'] ?? false), $result['message'] ?? '');
    }

    foreach (['Compliance Review','Capacity Review'] as $reviewType) {
        $result = $service->saveReview([
            'onboarding_type' => 'Subcontractor',
            'onboarding_id' => $onboardingId,
            'review_type' => $reviewType,
            'status' => 'Approved',
            'review_notes' => 'Approved for rollback-only operational workflow check.',
        ]);
        $check("{$reviewType} can be approved", (bool)($result['ok'] ?? false), $result['message'] ?? '');
    }

    $stageResult = $service->updateStage('Subcontractor', $onboardingId, 'Approved', 'Approved for rollback-only workflow check.');
    $check('subcontractor approval gate can clear', (bool)($stageResult['ok'] ?? false), $stageResult['message'] ?? '');

    $subcontractorId = (int)$db->query('SELECT subcontractor_id FROM subcontractor_onboarding WHERE id = ' . (int)$onboardingId)->fetchColumn();
    $capacityProfile = $db->query('SELECT * FROM capacity_profiles WHERE subcontractor_id = ' . $subcontractorId)->fetch();
    $check('approved subcontractor creates capacity profile', is_array($capacityProfile), (string)$subcontractorId);
    $check('capacity profile is approved', ($capacityProfile['status'] ?? '') === 'Approved', (string)($capacityProfile['status'] ?? ''));

    $organizationId = (int)$db->query('SELECT organization_id FROM subcontractors WHERE id = ' . $subcontractorId)->fetchColumn();
    $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, opportunity_type, estimated_value, stage, next_action, owner, relationship_score, capacity_score, strategic_alignment_score, notes) VALUES (?, ?, ?, "Atlanta", "Underground Fiber Backbone", 1000000, "Intelligence", "Review matching ground crew capacity before pursuit.", "Company", 65, 20, 80, "Rollback-only operational capacity check.")')
        ->execute(['Operational Capacity Check Underground Fiber Work', $organizationId, $regionId]);

    $queues = $workflow->commandCenterQueues([]);
    $revenueItems = $queues['mission']['revenue']['items'] ?? [];
    $match = null;
    foreach ($revenueItems as $item) {
        if (($item['title'] ?? '') === 'Operational Capacity Check Underground Fiber Work') {
            $match = $item;
            break;
        }
    }
    $check('revenue lane sees temporary opportunity', is_array($match));
    $check('revenue lane uses matching capacity instead of blind recruit', is_array($match) && in_array('Capacity candidate review', $match['blockers'] ?? [], true), is_array($match) ? implode(', ', $match['blockers'] ?? []) : 'not found');
    $check('revenue lane has useful next action', is_array($match) && str_contains((string)($match['next_action'] ?? ''), 'matching capacity candidate'));

    $dailyActionCount = (int)$db->query("SELECT COUNT(*) FROM daily_actions WHERE linked_record_type = 'subcontractor_onboarding' AND linked_record_id = " . (int)$onboardingId . " AND status IN ('Open','In Progress')")->fetchColumn();
    $check('onboarding produces one active daily action', $dailyActionCount === 1, (string)$dailyActionCount);

    $activityCount = (int)$db->query("SELECT COUNT(*) FROM activities WHERE entity_type = 'subcontractor_onboarding' AND entity_id = " . (int)$onboardingId)->fetchColumn();
    $check('onboarding writes timeline/activity entries', $activityCount >= 4, (string)$activityCount);
} catch (Throwable $e) {
    $failures++;
    $checks[] = 'FAIL operational capacity check threw exception - ' . $e->getMessage();
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

foreach ($checks as $line) {
    echo $line . PHP_EOL;
}
echo "\nOperational capacity workflow summary: " . ($failures ? 'FAIL' : 'PASS') . "\n";
exit($failures ? 1 : 0);
