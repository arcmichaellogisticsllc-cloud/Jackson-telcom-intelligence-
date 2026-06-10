<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\OwnerModelService;

const DATASET_COLUMNS = [
    'strategic_accounts' => ['account_name','account_type','region','market','website','source_url','source_type','confidence_score','review_status','import_source'],
    'organizations' => ['organization_name','organization_type','region','state','market','website','services','source_url','source_type','confidence_score','review_status','import_source'],
    'contacts' => ['name','title','organization_name','region','market','public_profile_url','business_email','business_phone','source_url','source_type','role_type','influence_category','confidence_score','review_status','import_source'],
    'capacity_providers' => ['company_name','website','disciplines','region','state','market','contact_name','business_phone','business_email','coverage_area','crew_equipment_notes','source_url','source_type','confidence_score','review_status','import_source'],
    'engineering_firms' => ['company_name','website','region','state','market','osp_fiber_focus','utility_broadband_experience','key_public_contacts','source_url','source_type','confidence_score','review_status','import_source'],
    'primes_competitors' => ['organization_name','website','services','regions_markets','public_hiring_activity','public_awards_news','subcontractor_programs','competitive_pressure_notes','source_url','source_type','confidence_score','review_status','import_source'],
    'workforce' => ['name','role','organization_name','region','market','public_profile_url','skill_category','influence_score','recruitability_score','confidence_score','review_status','import_source','source_url','source_type'],
    'opportunities' => ['opportunity_name','source_organization','region','state','market','opportunity_type','fiber_backbone_alignment','estimated_value','funding_source','status','source_url','source_type','confidence_score','review_status','import_source'],
    'markets' => ['market','region','state','active_utilities','active_primes','engineering_firms','municipalities','broadband_programs','known_contacts','upcoming_opportunities','confidence_score','strategic_priority','source_url','source_type','review_status','import_source'],
];

if (($argv[1] ?? '') === '' || ($argv[2] ?? '') === '' || !isset(DATASET_COLUMNS[$argv[2]])) {
    echo "Usage: php scripts/import_real_hunt.php <csv_path> <dataset>\n";
    echo "Datasets: " . implode(', ', array_keys(DATASET_COLUMNS)) . "\n";
    exit(1);
}

$path = $argv[1];
$dataset = $argv[2];

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

$header = array_map(static fn ($value) => trim((string)$value), $header);
$missing = array_values(array_diff(DATASET_COLUMNS[$dataset], $header));
if ($missing) {
    echo "Import failed: missing required columns: " . implode(', ', $missing) . "\n";
    exit(1);
}

$db = Database::connection();
$regions = loadRegions($db);
$db->beginTransaction();

$sourceId = ensureSignalSource($db, $regions['National'] ?? null, $dataset, $path);
$runId = createHarvesterRun($db, $sourceId, $path);
$counts = ['created' => 0, 'skipped' => 0, 'review_required' => 0, 'raw_items' => 0, 'signals' => 0, 'errors' => 0];
$rowNumber = 1;

try {
    while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        $rowNumber++;
        if (count(array_filter($values, static fn ($value) => trim((string)$value) !== '')) === 0) {
            continue;
        }

        $row = array_combine($header, array_pad($values, count($header), ''));
        $row = array_map(static fn ($value) => trim((string)$value), $row);
        $row['import_source'] = $row['import_source'] ?: 'real_hunt';
        $row['review_status'] = normalizeReviewStatus($row['review_status'] ?? '');
        $row['confidence_score'] = clampScore($row['confidence_score'] ?? 0);

        $regionId = regionId($regions, $row['region'] ?? 'National');
        $rawId = createRawSignal($db, $runId, $sourceId, $dataset, $row, $rowNumber);
        $signalId = createSignal($db, $dataset, $row, $regionId);
        $counts['raw_items']++;
        $counts['signals']++;

        [$recordType, $recordId, $created, $needsReview, $notes] = importBusinessRecord($db, $dataset, $row, $regionId, $signalId);

        if ($needsReview || $row['confidence_score'] < 75 || $row['review_status'] !== 'Verified') {
            createDataQualityIssue($db, $dataset, $row, $regionId, $recordType, $recordId);
            $counts['review_required']++;
        }

        recordImport($db, $dataset, $path, $rowNumber, $row, $rawId, $signalId, $recordType, $recordId, $created ? 'Imported' : ($needsReview ? 'Needs Review' : 'Skipped'), $notes);
        $created ? $counts['created']++ : $counts['skipped']++;
    }

    finishHarvesterRun($db, $runId, 'Completed', $counts);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    finishHarvesterRun($db, $runId, 'Failed', ['created' => 0, 'raw_items' => 0, 'errors' => 1], $e->getMessage());
    echo "Import failed on row {$rowNumber}: {$e->getMessage()}\n";
    exit(1);
}

