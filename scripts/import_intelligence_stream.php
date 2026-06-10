<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\ContactResolutionService;
use App\Services\IntelligenceStreamService;
use App\Services\OrganizationResolutionService;
use App\Services\OwnerModelService;

const STREAM_KEYS = ['broadband_funding','strategic_account','engineering_firm','contractor_discovery','prime_contractor'];
const REQUIRED_COLUMNS = [
    'organization_name',
    'organization_type',
    'contact_name',
    'contact_title',
    'contact_email',
    'contact_phone',
    'contact_public_url',
    'role_type',
    'access_category',
    'signal_type',
    'purpose',
    'region',
    'state',
    'market',
    'website',
    'source_url',
    'source_type',
    'evidence_summary',
    'confidence_score',
    'review_status',
    'recommended_action',
];

if (($argv[1] ?? '') === '' || ($argv[2] ?? '') === '' || !in_array($argv[2], STREAM_KEYS, true)) {
    echo "Usage: php scripts/import_intelligence_stream.php <csv_path> <stream>\n";
    echo "Streams: " . implode(', ', STREAM_KEYS) . "\n";
    exit(1);
}

$path = $argv[1];
$streamKey = $argv[2];
$dryRun = in_array('--dry-run', $argv, true);
if (!is_file($path)) {
    echo "Import failed: file not found {$path}\n";
    exit(1);
}

$handle = fopen($path, 'r');
if ($handle === false) {
    echo "Import failed: unable to open {$path}\n";
    exit(1);
}

$header = fgetcsv($handle, null, ',', '"', '\\');
if ($header === false) {
    echo "Import failed: missing CSV header\n";
    exit(1);
}
$header = array_map(static fn($value) => trim((string)$value), $header);
$missing = array_values(array_diff(REQUIRED_COLUMNS, $header));
if ($missing) {
    echo "Import failed: missing required columns: " . implode(', ', $missing) . "\n";
    exit(1);
}

$db = Database::connection();
(new IntelligenceStreamService())->ensureBaseline($db);
$regions = loadRegions($db);
$streamId = (new IntelligenceStreamService())->streamIdForKey($db, $streamKey);
$sourceId = ensureSignalSource($db, $regions['National'] ?? null, $streamKey, $path);
$runId = createHarvesterRun($db, $sourceId, $path, $streamKey);
$orgResolver = new OrganizationResolutionService();
$contactResolver = new ContactResolutionService();

$counts = [
    'rows' => 0,
    'organizations_created' => 0,
    'organizations_matched' => 0,
    'contacts_created' => 0,
    'signals_created' => 0,
    'targets_created' => 0,
    'capacity_prospects_created' => 0,
    'opportunities_created' => 0,
    'actions_created' => 0,
    'review_needed' => 0,
    'skipped' => 0,
    'errors' => 0,
];
$rowNumber = 1;
$db->beginTransaction();

try {
    while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        $rowNumber++;
        if (count(array_filter($values, static fn($value) => trim((string)$value) !== '')) === 0) {
            continue;
        }
        $counts['rows']++;
        $row = array_combine($header, array_pad($values, count($header), ''));
        $row = normalizeRow(array_map(static fn($value) => trim((string)$value), $row), $streamKey);
        $regionId = regionId($regions, $row['region']);
        $rawId = createRawSignal($db, $runId, $sourceId, $streamKey, $row, $rowNumber);

        [$organizationId, $orgCreated, $orgNeedsReview, $orgMessage] = $orgResolver->resolveOrCreate($db, $row, $regionId);
        if (!$organizationId) {
            createDataQualityIssue($db, 'Missing Region', 'stream_import_record', null, $regionId, 'Organization resolution required: ' . streamTitle($streamKey), $orgMessage, ownerForRegion($row['region']));
            recordImport($db, $streamKey, $path, $rowNumber, $row, $rawId, null, null, null, null, null, null, null, 'Needs Review');
            $counts['review_needed']++;
            continue;
        }
        $orgCreated ? $counts['organizations_created']++ : $counts['organizations_matched']++;

        $signalId = createSignal($db, $streamKey, $row, $organizationId, $regionId);
        $counts['signals_created']++;
        createEvidence($db, 'organization', $organizationId, $streamId, $row);
        createEvidence($db, 'signal', $signalId, $streamId, $row);
        applyOrganizationClassifications($db, $organizationId, $streamKey, $row);

        [$contactId, $contactCreated, $contactNeedsReview] = $contactResolver->resolveOrCreate($db, $row, $organizationId, $regionId);
        if ($contactId) {
            $contactCreated && $counts['contacts_created']++;
            createContactRoleAccess($db, $contactId, $organizationId, $row);
            createRelationshipProfile($db, $contactId, $organizationId, $regionId, $row);
            createEvidence($db, 'contact', $contactId, $streamId, $row);
        } elseif ($row['contact_name'] || $row['contact_email'] || $row['contact_public_url']) {
            createDataQualityIssue($db, 'Conflicting Data', 'organization', $organizationId, $regionId, 'Contact review required: ' . $row['organization_name'], 'Contact was present but could not be resolved confidently.', ownerForRegion($row['region']));
            $contactNeedsReview = true;
        }

        $opportunityId = null;
        $subcontractorId = null;
        $capacityProfileId = null;
        if (in_array($streamKey, ['broadband_funding', 'strategic_account', 'prime_contractor'], true)) {
            $opportunityId = createOpportunityWatch($db, $organizationId, $regionId, $streamKey, $row);
            if ($opportunityId) {
                $counts['opportunities_created']++;
                createEvidence($db, 'opportunity', $opportunityId, $streamId, $row);
            }
        }
        if ($streamKey === 'contractor_discovery') {
            [$subcontractorId, $capacityProfileId] = createCapacityProspect($db, $organizationId, $regionId, $row);
            if ($subcontractorId || $capacityProfileId) {
                $counts['capacity_prospects_created']++;
            }
        }
        if ($streamKey === 'prime_contractor') {
            createCompetitorProfile($db, $organizationId, $regionId, $row);
        }
        if ($streamKey === 'strategic_account') {
            createStrategicAccountProfile($db, $organizationId, $regionId, $row);
        }

        $targetId = createAcquisitionTarget($db, $organizationId, $contactId, $signalId, $streamKey, $regionId, $row);
        $targetId && $counts['targets_created']++;
        $actionId = createRecommendedAction($db, $organizationId, $contactId, $opportunityId, $subcontractorId, $streamKey, $regionId, $row);
        $actionId && $counts['actions_created']++;

        $needsReview = $orgNeedsReview || $contactNeedsReview || $row['review_status'] !== 'Verified' || (int)$row['confidence_score'] < 75 || $row['source_url'] === '';
        if ($needsReview) {
            createDataQualityIssue($db, dataQualityType($row), 'organization', $organizationId, $regionId, 'Review stream evidence: ' . $row['organization_name'], 'Review source evidence, confidence, contact role, and organization classification before treating as trusted.', ownerForRegion($row['region']));
            $counts['review_needed']++;
        }

        recordImport($db, $streamKey, $path, $rowNumber, $row, $rawId, $organizationId, $contactId, $signalId, $opportunityId, $subcontractorId, $capacityProfileId, $actionId, $needsReview ? 'Needs Review' : 'Imported');
    }

    finishHarvesterRun($db, $runId, 'Completed', $counts);
    if ($dryRun) {
        $db->rollBack();
    } else {
        $db->commit();
    }
} catch (Throwable $e) {
    $db->rollBack();
    finishHarvesterRun($db, $runId, 'Failed', ['rows' => $counts['rows'], 'errors' => 1], $e->getMessage());
    echo "Import failed on row {$rowNumber}: {$e->getMessage()}\n";
    exit(1);
}

fclose($handle);
echo ($dryRun ? 'DRY RUN ' : '') . "Intelligence stream import completed stream={$streamKey} rows={$counts['rows']} organizations_created={$counts['organizations_created']} organizations_matched={$counts['organizations_matched']} contacts_created={$counts['contacts_created']} signals_created={$counts['signals_created']} targets_created={$counts['targets_created']} capacity_prospects_created={$counts['capacity_prospects_created']} opportunities_created={$counts['opportunities_created']} actions_created={$counts['actions_created']} review_needed={$counts['review_needed']} skipped={$counts['skipped']} errors={$counts['errors']}\n";

function loadRegions(PDO $db): array
{
    $regions = [];
    foreach ($db->query('SELECT id, name FROM regions') as $row) {
        $regions[$row['name']] = (int)$row['id'];
    }
    return $regions;
}

function regionId(array $regions, string $region): int
{
    return $regions[$region] ?? $regions['National'] ?? (int)reset($regions);
}