fclose($handle);

echo "Real hunt import completed dataset={$dataset} created={$counts['created']} skipped={$counts['skipped']} raw_items={$counts['raw_items']} signals={$counts['signals']} review_required={$counts['review_required']} errors={$counts['errors']}\n";

function loadRegions(PDO $db): array
{
    $rows = $db->query('SELECT id, name FROM regions')->fetchAll();
    $regions = [];
    foreach ($rows as $row) {
        $regions[$row['name']] = (int)$row['id'];
    }
    return $regions;
}

function regionId(array $regions, string $region): int
{
    $region = trim($region) ?: 'National';
    return $regions[$region] ?? $regions['National'] ?? (int)reset($regions);
}

function clampScore(mixed $value): int
{
    return max(0, min(100, (int)$value));
}

function normalizeReviewStatus(string $status): string
{
    $status = trim($status);
    return in_array($status, ['Pending Review','Verified','Needs Review'], true) ? $status : 'Pending Review';
}

function ownerForRegion(string $region): string
{
    return (new OwnerModelService())->ownerForRegionName($region ?: 'National', 'relationship_opportunity');
}

function signalOwnerForRegion(string $region): string
{
    $owner = (new OwnerModelService())->ownerForRegionName($region ?: 'National', 'relationship_opportunity');
    return in_array($owner, ['Admin', 'Mike', 'Ron', 'Unassigned'], true) ? $owner : 'Admin';
}

function signalTypeForDataset(string $dataset): string
{
    return match ($dataset) {
        'capacity_providers', 'engineering_firms', 'workforce' => 'Capacity',
        'contacts', 'strategic_accounts' => 'Relationship',
        'opportunities' => 'Opportunity',
        'markets', 'organizations', 'primes_competitors' => 'Market',
        default => 'Market',
    };
}

function sourceType(string $sourceType): string
{
    $allowed = ['Google Search','Google Business Profile','Facebook Marketplace','LinkedIn','Industry Forum','YouTube','Broadband Grant','Utility Announcement','Equipment Listing','New Business Filing','Hiring Activity','Manual Entry','Industry News','Referral','Conference','Website Form','Government Data','Contractor Intelligence','Other'];
    return in_array($sourceType, $allowed, true) ? $sourceType : 'Other';
}

function titleForRow(string $dataset, array $row): string
{
    return $row['account_name'] ?? $row['organization_name'] ?? $row['company_name'] ?? $row['opportunity_name'] ?? $row['market'] ?? $row['name'] ?? ucfirst(str_replace('_', ' ', $dataset));
}

function sourceUrlForRow(array $row): string
{
    return $row['source_url'] ?: ($row['website'] ?? '') ?: ($row['public_profile_url'] ?? '');
}

function ensureSignalSource(PDO $db, ?int $nationalRegionId, string $dataset, string $path): int
{
    $name = 'Real Hunt Import - ' . str_replace('_', ' ', ucwords($dataset, '_'));
    $stmt = $db->prepare('SELECT id FROM signal_sources WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $insert = $db->prepare('INSERT INTO signal_sources (name, source_type, region_id, target_category, collection_method, source_url, frequency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$name, 'Manual Entry', $nationalRegionId, 'Real Hunting Import', 'Review-Gated CSV Import', $path, 'Manual', 'Active', 'Production real-hunt import source. Imported rows remain review-gated.']);
    return (int)$db->lastInsertId();
}