function normalizeRow(array $row, string $streamKey): array
{
    $row['stream_key'] = $streamKey;
    $row['region'] = $row['region'] ?: 'National';
    $row['confidence_score'] = max(0, min(100, (int)($row['confidence_score'] ?: defaultConfidence($streamKey, $row['source_type'] ?? ''))));
    $row['review_status'] = in_array($row['review_status'], ['Pending Review','Verified','Needs Review','Rejected'], true) ? $row['review_status'] : 'Pending Review';
    $row['signal_type'] = $row['signal_type'] ?: signalTypeForStream($streamKey);
    $row['purpose'] = $row['purpose'] ?: purposeForStream($streamKey);
    $row['recommended_action'] = $row['recommended_action'] ?: recommendedActionForStream($streamKey);
    $row['organization_type'] = $row['organization_type'] ?: organizationTypeForStream($streamKey);
    return $row;
}

function defaultConfidence(string $streamKey, string $sourceType): int
{
    if (str_contains(strtolower($sourceType), 'official') || $streamKey === 'broadband_funding') {
        return 90;
    }
    return in_array($streamKey, ['engineering_firm', 'contractor_discovery'], true) ? 65 : 75;
}

function streamTitle(string $streamKey): string
{
    return ucwords(str_replace('_', ' ', $streamKey));
}

function signalTypeForStream(string $streamKey): string
{
    return match ($streamKey) {
        'contractor_discovery' => 'Capacity',
        'engineering_firm', 'strategic_account' => 'Relationship',
        'broadband_funding', 'prime_contractor' => 'Opportunity',
        default => 'Market',
    };
}

function purposeForStream(string $streamKey): string
{
    return match ($streamKey) {
        'contractor_discovery' => 'Who Has Capacity',
        'engineering_firm' => 'Who Influences Work',
        'prime_contractor' => 'Where Work Is Flowing',
        default => 'Who Has Work',
    };
}

function organizationTypeForStream(string $streamKey): string
{
    return match ($streamKey) {
        'broadband_funding' => 'Funding Source',
        'strategic_account' => 'Strategic Account',
        'engineering_firm' => 'Engineering Firm',
        'contractor_discovery' => 'Capacity Provider',
        'prime_contractor' => 'Prime Contractor',
        default => 'Organization',
    };
}

function recommendedActionForStream(string $streamKey): string
{
    return match ($streamKey) {
        'broadband_funding' => 'Monitor funding source and create opportunity watch if scope is relevant.',
        'strategic_account' => 'Research account coverage and map construction/influence contacts.',
        'engineering_firm' => 'Map engineering firm relationships and identify preconstruction influence paths.',
        'contractor_discovery' => 'Qualify contractor and request onboarding information before approval.',
        'prime_contractor' => 'Watch prime activity and identify relationship or competitive response.',
        default => 'Review source evidence and determine next action.',
    };
}

function ownerForRegion(string $region): string
{
    return (new OwnerModelService())->ownerForRegionName($region ?: 'National', 'relationship_opportunity');
}

function ensureSignalSource(PDO $db, ?int $nationalRegionId, string $streamKey, string $path): int
{
    $name = 'Intelligence Stream - ' . streamTitle($streamKey);
    $stmt = $db->prepare('SELECT id FROM signal_sources WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }
    $db->prepare('INSERT INTO signal_sources (name, source_type, region_id, target_category, collection_method, source_url, frequency, status, notes) VALUES (?, "Manual Entry", ?, "Organization-Centric Intelligence", "Review-Gated CSV Import", ?, "Manual", "Paused", "Stream import source only. Do not run with generic harvesters; imported rows remain review-gated and organization-centric.")')
        ->execute([$name, $nationalRegionId, $path]);
    return (int)$db->lastInsertId();
}

function createHarvesterRun(PDO $db, int $sourceId, string $path, string $streamKey): int
{
    $db->prepare('INSERT INTO harvester_runs (signal_source_id, status, raw_payload_path, created_by, summary) VALUES (?, "Running", ?, "intelligence_stream_import", ?)')
        ->execute([$sourceId, $path, 'Review-gated import for ' . streamTitle($streamKey)]);
    return (int)$db->lastInsertId();
}

function finishHarvesterRun(PDO $db, int $runId, string $status, array $counts, string $error = ''): void
{
    $db->prepare('UPDATE harvester_runs SET finished_at = CURRENT_TIMESTAMP, status = ?, records_found = ?, records_created = ?, errors_count = ?, summary = ? WHERE id = ?')
        ->execute([$status, $counts['rows'] ?? 0, ($counts['organizations_created'] ?? 0) + ($counts['contacts_created'] ?? 0), $counts['errors'] ?? 0, $error ?: 'Intelligence stream import completed.', $runId]);
}

function createRawSignal(PDO $db, int $runId, int $sourceId, string $streamKey, array $row, int $rowNumber): int
{
    $payload = json_encode(['stream' => $streamKey, 'row_number' => $rowNumber, 'row' => $row], JSON_UNESCAPED_SLASHES);
    $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_contact_name, raw_phone, raw_email, raw_location, raw_state, raw_city, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Needs Review", ?, ?)')
        ->execute([
            $runId,
            $sourceId,
            $row['organization_name'] ?: streamTitle($streamKey),
            $row['evidence_summary'] ?: streamTitle($streamKey) . ' source evidence',
            $row['source_url'],
            $row['organization_name'],
            $row['contact_name'],
            $row['contact_phone'],
            $row['contact_email'],
            trim($row['market'] . ' ' . $row['region']),
            $row['state'],
            $row['market'],
            $payload,
            sha1(strtolower($streamKey . '|' . $row['organization_name'] . '|' . $row['source_url'] . '|' . $rowNumber)),
            'Imported by organization-centric stream; review before trusted use.',
        ]);
    return (int)$db->lastInsertId();
}

function createSignal(PDO $db, string $streamKey, array $row, int $organizationId, int $regionId): int
{
    $owner = ownerForRegion($row['region']);
    $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?, ?)')
        ->execute([
            streamTitle($streamKey) . ': ' . $row['organization_name'],
            $row['evidence_summary'] ?: $row['purpose'],
            signalTypeForStream($streamKey),
            sourceType($row['source_type']),
            $row['source_url'],
            $regionId,
            $row['state'],
            $row['market'],
            $row['organization_name'],
            $row['contact_name'],
            $row['confidence_score'],
            min(95, max(35, (int)$row['confidence_score'] + 5)),
            (int)$row['confidence_score'] >= 85 ? 'High' : 'Medium',
            in_array($owner, ['Admin','Mike','Ron'], true) ? $owner : 'Admin',
            $row['recommended_action'],
            'organization_id=' . $organizationId . '; stream=' . $streamKey . '; review_status=' . $row['review_status'],
        ]);
    return (int)$db->lastInsertId();
}

function sourceType(string $sourceType): string
{
    $allowed = ['Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other'];
    return in_array($sourceType, $allowed, true) ? $sourceType : (str_contains(strtolower($sourceType), 'government') ? 'Government Data' : 'Other');
}