function createHarvesterRun(PDO $db, int $sourceId, string $path): int
{
    $stmt = $db->prepare('INSERT INTO harvester_runs (signal_source_id, status, raw_payload_path, created_by, summary) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$sourceId, 'Running', $path, 'real_hunt_import', 'Review-gated real hunting import.']);
    return (int)$db->lastInsertId();
}

function finishHarvesterRun(PDO $db, int $runId, string $status, array $counts, string $error = ''): void
{
    $stmt = $db->prepare('UPDATE harvester_runs SET finished_at = CURRENT_TIMESTAMP, status = ?, records_found = ?, records_created = ?, errors_count = ?, summary = ? WHERE id = ?');
    $stmt->execute([$status, $counts['raw_items'] ?? 0, $counts['created'] ?? 0, $counts['errors'] ?? 0, $error ?: 'Real hunt import completed.', $runId]);
}

function createRawSignal(PDO $db, int $runId, int $sourceId, string $dataset, array $row, int $rowNumber): int
{
    $payload = json_encode(['dataset' => $dataset, 'row_number' => $rowNumber, 'row' => $row], JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_contact_name, raw_phone, raw_email, raw_location, raw_state, raw_city, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $company = $row['organization_name'] ?? $row['company_name'] ?? $row['account_name'] ?? $row['source_organization'] ?? '';
    $stmt->execute([
        $runId,
        $sourceId,
        titleForRow($dataset, $row),
        ($row['services'] ?? $row['opportunity_type'] ?? $row['disciplines'] ?? $row['title'] ?? 'Real hunting import row'),
        sourceUrlForRow($row),
        $company,
        $row['name'] ?? $row['contact_name'] ?? '',
        $row['business_phone'] ?? '',
        $row['business_email'] ?? '',
        trim(($row['market'] ?? '') . ' ' . ($row['region'] ?? '')),
        $row['state'] ?? '',
        $row['market'] ?? '',
        $payload,
        'New',
        sha1(strtolower($dataset . '|' . titleForRow($dataset, $row) . '|' . sourceUrlForRow($row))),
        'Imported from real_hunt; do not bypass Signal Quality or Data Quality review.',
    ]);
    return (int)$db->lastInsertId();
}

function createSignal(PDO $db, string $dataset, array $row, int $regionId): int
{
    $region = $row['region'] ?? 'National';
    $title = 'Real hunt: ' . titleForRow($dataset, $row);
    $description = implode(' | ', array_filter([
        ucfirst(str_replace('_', ' ', $dataset)),
        $row['services'] ?? $row['opportunity_type'] ?? $row['disciplines'] ?? $row['title'] ?? '',
        'Review status: ' . ($row['review_status'] ?? 'Pending Review'),
        'Source: ' . sourceUrlForRow($row),
    ]));

    $stmt = $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $score = clampScore($row['confidence_score'] ?? 0);
    $stmt->execute([
        $title,
        $description,
        signalTypeForDataset($dataset),
        sourceType($row['source_type'] ?? 'Manual Entry'),
        sourceUrlForRow($row),
        $regionId,
        $row['state'] ?? '',
        $row['market'] ?? '',
        $row['organization_name'] ?? $row['company_name'] ?? $row['account_name'] ?? $row['source_organization'] ?? '',
        $row['name'] ?? $row['contact_name'] ?? '',
        $score,
        max(30, min(95, $score + 5)),
        $score >= 85 ? 'High' : 'Medium',
        signalOwnerForRegion($region),
        'New',
        'Review the source, confirm data quality, then decide whether this becomes Work, Capacity, Need, or Influence intelligence.',
        'import_source=' . ($row['import_source'] ?? 'real_hunt') . '; review_status=' . ($row['review_status'] ?? 'Pending Review'),
    ]);
    return (int)$db->lastInsertId();
}

function importBusinessRecord(PDO $db, string $dataset, array $row, int $regionId, int $signalId): array
{
    return match ($dataset) {
        'strategic_accounts' => importStrategicAccount($db, $row, $regionId),
        'organizations' => importOrganization($db, $row, $regionId, $signalId),
        'contacts' => importContactOrRelationshipTarget($db, $row, $regionId),
        'capacity_providers' => importCapacityProvider($db, $row, $regionId),
        'engineering_firms' => importEngineeringFirm($db, $row, $regionId),
        'primes_competitors' => importPrimeCompetitor($db, $row, $regionId),
        'workforce' => importWorkforce($db, $row, $regionId),
        'opportunities' => importOpportunity($db, $row, $regionId),
        'markets' => importMarket($db, $row, $regionId),
        default => ['', null, false, true, 'Unsupported dataset.'],
    };
}

function findOrganization(PDO $db, string $name, int $regionId): ?int
{
    $stmt = $db->prepare('SELECT id FROM organizations WHERE lower(name) = lower(?) AND region_id = ? LIMIT 1');
    $stmt->execute([$name, $regionId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function ensureOrganization(PDO $db, string $name, string $type, int $regionId, array $row): int
{
    $existing = findOrganization($db, $name, $regionId);
    if ($existing) {
        return $existing;
    }
    $stmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $notes = 'import_source=' . ($row['import_source'] ?? 'real_hunt') . '; review_status=' . ($row['review_status'] ?? 'Pending Review') . '; source_url=' . sourceUrlForRow($row) . '; services=' . ($row['services'] ?? $row['disciplines'] ?? '');
    $stmt->execute([$name, $type ?: 'Other', $regionId, $row['state'] ?? '', $row['market'] ?? '', $row['website'] ?? '', $row['business_phone'] ?? '', $notes, 'Active']);
    return (int)$db->lastInsertId();
}

function importStrategicAccount(PDO $db, array $row, int $regionId): array
{
    $name = $row['account_name'];
    $stmt = $db->prepare('SELECT id FROM strategic_accounts WHERE lower(account_name) = lower(?) AND COALESCE(region_id, 0) = ? LIMIT 1');
    $stmt->execute([$name, $regionId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['strategic_account', (int)$existing, false, false, 'Duplicate strategic account skipped.'];
    }
    $notes = 'website=' . ($row['website'] ?? '') . '; source_url=' . sourceUrlForRow($row) . '; review_status=' . $row['review_status'];
    $insert = $db->prepare('INSERT INTO strategic_accounts (account_name, account_type, region_id, relationship_coverage_score, opportunity_volume_score, capacity_demand_score, influence_coverage_score, strategic_score, primary_owner, next_best_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$name, normalizeAccountType($row['account_type'] ?? 'Other'), $regionId, 20, 45, 55, 20, clampScore($row['confidence_score']), ownerForRegion($row['region'] ?? 'National'), 'Map public regional roles and validate active work signals before outreach.', $notes]);
    return ['strategic_account', (int)$db->lastInsertId(), true, $row['review_status'] !== 'Verified', 'Strategic account imported.'];
}

function normalizeAccountType(string $type): string
{
    $allowed = ['Utility','Prime Contractor','Telecom Provider','Electric Cooperative','Municipal Broadband','Engineering Firm','Strategic Partner','Other'];
    return in_array($type, $allowed, true) ? $type : 'Other';
}

function importOrganization(PDO $db, array $row, int $regionId, int $signalId): array
{
    $id = ensureOrganization($db, $row['organization_name'], $row['organization_type'] ?: 'Other', $regionId, $row);
    return ['organization', $id, true, $row['review_status'] !== 'Verified', 'Organization imported or matched.'];
}

function importContactOrRelationshipTarget(PDO $db, array $row, int $regionId): array
{
    $orgId = ensureOrganization($db, $row['organization_name'], 'Relationship Target', $regionId, $row);
    $name = trim($row['name']);
    if ($name === '') {
        $stmt = $db->prepare('INSERT INTO relationship_creation_signals (source, region_id, organization_name, contact_name, title, notes, confidence_score, recommended_next_action, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$row['source_type'] ?: 'Manual Entry', $regionId, $row['organization_name'], '', $row['title'], 'Role target imported without a public person name. source_url=' . sourceUrlForRow($row), clampScore($row['confidence_score']), 'Manually identify the public person currently holding this role before creating a contact.', 'New']);
        return ['relationship_creation_signal', (int)$db->lastInsertId(), true, true, 'Relationship target created instead of contact because public name is missing.'];
    }
    [$first, $last] = splitName($name);
    $check = $db->prepare('SELECT id FROM contacts WHERE lower(first_name) = lower(?) AND lower(last_name) = lower(?) AND organization_id = ? LIMIT 1');
    $check->execute([$first, $last, $orgId]);
    $existing = $check->fetchColumn();
    if ($existing) {
        return ['contact', (int)$existing, false, false, 'Duplicate contact skipped.'];
    }
    $stmt = $db->prepare('INSERT INTO contacts (first_name, last_name, title, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$first, $last, $row['title'], $row['business_email'], $row['business_phone'], $orgId, $regionId, ownerForRegion($row['region'] ?? 'National'), $row['influence_category'] ?: 'Unknown', 'Unknown', 'Verify source and assign relationship objective.', 'public_profile_url=' . ($row['public_profile_url'] ?? '') . '; source_url=' . sourceUrlForRow($row) . '; review_status=' . $row['review_status']]);
    $contactId = (int)$db->lastInsertId();
    $profile = $db->prepare('INSERT OR IGNORE INTO relationship_intelligence_profiles (contact_id, organization_id, region_id, owner, influence_score, access_score, trust_score, strategic_value_score, relationship_value_score, relationship_priority, relationship_status, relationship_summary, known_context, next_best_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $score = clampScore($row['confidence_score']);
    $profile->execute([$contactId, $orgId, $regionId, ownerForRegion($row['region'] ?? 'National'), $score, 35, 10, $score, $score, $score >= 80 ? 'High' : 'Medium', 'Unknown', 'Public business contact imported from real hunt.', 'Source URL: ' . sourceUrlForRow($row), 'Verify contact, add primary objective, then decide outreach.']);
    return ['contact', $contactId, true, $row['review_status'] !== 'Verified', 'Contact and relationship profile imported.'];
}

function splitName(string $name): array
{
    $parts = preg_split('/\s+/', trim($name));
    $first = array_shift($parts) ?: 'Unknown';
    $last = implode(' ', $parts) ?: 'Contact';
    return [$first, $last];
}

function importCapacityProvider(PDO $db, array $row, int $regionId): array
{
    $orgId = ensureOrganization($db, $row['company_name'], 'Subcontractor', $regionId, ['organization_name' => $row['company_name']] + $row);
    $stmt = $db->prepare('SELECT id FROM subcontractors WHERE organization_id = ? LIMIT 1');
    $stmt->execute([$orgId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['subcontractor', (int)$existing, false, false, 'Duplicate capacity provider skipped.'];
    }
    $insert = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, website, phone, email, primary_contact, states_served, markets_served, services_offered, approval_stage, availability, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$orgId, $regionId, $row['company_name'], $row['website'], $row['business_phone'], $row['business_email'], $row['contact_name'], $row['coverage_area'], $row['market'], $row['disciplines'], 'Prospect', 'Unknown', 'source_url=' . sourceUrlForRow($row) . '; crew_equipment_notes=' . $row['crew_equipment_notes'] . '; review_status=' . $row['review_status']]);
    $subId = (int)$db->lastInsertId();
    $profile = $db->prepare('INSERT INTO capacity_profiles (profile_name, profile_type, organization_id, subcontractor_id, region_id, market, state, owner, status, states_served, markets_served, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $profile->execute([$row['company_name'], 'Subcontractor', $orgId, $subId, $regionId, $row['market'], $row['state'], ownerForRegion($row['region'] ?? 'National'), 'Prospect', $row['coverage_area'], $row['market'], 'Review-gated capacity provider prospect from real_hunt. source_url=' . sourceUrlForRow($row)]);
    $capacityProfileId = (int)$db->lastInsertId();
    $trust = $db->prepare('INSERT INTO capacity_trust_scores (capacity_profile_id, safety_score, quality_score, communication_score, responsiveness_score, production_score, documentation_score, relationship_history_score, trust_score, trust_category) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, ?)');
    $trust->execute([$capacityProfileId, 'Developing']);
    return ['subcontractor', $subId, true, true, 'Capacity provider imported as Prospect only.'];
}

function importEngineeringFirm(PDO $db, array $row, int $regionId): array
{
    $id = ensureOrganization($db, $row['company_name'], 'Engineering Firm', $regionId, ['organization_name' => $row['company_name'], 'services' => $row['osp_fiber_focus']] + $row);
    return ['organization', $id, true, $row['review_status'] !== 'Verified', 'Engineering firm imported as organization.'];
}

function importPrimeCompetitor(PDO $db, array $row, int $regionId): array
{
    $orgId = ensureOrganization($db, $row['organization_name'], 'Prime Contractor', $regionId, ['organization_name' => $row['organization_name'], 'services' => $row['services'], 'market' => $row['regions_markets']] + $row);
    $stmt = $db->prepare('SELECT id FROM competitor_profiles WHERE lower(competitor_name) = lower(?) AND COALESCE(region_id, 0) = ? LIMIT 1');
    $stmt->execute([$row['organization_name'], $regionId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['competitor_profile', (int)$existing, false, false, 'Duplicate competitor profile skipped.'];
    }
    $score = clampScore($row['confidence_score']);
    $insert = $db->prepare('INSERT INTO competitor_profiles (competitor_name, region_id, market, services, hiring_activity, award_activity, office_expansion_activity, subcontractor_recruiting_activity, capacity_growth_score, competitive_pressure_score, threat_level, notes, source_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$row['organization_name'], $regionId, $row['regions_markets'], $row['services'], $row['public_hiring_activity'], $row['public_awards_news'], '', $row['subcontractor_programs'], $score, $score, $score >= 85 ? 'High' : 'Medium', 'source_url=' . sourceUrlForRow($row) . '; notes=' . $row['competitive_pressure_notes'], 1, 'Track hiring, awards, and subcontractor recruiting before pursuit decisions.']);
    return ['competitor_profile', (int)$db->lastInsertId(), true, $row['review_status'] !== 'Verified', 'Prime/competitor profile imported.'];
}

function normalizeRole(string $role): string
{
    $allowed = ['Program Manager','Project Manager','Construction Manager','OSP Manager','Foreman','Crew Leader','Fiber Splicer','Bore Operator','Aerial Lead','Inspector','QC Lead'];
    return in_array($role, $allowed, true) ? $role : 'Project Manager';
}

function importWorkforce(PDO $db, array $row, int $regionId): array
{
    if (trim($row['name']) === '') {
        createDataQualityIssue($db, 'workforce', $row, $regionId, '', null);
        return ['workforce_profile', null, false, true, 'Workforce row needs public name verification.'];
    }
    $stmt = $db->prepare('SELECT id FROM workforce_profiles WHERE lower(name) = lower(?) AND lower(COALESCE(current_company, "")) = lower(?) LIMIT 1');
    $stmt->execute([$row['name'], $row['organization_name']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['workforce_profile', (int)$existing, false, false, 'Duplicate workforce profile skipped.'];
    }
    $insert = $db->prepare('INSERT INTO workforce_profiles (name, current_company, role_type, region_id, market, skills, availability_status, influence_score, recruitability_score, relationship_score, notes, source_signal_count, recommended_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$row['name'], $row['organization_name'], normalizeRole($row['role']), $regionId, $row['market'], $row['skill_category'], 'Unknown', clampScore($row['influence_score']), clampScore($row['recruitability_score']), 0, 'source_url=' . sourceUrlForRow($row) . '; review_status=' . $row['review_status'], 1, 'Verify public profile and decide whether this is a recruit, influence, or market-intel relationship.']);
    return ['workforce_profile', (int)$db->lastInsertId(), true, $row['review_status'] !== 'Verified', 'Workforce profile imported.'];
}

function importOpportunity(PDO $db, array $row, int $regionId): array
{
    $orgId = ensureOrganization($db, $row['source_organization'], 'Opportunity Source', $regionId, ['organization_name' => $row['source_organization'], 'services' => $row['opportunity_type']] + $row);
    $stmt = $db->prepare('SELECT id FROM opportunities WHERE lower(name) = lower(?) AND region_id = ? LIMIT 1');
    $stmt->execute([$row['opportunity_name'], $regionId]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['opportunity', (int)$existing, false, false, 'Duplicate opportunity skipped.'];
    }
    $stage = match ($row['status']) {
        'Active' => 'Qualified',
        'Future' => 'Intelligence',
        'Research' => 'Intelligence',
        default => 'Watch',
    };
    $insert = $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, opportunity_type, customer_type, funding_source, estimated_value, stage, next_action, owner, strategic_alignment_score, demand_score, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $alignment = clampScore($row['fiber_backbone_alignment']);
    $insert->execute([$row['opportunity_name'], $orgId, $regionId, $row['market'], $row['opportunity_type'], 'Public / Utility / Broadband', $row['funding_source'], numericValue($row['estimated_value']), $stage, 'Verify procurement path, relationship owner, and capacity requirements.', ownerForRegion($row['region'] ?? 'National'), $alignment, clampScore($row['confidence_score']), 'source_url=' . sourceUrlForRow($row) . '; review_status=' . $row['review_status']]);
    $opportunityId = (int)$db->lastInsertId();
    $watch = $db->prepare('INSERT OR IGNORE INTO opportunity_watchlists (opportunity_id, region_id, status, reason, next_review_date, owner) VALUES (?, ?, ?, ?, date("now", "+30 days"), ?)');
    $watch->execute([$opportunityId, $regionId, $row['status'] === 'Active' ? 'Active Pursuit' : 'Watch', 'Public real-hunt opportunity requires human review before pursuit.', ownerForRegion($row['region'] ?? 'National')]);
    return ['opportunity', $opportunityId, true, $row['review_status'] !== 'Verified', 'Opportunity imported to watch/research state.'];
}

function numericValue(string $value): float
{
    $clean = preg_replace('/[^0-9.]/', '', $value);
    return $clean === '' ? 0.0 : (float)$clean;
}

function importMarket(PDO $db, array $row, int $regionId): array
{
    $stmt = $db->prepare('SELECT id FROM market_intelligence_profiles WHERE region_id = ? AND lower(market) = lower(?) LIMIT 1');
    $stmt->execute([$regionId, $row['market']]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return ['market_intelligence_profile', (int)$existing, false, false, 'Duplicate market skipped.'];
    }
    $insert = $db->prepare('INSERT INTO market_intelligence_profiles (region_id, market, active_utilities, active_primes, engineering_firms, municipalities, broadband_programs, known_contacts, upcoming_opportunities, confidence_score, strategic_priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$regionId, $row['market'], $row['active_utilities'], $row['active_primes'], $row['engineering_firms'], $row['municipalities'], $row['broadband_programs'], $row['known_contacts'], $row['upcoming_opportunities'], clampScore($row['confidence_score']), $row['strategic_priority'] ?: 'Medium']);
    $marketId = (int)$db->lastInsertId();
    $readiness = $db->prepare('INSERT INTO market_readiness_scores (market_profile_id, relationship_strength, capacity_strength, opportunity_activity, funding_activity, demand_visibility, competition_level, market_readiness_score, readiness_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $score = clampScore($row['confidence_score']);
    $readiness->execute([$marketId, 20, 20, $score, $score, 30, 40, max(20, min(80, $score - 10)), $score >= 80 ? 'Priority' : 'Developing']);
    $source = $db->prepare('INSERT INTO market_intelligence_sources (source_name, source_type, region_id, state, market, collection_method, signal_yield, opportunity_yield, relationship_yield, noise_level, quality_score, last_reviewed, next_review, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, date("now"), date("now", "+30 days"), ?)');
    $source->execute([$row['market'] . ' real hunt source', mapMarketSourceType($row['source_type']), $regionId, $row['state'], $row['market'], 'Manual Public Research', 1, 1, 0, 20, $score, 'source_url=' . sourceUrlForRow($row) . '; review_status=' . $row['review_status']]);
    return ['market_intelligence_profile', $marketId, true, $row['review_status'] !== 'Verified', 'Market intelligence profile imported.'];
}

function mapMarketSourceType(string $sourceType): string
{
    return str_contains(strtolower($sourceType), 'government') ? 'Funding Intelligence' : 'Infrastructure Growth Intelligence';
}

function createDataQualityIssue(PDO $db, string $dataset, array $row, int $regionId, string $recordType, ?int $recordId): void
{
    $issueType = ($row['confidence_score'] ?? 0) < 75 ? 'Low Confidence Signal' : 'Missing Contact Info';
    if (($row['review_status'] ?? '') === 'Needs Review') {
        $issueType = 'Source Reliability Concern';
    }
    if (in_array($dataset, ['contacts','workforce'], true) && trim($row['name'] ?? '') === '') {
        $issueType = 'Missing Contact Info';
    }
    $stmt = $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, status, assigned_owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $issueType,
        $recordType ?: $dataset,
        $recordId,
        $regionId,
        'Review real hunt import: ' . titleForRow($dataset, $row),
        'Dataset=' . $dataset . '; review_status=' . ($row['review_status'] ?? '') . '; confidence=' . ($row['confidence_score'] ?? '') . '; source_url=' . sourceUrlForRow($row),
        ($row['confidence_score'] ?? 0) < 60 ? 'High' : 'Medium',
        'Open',
        ownerForRegion($row['region'] ?? 'National'),
    ]);
}

function recordImport(PDO $db, string $dataset, string $path, int $rowNumber, array $row, int $rawId, int $signalId, string $recordType, ?int $recordId, string $status, string $notes): void
{
    $stmt = $db->prepare('INSERT INTO real_hunt_import_records (dataset, source_file, source_row, import_source, source_url, source_type, confidence_score, review_status, raw_signal_item_id, signal_id, created_record_type, created_record_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$dataset, $path, $rowNumber, $row['import_source'] ?? 'real_hunt', sourceUrlForRow($row), $row['source_type'] ?? '', clampScore($row['confidence_score'] ?? 0), $row['review_status'] ?? 'Pending Review', $rawId, $signalId, $recordType, $recordId, $status, $notes]);
}