function applyOrganizationClassifications(PDO $db, int $organizationId, string $streamKey, array $row): void
{
    $classifications = match ($streamKey) {
        'broadband_funding' => ['Has Work','Funding Source','Municipal / Public Entity'],
        'strategic_account' => ['Has Work','Strategic Account','Influences Work'],
        'engineering_firm' => ['Influences Work','Engineering Firm'],
        'contractor_discovery' => ['Has Capacity','Capacity Provider'],
        'prime_contractor' => ['Has Work','Prime Contractor','Competitor'],
        default => [],
    };
    foreach ($classifications as $classification) {
        $exists = $db->prepare('SELECT id FROM organization_classifications WHERE organization_id = ? AND classification = ? LIMIT 1');
        $exists->execute([$organizationId, $classification]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $db->prepare('INSERT INTO organization_classifications (organization_id, classification, confidence_score, source_url, review_status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$organizationId, $classification, $row['confidence_score'], $row['source_url'], $row['review_status']]);
    }
}

function createContactRoleAccess(PDO $db, int $contactId, int $organizationId, array $row): void
{
    $roleType = $row['role_type'] ?: $row['contact_title'];
    $accessCategory = $row['access_category'] ?: defaultAccessCategory($roleType, $row['stream_key']);
    $exists = $db->prepare('SELECT id FROM contact_role_access_profiles WHERE contact_id = ? AND organization_id = ? AND role_type = ? AND access_category = ? LIMIT 1');
    $exists->execute([$contactId, $organizationId, $roleType, $accessCategory]);
    if ($exists->fetchColumn()) {
        return;
    }
    $scores = roleScores($roleType, $accessCategory, (int)$row['confidence_score']);
    $db->prepare('INSERT INTO contact_role_access_profiles (contact_id, organization_id, role_type, access_category, decision_authority_score, influence_score, access_score, trust_score, strategic_value_score, confidence_score, source_url, review_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$contactId, $organizationId, $roleType, $accessCategory, $scores['decision'], $scores['influence'], $scores['access'], 20, $scores['strategic'], $row['confidence_score'], $row['source_url'], $row['review_status']]);
}

function defaultAccessCategory(string $roleType, string $streamKey): string
{
    $role = strtolower($roleType);
    if ($streamKey === 'contractor_discovery' || str_contains($role, 'foreman') || str_contains($role, 'splicer') || str_contains($role, 'operator')) {
        return 'Capacity Access';
    }
    if (str_contains($role, 'procurement') || str_contains($role, 'vendor')) {
        return 'Prime Access';
    }
    if (str_contains($role, 'utility') || str_contains($role, 'broadband')) {
        return 'Utility Access';
    }
    if (str_contains($role, 'project') || str_contains($role, 'construction') || str_contains($role, 'osp')) {
        return 'Project Access';
    }
    return 'Market Intelligence';
}

function roleScores(string $roleType, string $accessCategory, int $confidence): array
{
    $role = strtolower($roleType . ' ' . $accessCategory);
    $base = max(25, min(80, $confidence - 10));
    if (str_contains($role, 'director') || str_contains($role, 'manager') || str_contains($role, 'procurement')) {
        $base += 10;
    }
    return ['decision' => min(95, $base), 'influence' => min(95, $base + 5), 'access' => min(95, $base + 5), 'strategic' => min(95, $base)];
}

function createRelationshipProfile(PDO $db, int $contactId, int $organizationId, int $regionId, array $row): void
{
    $exists = $db->prepare('SELECT id FROM relationship_intelligence_profiles WHERE contact_id = ? LIMIT 1');
    $exists->execute([$contactId]);
    if ($exists->fetchColumn()) {
        return;
    }
    $scores = roleScores($row['role_type'] ?: $row['contact_title'], $row['access_category'], (int)$row['confidence_score']);
    $db->prepare('INSERT INTO relationship_intelligence_profiles (contact_id, organization_id, region_id, owner, decision_authority_score, influence_score, access_score, trust_score, strategic_value_score, relationship_value_score, relationship_priority, relationship_status, relationship_summary, known_context, next_best_action) VALUES (?, ?, ?, ?, ?, ?, ?, 20, ?, ?, ?, "Needs Review", ?, ?, ?)')
        ->execute([$contactId, $organizationId, $regionId, ownerForRegion($row['region']), $scores['decision'], $scores['influence'], $scores['access'], $scores['strategic'], min(95, $scores['influence'] + 5), $scores['influence'] >= 75 ? 'High' : 'Medium', $row['evidence_summary'], 'Source: ' . $row['source_url'], $row['recommended_action']]);
}

function createOpportunityWatch(PDO $db, int $organizationId, int $regionId, string $streamKey, array $row): ?int
{
    if (!in_array($row['signal_type'], ['Opportunity','Market','Work'], true) && !in_array($streamKey, ['broadband_funding','strategic_account','prime_contractor'], true)) {
        return null;
    }
    $name = streamTitle($streamKey) . ' watch: ' . $row['organization_name'];
    $exists = $db->prepare('SELECT id FROM opportunities WHERE organization_id = ? AND name = ? LIMIT 1');
    $exists->execute([$organizationId, $name]);
    $id = $exists->fetchColumn();
    if ($id) {
        return (int)$id;
    }
    $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, estimated_value, estimated_margin, probability, stage, capacity_required, decision_makers, next_action, owner, notes, opportunity_type, customer_type, funding_source, strategic_alignment_score, relationship_score, capacity_score, demand_score, risk_score, primary_owner, ownership_notes) VALUES (?, ?, ?, ?, 0, 0, ?, "Watch", 0, ?, ?, ?, ?, ?, ?, ?, ?, 40, 30, ?, 35, ?, ?)')
        ->execute([$name, $organizationId, $regionId, $row['market'], min(80, max(25, (int)$row['confidence_score'] - 10)), $row['contact_name'], $row['recommended_action'], ownerForRegion($row['region']), 'source_url=' . $row['source_url'] . "\n" . $row['evidence_summary'], 'Fiber Backbone Intelligence', $row['organization_type'], $streamKey === 'broadband_funding' ? $row['organization_name'] : '', min(95, (int)$row['confidence_score']), min(95, (int)$row['confidence_score']), ownerForRegion($row['region']), 'Created as opportunity watch from organization-centric stream.']);
    return (int)$db->lastInsertId();
}

function createCapacityProspect(PDO $db, int $organizationId, int $regionId, array $row): array
{
    $exists = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? LIMIT 1');
    $exists->execute([$organizationId]);
    $subId = $exists->fetchColumn();
    if (!$subId) {
        $db->prepare('INSERT INTO subcontractors (organization_id, region_id, markets_served, services_offered, crew_count, approval_stage, availability, performance_score, notes, company_name, website, phone, email, primary_contact, contact_title, primary_owner, ownership_notes) VALUES (?, ?, ?, ?, 0, "Prospect", "Needs Review", 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$organizationId, $regionId, $row['market'], $row['evidence_summary'] ?: $row['purpose'], 'source_url=' . $row['source_url'] . "\nreview_status=" . $row['review_status'], $row['organization_name'], $row['website'], $row['contact_phone'], $row['contact_email'], $row['contact_name'], $row['contact_title'], ownerForRegion($row['region']), 'Capacity prospect from organization-centric stream.']);
        $subId = (int)$db->lastInsertId();
    }
    $exists = $db->prepare('SELECT id FROM capacity_profiles WHERE organization_id = ? LIMIT 1');
    $exists->execute([$organizationId]);
    $profileId = $exists->fetchColumn();
    if (!$profileId) {
        $db->prepare('INSERT INTO capacity_profiles (profile_name, profile_type, organization_id, subcontractor_id, region_id, market, state, owner, status, primary_mobilization_readiness, notes, primary_owner, ownership_notes) VALUES (?, "Subcontractor", ?, ?, ?, ?, ?, ?, "Prospect", "Needs Review", ?, ?, ?)')
            ->execute([$row['organization_name'], $organizationId, $subId, $regionId, $row['market'], $row['state'], ownerForRegion($row['region']), 'source_url=' . $row['source_url'] . "\n" . $row['evidence_summary'], ownerForRegion($row['region']), 'Capacity profile from stream import; not approved.']);
        $profileId = (int)$db->lastInsertId();
    }
    return [(int)$subId, (int)$profileId];
}

function createCompetitorProfile(PDO $db, int $organizationId, int $regionId, array $row): void
{
    $exists = $db->prepare('SELECT id FROM competitor_profiles WHERE competitor_name = ? AND region_id = ? LIMIT 1');
    $exists->execute([$row['organization_name'], $regionId]);
    if ($exists->fetchColumn()) {
        return;
    }
    $db->prepare('INSERT INTO competitor_profiles (competitor_name, region_id, market, services, hiring_activity, award_activity, subcontractor_recruiting_activity, capacity_growth_score, competitive_pressure_score, threat_level, notes, source_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
        ->execute([$row['organization_name'], $regionId, $row['market'], $row['evidence_summary'], $row['signal_type'] === 'Hiring Activity' ? $row['evidence_summary'] : '', $row['signal_type'] === 'Award' ? $row['evidence_summary'] : '', '', max(20, (int)$row['confidence_score'] - 25), max(25, (int)$row['confidence_score'] - 15), (int)$row['confidence_score'] >= 85 ? 'High' : 'Medium', 'source_url=' . $row['source_url'], $row['recommended_action']]);
}

function createStrategicAccountProfile(PDO $db, int $organizationId, int $regionId, array $row): void
{
    $exists = $db->prepare('SELECT id FROM strategic_accounts WHERE account_name = ? AND region_id = ? LIMIT 1');
    $exists->execute([$row['organization_name'], $regionId]);
    if ($exists->fetchColumn()) {
        return;
    }
    $score = min(95, max(40, (int)$row['confidence_score']));
    $db->prepare('INSERT INTO strategic_accounts (account_name, account_type, region_id, relationship_coverage_score, opportunity_volume_score, capacity_demand_score, influence_coverage_score, strategic_score, primary_owner, next_best_action, notes, market, owner, relationship_health_score, opportunity_score, account_status, recent_signal_count, recommended_action, ownership_notes) VALUES (?, ?, ?, 25, ?, 50, 25, ?, ?, ?, ?, ?, ?, 25, ?, "Needs Review", 1, ?, ?)')
        ->execute([$row['organization_name'], $row['organization_type'], $regionId, $score, $score, ownerForRegion($row['region']), $row['recommended_action'], 'source_url=' . $row['source_url'] . "\n" . $row['evidence_summary'], $row['market'], ownerForRegion($row['region']), $score, $row['recommended_action'], 'Strategic account candidate from organization-centric stream.']);
}

function createAcquisitionTarget(PDO $db, int $organizationId, ?int $contactId, int $signalId, string $streamKey, int $regionId, array $row): ?int
{
    $duplicate = sha1(strtolower($streamKey . '|target|' . $organizationId . '|' . $row['source_url']));
    $exists = $db->prepare('SELECT id FROM acquisition_targets WHERE duplicate_key = ? LIMIT 1');
    $exists->execute([$duplicate]);
    if ($exists->fetchColumn()) {
        return null;
    }
    $db->prepare('INSERT INTO acquisition_targets (target_name, target_type, source_signal_id, source_type, source_url, organization_name, contact_name, email, phone, website, region_id, state, city, owner, acquisition_score, confidence_score, strategic_value_score, urgency_score, capacity_value_score, relationship_value_score, opportunity_value_score, status, priority, reason_to_pursue, recommended_next_action, notes, duplicate_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 50, ?, ?, ?, "Needs Review", ?, ?, ?, ?, ?)')
        ->execute([$row['organization_name'], targetTypeForStream($streamKey), $signalId, sourceType($row['source_type']), $row['source_url'], $row['organization_name'], $row['contact_name'], $row['contact_email'], $row['contact_phone'], $row['website'], $regionId, $row['state'], $row['market'], ownerForRegion($row['region']), min(95, (int)$row['confidence_score']), $row['confidence_score'], strategicValue($streamKey, $row), capacityValue($streamKey, $row), relationshipValue($streamKey, $row), opportunityValue($streamKey, $row), (int)$row['confidence_score'] >= 85 ? 'High' : 'Medium', $row['evidence_summary'] ?: $row['purpose'], $row['recommended_action'], 'organization_id=' . $organizationId . '; contact_id=' . ($contactId ?: '') . '; stream=' . $streamKey, $duplicate]);
    return (int)$db->lastInsertId();
}

function targetTypeForStream(string $streamKey): string
{
    return match ($streamKey) {
        'contractor_discovery' => 'Subcontractor',
        'engineering_firm' => 'Relationship Contact',
        'prime_contractor' => 'Prime Contractor',
        'strategic_account' => 'Strategic Account',
        default => 'Opportunity Source',
    };
}

function strategicValue(string $streamKey, array $row): int { return in_array($streamKey, ['strategic_account','broadband_funding','prime_contractor'], true) ? min(95, (int)$row['confidence_score']) : 60; }
function capacityValue(string $streamKey, array $row): int { return $streamKey === 'contractor_discovery' ? min(95, (int)$row['confidence_score']) : 35; }
function relationshipValue(string $streamKey, array $row): int { return in_array($streamKey, ['strategic_account','engineering_firm','prime_contractor'], true) ? min(90, (int)$row['confidence_score']) : 40; }
function opportunityValue(string $streamKey, array $row): int { return in_array($streamKey, ['broadband_funding','strategic_account','prime_contractor'], true) ? min(95, (int)$row['confidence_score']) : 30; }

function createRecommendedAction(PDO $db, int $organizationId, ?int $contactId, ?int $opportunityId, ?int $subcontractorId, string $streamKey, int $regionId, array $row): ?int
{
    $title = match ($streamKey) {
        'broadband_funding' => 'Monitor funding signal for ' . $row['organization_name'],
        'strategic_account' => 'Map account coverage for ' . $row['organization_name'],
        'engineering_firm' => 'Map engineering influence for ' . $row['organization_name'],
        'contractor_discovery' => 'Qualify capacity provider ' . $row['organization_name'],
        'prime_contractor' => 'Watch prime activity for ' . $row['organization_name'],
        default => 'Review intelligence for ' . $row['organization_name'],
    };
    $sourceType = $opportunityId ? 'opportunity' : ($subcontractorId ? 'subcontractor' : ($contactId ? 'contact' : 'organization'));
    $sourceId = $opportunityId ?: ($subcontractorId ?: ($contactId ?: $organizationId));
    $exists = $db->prepare('SELECT id FROM recommended_actions WHERE status = "Open" AND source_type = ? AND source_id = ? AND title = ? LIMIT 1');
    $exists->execute([$sourceType, $sourceId, $title]);
    if ($exists->fetchColumn()) {
        return null;
    }
    $db->prepare('INSERT INTO recommended_actions (title, category, region_id, priority, reason, recommended_next_action, assigned_owner, status, source_type, source_id, recommendation_type, priority_score, trigger_detail, why_it_matters, source_module, recommended_primary_owner, ownership_reason, shared_required) VALUES (?, ?, ?, ?, ?, ?, ?, "Open", ?, ?, "Stream Review", ?, ?, ?, "Organization-Centric Intelligence Streams", ?, ?, ?)')
        ->execute([$title, categoryForStream($streamKey), $regionId, (int)$row['confidence_score'] >= 85 ? 'High' : 'Medium', $row['evidence_summary'] ?: 'Source-backed intelligence requires review.', $row['recommended_action'], ownerForRegion($row['region']), $sourceType, $sourceId, min(95, (int)$row['confidence_score']), $row['source_url'], 'This record may create work, capacity, influence, or competitive awareness after review.', ownerForRegion($row['region']), 'Assigned from stream region and intelligence type.', in_array($row['region'], ['Southwest','National'], true) ? 1 : 0]);
    return (int)$db->lastInsertId();
}

function categoryForStream(string $streamKey): string
{
    return match ($streamKey) {
        'contractor_discovery' => 'Capacity',
        'engineering_firm', 'strategic_account' => 'Relationship',
        'prime_contractor' => 'Risk',
        default => 'Opportunity',
    };
}

function createEvidence(PDO $db, string $type, int $id, ?int $streamId, array $row): void
{
    if ($row['source_url'] === '') {
        return;
    }
    $exists = $db->prepare('SELECT id FROM source_evidence_records WHERE linked_record_type = ? AND linked_record_id = ? AND source_url = ? LIMIT 1');
    $exists->execute([$type, $id, $row['source_url']]);
    if ($exists->fetchColumn()) {
        return;
    }
    $db->prepare('INSERT INTO source_evidence_records (linked_record_type, linked_record_id, intelligence_stream_id, source_url, source_name, source_type, confidence_score, evidence_summary, review_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$type, $id, $streamId, $row['source_url'], streamTitle($row['stream_key']), $row['source_type'], $row['confidence_score'], $row['evidence_summary'], $row['review_status']]);
}

function dataQualityType(array $row): string
{
    if ($row['source_url'] === '') {
        return 'Source Reliability Concern';
    }
    if ((int)$row['confidence_score'] < 75) {
        return 'Low Confidence Signal';
    }
    return 'Disputed Classification';
}

function createDataQualityIssue(PDO $db, string $type, string $recordType, ?int $recordId, ?int $regionId, string $title, string $description, string $owner): void
{
    $exists = $db->prepare('SELECT id FROM data_quality_issues WHERE status IN ("Open","In Review") AND issue_type = ? AND linked_record_type = ? AND COALESCE(linked_record_id, 0) = ? AND title = ? LIMIT 1');
    $exists->execute([$type, $recordType, $recordId ?: 0, $title]);
    if ($exists->fetchColumn()) {
        return;
    }
    $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, assigned_owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$type, $recordType, $recordId, $regionId, $title, $description, $type === 'Low Confidence Signal' ? 'Medium' : 'High', $owner]);
}

function recordImport(PDO $db, string $streamKey, string $path, int $rowNumber, array $row, int $rawId, ?int $organizationId, ?int $contactId, ?int $signalId, ?int $opportunityId, ?int $subcontractorId, ?int $capacityProfileId, ?int $actionId, string $status): void
{
    $db->prepare('INSERT INTO intelligence_stream_import_records (stream_key, source_file_path, source_row, organization_id, contact_id, signal_id, opportunity_id, subcontractor_id, capacity_profile_id, recommended_action_id, raw_signal_item_id, review_status, confidence_score, source_url, evidence_summary, raw_payload_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$streamKey, $path, $rowNumber, $organizationId, $contactId, $signalId, $opportunityId, $subcontractorId, $capacityProfileId, $actionId, $rawId, $status, $row['confidence_score'], $row['source_url'], $row['evidence_summary'], json_encode($row, JSON_UNESCAPED_SLASHES)]);
}
