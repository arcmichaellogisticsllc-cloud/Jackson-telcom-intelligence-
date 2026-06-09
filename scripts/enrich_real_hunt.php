<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$db->beginTransaction();

try {
    clearPriorEnrichment($db);

    $counts = [
        'signal_quality' => enrichSignalQuality($db),
        'source_quality' => enrichSourceQuality($db),
        'strategic_accounts' => enrichStrategicAccounts($db),
        'organizations' => enrichOrganizations($db),
        'capacity' => enrichCapacityProviders($db),
        'opportunities' => enrichOpportunities($db),
        'markets' => enrichMarkets($db),
        'relationship_targets' => enrichRelationshipTargets($db),
        'executive_packages' => enrichExecutivePackages($db),
        'review_items' => enrichReviewQueue($db),
    ];

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "Real hunt enrichment failed: {$e->getMessage()}\n");
    exit(1);
}

foreach ($counts as $key => $count) {
    echo str_pad($key, 24) . $count . PHP_EOL;
}
echo "Real hunt enrichment completed. No call notes, completed outreach, or Daily Actions were created.\n";

function clearPriorEnrichment(PDO $db): void
{
    foreach ([
        "DELETE FROM package_timeline_events WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM package_actions WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM decision_packages WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM work_packages WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM capacity_packages WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM need_packages WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM influence_packages WHERE executive_package_id IN (SELECT id FROM executive_packages WHERE source_module = 'Real Hunt Enrichment')",
        "DELETE FROM executive_packages WHERE source_module = 'Real Hunt Enrichment'",
        "DELETE FROM recommended_actions WHERE source_module = 'Real Hunt Enrichment'",
        "DELETE FROM data_review_items WHERE review_type = 'Data Quality' AND linked_record_type LIKE 'real_hunt_%'",
        "DELETE FROM acquisition_watchlists WHERE recent_change LIKE 'Real hunt enrichment:%'",
        "DELETE FROM acquisition_scores WHERE entity_type LIKE 'real_hunt_%'",
        "DELETE FROM acquisition_classifications WHERE entity_type LIKE 'real_hunt_%'",
        "DELETE FROM work_intelligence WHERE rowid IN (SELECT wi.rowid FROM work_intelligence wi JOIN organizations o ON o.id = wi.organization_id WHERE o.notes LIKE '%import_source=real_hunt%')",
        "DELETE FROM need_intelligence WHERE rowid IN (SELECT ni.rowid FROM need_intelligence ni JOIN organizations o ON o.id = ni.organization_id WHERE o.notes LIKE '%import_source=real_hunt%')",
        "DELETE FROM capacity_intelligence WHERE capacity_profile_id IN (SELECT id FROM capacity_profiles WHERE notes LIKE '%real_hunt%')",
        "DELETE FROM strategic_alignment_profiles WHERE opportunity_id IN (SELECT id FROM opportunities WHERE notes LIKE '%real_hunt%' OR notes LIKE '%source_url=%')",
        "DELETE FROM pursuit_scores WHERE opportunity_id IN (SELECT id FROM opportunities WHERE notes LIKE '%real_hunt%' OR notes LIKE '%source_url=%')",
        "DELETE FROM opportunity_pursuit_decisions WHERE opportunity_id IN (SELECT id FROM opportunities WHERE notes LIKE '%real_hunt%' OR notes LIKE '%source_url=%')",
        "DELETE FROM subcontractor_onboarding WHERE subcontractor_id IN (SELECT id FROM subcontractors WHERE notes LIKE '%real_hunt%' OR notes LIKE '%source_url=%')",
        "DELETE FROM strategic_account_onboarding WHERE strategic_account_id IN (SELECT id FROM strategic_accounts WHERE notes LIKE '%source_url=%')",
        "DELETE FROM market_onboarding WHERE market_profile_id IN (SELECT id FROM market_intelligence_profiles)",
        "DELETE FROM real_hunt_enrichment_records",
    ] as $sql) {
        if (str_contains($sql, 'source_module') && !columnExists($db, 'executive_packages', 'source_module')) {
            continue;
        }
        $db->exec($sql);
    }
}

function columnExists(PDO $db, string $table, string $column): bool
{
    foreach ($db->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function enrichSignalQuality(PDO $db): int
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO signal_quality_profiles (signal_id, source_quality_score, signal_value_score, strategic_value_score, capacity_value_score, opportunity_value_score, relationship_value_score, revenue_value_score, confidence_score, impact_score, accumulation_score, classification, reason_for_classification, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "System", CURRENT_TIMESTAMP)');
    $count = 0;
    foreach ($db->query("SELECT * FROM signals WHERE notes LIKE '%import_source=real_hunt%'")->fetchAll() as $signal) {
        $confidence = (int)$signal['confidence_score'];
        $type = $signal['signal_type'];
        $sourceQuality = sourceQualityScore((string)$signal['source_type'], $confidence);
        $capacity = $type === 'Capacity' ? min(100, $confidence + 10) : 20;
        $opportunity = $type === 'Opportunity' ? min(100, $confidence + 10) : 25;
        $relationship = $type === 'Relationship' ? min(100, $confidence + 10) : 25;
        $strategic = max($capacity, $opportunity, $relationship, $type === 'Market' ? $confidence : 40);
        $value = (int)round(($sourceQuality + $strategic + $confidence) / 3);
        $classification = $confidence >= 90 || $value >= 88 ? 'Escalate' : ($confidence >= 75 || $value >= 72 ? 'Hunt' : 'Watch');
        $reason = 'Real-hunt enrichment classified this signal from source confidence, source type, and workflow value. Human review still required before trusted use.';
        $stmt->execute([(int)$signal['id'], $sourceQuality, $value, $strategic, $capacity, $opportunity, $relationship, $opportunity, $confidence, (int)$signal['impact_score'], $value, $classification, $reason]);
        $count += $stmt->rowCount();
        provenance($db, 'signal_quality', 'signal', (int)$signal['id'], 'signal_quality_profile', null, $confidence, reviewStatusFromNotes((string)$signal['notes']), (string)$signal['source_url'], $classification);
    }
    return $count;
}

function sourceQualityScore(string $sourceType, int $confidence): int
{
    if (in_array($sourceType, ['Government Data','Broadband Grant','Utility Announcement'], true)) {
        return min(100, max(90, $confidence));
    }
    if (in_array($sourceType, ['Industry News','Contractor Intelligence','Manual Entry'], true)) {
        return max(55, min(85, $confidence));
    }
    return max(45, min(80, $confidence));
}

function enrichSourceQuality(PDO $db): int
{
    $insert = $db->prepare('INSERT OR REPLACE INTO source_quality_profiles (signal_source_id, total_signals, escalated_signals, hunt_signals, watch_signals, archived_signals, converted_targets, converted_opportunities, converted_subcontractors, source_quality_score, last_updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, CURRENT_TIMESTAMP)');
    $count = 0;
    foreach ($db->query('SELECT ss.id, COUNT(rsi.id) total FROM signal_sources ss LEFT JOIN raw_signal_items rsi ON rsi.signal_source_id = ss.id WHERE ss.name LIKE "Real Hunt Import%" GROUP BY ss.id')->fetchAll() as $source) {
        $quality = $db->prepare('SELECT classification, COUNT(*) total FROM signal_quality_profiles sqp JOIN signals s ON s.id = sqp.signal_id WHERE s.notes LIKE "%import_source=real_hunt%" AND s.title LIKE ? GROUP BY classification');
        $quality->execute(['%']);
        $rows = $quality->fetchAll();
        $total = (int)$source['total'];
        $score = min(100, 60 + min(30, $total));
        $insert->execute([(int)$source['id'], $total, 0, 0, $total, 0, 0, $score]);
        $count++;
    }
    return $count;
}

function enrichStrategicAccounts(PDO $db): int
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO strategic_account_onboarding (strategic_account_id, region_id, onboarding_status, account_owner, relationship_coverage, influence_coverage, opportunity_count, capacity_demand, account_readiness_score, readiness_category, missing_items, risk_flags, next_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $count = 0;
    foreach ($db->query("SELECT * FROM strategic_accounts WHERE notes LIKE '%source_url=%'")->fetchAll() as $account) {
        $score = (int)$account['strategic_score'];
        $status = $score >= 85 ? 'Opportunity Mapping' : 'Relationship Mapping';
        $readiness = $score >= 85 ? 'Ready' : 'Developing';
        $missing = 'Named project managers; regional construction contacts; public procurement path; relationship objectives.';
        $risk = 'Public research only. Do not treat as active relationship coverage until verified by operator.';
        $stmt->execute([(int)$account['id'], (int)$account['region_id'], $status, $account['primary_owner'] ?: regionOwner($db, (int)$account['region_id']), 20, 20, opportunityCountForAccount($db, (string)$account['account_name']), (int)$account['capacity_demand_score'], $score, $readiness, $missing, $risk, 'Verify regional roles and map account influence before outreach.']);
        $count += $stmt->rowCount();
        classify($db, 'real_hunt_strategic_account', (int)$account['id'], 'Work', (int)$account['region_id'], 'Strategic account may control fiber backbone work.');
        score($db, 'real_hunt_strategic_account', (int)$account['id'], (int)$account['region_id'], $score, 20, 0, 20);
        watch($db, 'Work', 'real_hunt_strategic_account', (int)$account['id'], (int)$account['region_id'], 'Real hunt enrichment: strategic account imported from official/public source.', 'Verify account coverage and relationship gaps.');
        provenance($db, 'strategic_account_onboarding', 'strategic_account', (int)$account['id'], 'strategic_account_onboarding', null, $score, 'Pending Review', sourceFromNotes((string)$account['notes']), $missing);
    }
    return $count;
}

function opportunityCountForAccount(PDO $db, string $accountName): int
{
    $token = strtok($accountName, ' ') ?: $accountName;
    $stmt = $db->prepare('SELECT COUNT(*) FROM opportunities op LEFT JOIN organizations o ON o.id = op.organization_id WHERE op.name LIKE ? OR o.name LIKE ?');
    $like = '%' . $token . '%';
    $stmt->execute([$like, $like]);
    return (int)$stmt->fetchColumn();
}

function enrichOrganizations(PDO $db): int
{
    $count = 0;
    foreach ($db->query("SELECT * FROM organizations WHERE notes LIKE '%import_source=real_hunt%'")->fetchAll() as $org) {
        $category = organizationCategory((string)$org['type']);
        $work = $category === 'Work' ? 78 : 20;
        $capacity = $category === 'Capacity' ? 72 : 15;
        $influence = in_array($org['type'], ['Engineering Firm','Government','Utility','Municipality'], true) ? 65 : 20;
        $need = $category === 'Need' ? 60 : 10;
        classify($db, 'real_hunt_organization', (int)$org['id'], $category, (int)$org['region_id'], 'Organization enriched from real-hunt public research.');
        score($db, 'real_hunt_organization', (int)$org['id'], (int)$org['region_id'], $work, $capacity, $need, $influence);
        if ($category === 'Work') {
            $stmt = $db->prepare('INSERT OR IGNORE INTO work_intelligence (organization_id, region_id, market, work_type, work_status, estimated_value, confidence_score, strategic_value_score, relationship_strength, capacity_required, source_signal_count, work_readiness_score) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)');
            $signalCount = signalCount($db, (string)$org['name'], (int)$org['region_id']);
            $readiness = min(95, 60 + ($signalCount * 5));
            $stmt->execute([(int)$org['id'], (int)$org['region_id'], $org['city'] ?: $org['state'], 'Fiber / utility / broadband infrastructure', 'Upcoming', 78, 82, 'Unknown', 0, $signalCount, $readiness]);
            $count += $stmt->rowCount();
        } elseif ($category === 'Need' || $category === 'Capacity') {
            $stmt = $db->prepare('INSERT OR IGNORE INTO need_intelligence (organization_id, region_id, workload_status, confidence_score, capacity_available, estimated_idle_crews, urgency, need_score) VALUES (?, ?, ?, ?, 0, 0, ?, ?)');
            $stmt->execute([(int)$org['id'], (int)$org['region_id'], 'Unknown', 55, 'Medium', $need]);
            $count += $stmt->rowCount();
        }
        provenance($db, 'organization_classification', 'organization', (int)$org['id'], 'acquisition_classification', null, max($work, $capacity, $need, $influence), 'Pending Review', sourceFromNotes((string)$org['notes']), $category);
    }
    return $count;
}

function organizationCategory(string $type): string
{
    return match ($type) {
        'Utility', 'Telecom Provider', 'Municipal Broadband', 'Municipality', 'Government', 'Opportunity Source' => 'Work',
        'Subcontractor', 'Prime Contractor' => 'Capacity',
        'Engineering Firm' => 'Influence',
        default => 'Work',
    };
}

function enrichCapacityProviders(PDO $db): int
{
    $onboarding = $db->prepare('INSERT OR IGNORE INTO subcontractor_onboarding (subcontractor_id, region_id, onboarding_status, onboarding_score, readiness_category, assigned_owner, w9_status, coi_status, msa_status, nda_status, safety_program_status, coverage_area, disciplines, crew_counts, equipment_counts, missing_items, approval_notes, risk_flags) VALUES (?, ?, "Prospect", ?, ?, ?, "Missing", "Missing", "Missing", "Missing", "Missing", ?, ?, "Unknown", "Unknown", ?, ?, ?)');
    $intel = $db->prepare('INSERT OR IGNORE INTO capacity_intelligence (capacity_profile_id, region_id, disciplines, available_crews, mobilization_readiness, trust_score, capacity_contribution_score, deployable_capacity_score) VALUES (?, ?, ?, 0, ?, 0, ?, ?)');
    $discipline = $db->prepare('INSERT INTO capacity_discipline_counts (capacity_profile_id, discipline, unknown_count) VALUES (?, ?, 1)');
    $count = 0;
    foreach ($db->query("SELECT s.*, cp.id capacity_profile_id, cp.primary_mobilization_readiness FROM subcontractors s JOIN capacity_profiles cp ON cp.subcontractor_id = s.id WHERE s.notes LIKE '%source_url=%'")->fetchAll() as $sub) {
        $disciplines = (string)$sub['services_offered'];
        $score = capacityContributionEstimate($disciplines);
        $category = $score >= 80 ? 'Ready' : ($score >= 60 ? 'Developing' : 'Not Ready');
        $missing = 'W9, COI, MSA, NDA, Safety Program, crew counts, equipment counts, internal trust verification.';
        $risk = 'Public source only. Prospect cannot be treated as approved capacity.';
        $onboarding->execute([(int)$sub['id'], (int)$sub['region_id'], $score, $category, regionOwner($db, (int)$sub['region_id']), $sub['states_served'], $disciplines, $missing, 'Imported as Prospect only from public research.', $risk]);
        $intel->execute([(int)$sub['capacity_profile_id'], (int)$sub['region_id'], $disciplines, $sub['primary_mobilization_readiness'] ?: '30 Days', $score, max(20, $score - 15)]);
        foreach (disciplineList($disciplines) as $item) {
            $discipline->execute([(int)$sub['capacity_profile_id'], $item]);
        }
        classify($db, 'real_hunt_capacity_provider', (int)$sub['id'], 'Capacity', (int)$sub['region_id'], 'Public provider profile indicates possible construction capacity.');
        score($db, 'real_hunt_capacity_provider', (int)$sub['id'], (int)$sub['region_id'], 0, $score, 0, 10);
        watch($db, 'Capacity', 'real_hunt_capacity_provider', (int)$sub['id'], (int)$sub['region_id'], 'Real hunt enrichment: capacity prospect requires onboarding review.', 'Verify services, crews, equipment, insurance, W9, and safety program.');
        provenance($db, 'capacity_onboarding', 'subcontractor', (int)$sub['id'], 'subcontractor_onboarding', null, $score, 'Needs Review', sourceFromNotes((string)$sub['notes']), $missing);
        $count++;
    }
    return $count;
}

function capacityContributionEstimate(string $disciplines): int
{
    $score = 45;
    foreach (['fiber', 'telecom', 'communications', 'aerial', 'underground', 'directional', 'splicing', 'utility'] as $term) {
        if (str_contains(strtolower($disciplines), $term)) {
            $score += 6;
        }
    }
    return min(90, $score);
}

function disciplineList(string $disciplines): array
{
    $map = [
        'aerial' => 'Aerial',
        'underground' => 'Underground',
        'splicing' => 'Fiber Splicing',
        'directional' => 'Directional Boring',
        'utility' => 'Make Ready',
        'inspection' => 'Inspection',
        'qc' => 'QC',
    ];
    $found = [];
    $text = strtolower($disciplines);
    foreach ($map as $needle => $label) {
        if (str_contains($text, $needle)) {
            $found[] = $label;
        }
    }
    return $found ?: ['Underground'];
}

function enrichOpportunities(PDO $db): int
{
    $alignment = $db->prepare('INSERT OR REPLACE INTO strategic_alignment_profiles (opportunity_id, fiber_backbone_alignment_score, strategic_market_score, relationship_alignment_score, capacity_alignment_score, strategic_alignment_score, category, classification, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $pursuit = $db->prepare('INSERT OR REPLACE INTO pursuit_scores (opportunity_id, relationship_fit_score, capacity_fit_score, market_fit_score, margin_score, risk_score, pursuit_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $decision = $db->prepare('INSERT OR REPLACE INTO opportunity_pursuit_decisions (opportunity_id, region_id, recommended_decision, decision_reason, relationship_gap, capacity_gap, next_best_action) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $count = 0;
    foreach ($db->query("SELECT * FROM opportunities WHERE notes LIKE '%source_url=%'")->fetchAll() as $opportunity) {
        $fiber = (int)$opportunity['strategic_alignment_score'];
        $market = marketScore($db, (int)$opportunity['region_id'], (string)$opportunity['market']);
        $relationship = 25;
        $capacity = 30;
        $strategic = (int)round(($fiber * .45) + ($market * .25) + ($relationship * .15) + ($capacity * .15));
        $category = $strategic >= 85 ? 'Core' : ($strategic >= 72 ? 'Strong' : ($strategic >= 55 ? 'Moderate' : 'Weak'));
        $classification = $fiber >= 85 ? 'Core' : ($fiber >= 70 ? 'Supporting' : 'Adjacent');
        $pursuitScore = (int)round(($strategic * .5) + ($relationship * .2) + ($capacity * .2) + 5);
        $decisionText = $pursuitScore >= 75 ? 'Pursue Selectively' : 'Monitor';
        $alignment->execute([(int)$opportunity['id'], $fiber, $market, $relationship, $capacity, $strategic, $category, $classification, 'Real-hunt enrichment based on public source, market readiness, and backbone alignment.']);
        $pursuit->execute([(int)$opportunity['id'], $relationship, $capacity, $market, 0, 40, $pursuitScore]);
        $decision->execute([(int)$opportunity['id'], (int)$opportunity['region_id'], $decisionText, 'Review-gated public opportunity. Relationship and capacity fit are not yet verified.', 'Named decision makers and relationship objectives missing.', 'Capacity plan and approved subcontractor coverage missing.', 'Verify procurement path, map relationship owner, and review capacity before bid/pursuit.']);
        classify($db, 'real_hunt_opportunity', (int)$opportunity['id'], 'Work', (int)$opportunity['region_id'], 'Public opportunity source indicates future fiber/broadband work.');
        score($db, 'real_hunt_opportunity', (int)$opportunity['id'], (int)$opportunity['region_id'], $strategic, 0, 0, $relationship);
        watch($db, 'Work', 'real_hunt_opportunity', (int)$opportunity['id'], (int)$opportunity['region_id'], 'Real hunt enrichment: public opportunity requires procurement and relationship review.', 'Review opportunity source and map decision path.');
        provenance($db, 'opportunity_pursuit_context', 'opportunity', (int)$opportunity['id'], 'opportunity_pursuit_decision', null, $pursuitScore, 'Pending Review', sourceFromNotes((string)$opportunity['notes']), $decisionText);
        $count++;
    }
    return $count;
}

function marketScore(PDO $db, int $regionId, string $market): int
{
    $stmt = $db->prepare('SELECT confidence_score FROM market_intelligence_profiles WHERE region_id = ? AND (market = ? OR market LIKE ?) ORDER BY confidence_score DESC LIMIT 1');
    $stmt->execute([$regionId, $market, '%' . $market . '%']);
    return (int)($stmt->fetchColumn() ?: 65);
}

function enrichMarkets(PDO $db): int
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO market_onboarding (market_profile_id, region_id, market, onboarding_status, utilities, engineering_firms, primes, subcontractors, workforce, strategic_accounts, opportunity_density, market_readiness_score, readiness_category, assigned_owner, missing_items, risk_flags, next_action) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $count = 0;
    foreach ($db->query('SELECT mip.*, mrs.market_readiness_score, mrs.readiness_category FROM market_intelligence_profiles mip LEFT JOIN market_readiness_scores mrs ON mrs.market_profile_id = mip.id')->fetchAll() as $market) {
        $score = (int)($market['market_readiness_score'] ?: $market['confidence_score']);
        $status = $score >= 75 ? 'Relationship Mapping' : 'Researching';
        $missing = 'Named utility contacts, prime contacts, verified capacity providers, workforce leaders, and procurement cadence.';
        $risk = 'Market intelligence is public-source only until operator validates relationships and capacity.';
        $stmt->execute([(int)$market['id'], (int)$market['region_id'], $market['market'], $status, $market['active_utilities'], $market['engineering_firms'], $market['active_primes'], '', $market['known_contacts'], '', opportunityDensity($db, (int)$market['region_id'], (string)$market['market']), $score, readiness($score), regionOwner($db, (int)$market['region_id']), $missing, $risk, 'Complete utility, prime, capacity, and relationship mapping.']);
        watch($db, 'Work', 'real_hunt_market', (int)$market['id'], (int)$market['region_id'], 'Real hunt enrichment: market has public funding/work signals.', 'Verify market map and assign relationship/capacity review.');
        provenance($db, 'market_onboarding', 'market_intelligence_profile', (int)$market['id'], 'market_onboarding', null, $score, 'Pending Review', '', $missing);
        $count += $stmt->rowCount();
    }
    return $count;
}

function opportunityDensity(PDO $db, int $regionId, string $market): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM opportunities WHERE region_id = ? AND (market = ? OR market LIKE ?)');
    $stmt->execute([$regionId, $market, '%' . strtok($market, ' /') . '%']);
    return (int)$stmt->fetchColumn();
}

function readiness(int $score): string
{
    return $score >= 85 ? 'Ready' : ($score >= 65 ? 'Developing' : 'Not Ready');
}

function enrichRelationshipTargets(PDO $db): int
{
    $count = 0;
    foreach ($db->query("SELECT * FROM relationship_creation_signals WHERE notes LIKE '%source_url=%' OR notes LIKE '%Role target%'")->fetchAll() as $target) {
        classify($db, 'real_hunt_relationship_target', (int)$target['id'], 'Influence', (int)$target['region_id'], 'Role target may influence work, capacity, utility access, or market intelligence.');
        score($db, 'real_hunt_relationship_target', (int)$target['id'], (int)$target['region_id'], 0, 0, 0, (int)$target['confidence_score']);
        watch($db, 'Influence', 'real_hunt_relationship_target', (int)$target['id'], (int)$target['region_id'], 'Real hunt enrichment: relationship role target needs public person verification.', 'Find verified public person before creating contact or outreach.');
        provenance($db, 'relationship_target', 'relationship_creation_signal', (int)$target['id'], 'acquisition_classification', null, (int)$target['confidence_score'], 'Needs Review', '', (string)$target['recommended_next_action']);
        $count++;
    }
    return $count;
}

function enrichExecutivePackages(PDO $db): int
{
    $package = $db->prepare('INSERT INTO executive_packages (package_title, package_type, region_id, market, confidence_score, impact_score, urgency_score, decision_required, executive_summary, recommended_action, risk_of_inaction, owner, source_record_type, source_record_id, package_status, source_module) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", "Real Hunt Enrichment")');
    $timeline = $db->prepare('INSERT INTO package_timeline_events (executive_package_id, event_type, event_title, event_summary, owner) VALUES (?, "Created", ?, ?, ?)');
    $count = 0;

    foreach ($db->query('SELECT * FROM opportunities ORDER BY strategic_alignment_score DESC, id LIMIT 8')->fetchAll() as $opportunity) {
        $owner = regionOwner($db, (int)$opportunity['region_id']);
        $summary = 'Public-source opportunity requires procurement, relationship, and capacity validation before pursuit.';
        $package->execute(['Review public opportunity: ' . $opportunity['name'], 'Work', (int)$opportunity['region_id'], $opportunity['market'], (int)$opportunity['demand_score'], 78, 60, 'Decide whether to keep on watchlist, research further, or qualify for pursuit.', $summary, 'Verify source, relationship path, and capacity fit.', 'Jackson may miss early positioning if public funding/work signals are not reviewed.', $owner, 'opportunity', (int)$opportunity['id']]);
        $id = (int)$db->lastInsertId();
        $timeline->execute([$id, 'Real hunt enrichment created package', $summary, $owner]);
        $count++;
    }

    foreach ($db->query('SELECT s.* FROM subcontractors s ORDER BY s.id LIMIT 8')->fetchAll() as $sub) {
        $owner = regionOwner($db, (int)$sub['region_id']);
        $summary = 'Capacity provider prospect has public service evidence but no verified documents, crew counts, or trust score.';
        $package->execute(['Review capacity prospect: ' . $sub['company_name'], 'Capacity', (int)$sub['region_id'], $sub['markets_served'], 70, 75, 68, 'Decide whether to move this prospect into human qualification.', $summary, 'Review website, service fit, coverage, and missing onboarding documents.', 'Capacity gap may remain unresolved if qualified providers are not reviewed.', $owner, 'subcontractor', (int)$sub['id']]);
        $id = (int)$db->lastInsertId();
        $timeline->execute([$id, 'Real hunt enrichment created package', $summary, $owner]);
        $count++;
    }

    foreach ($db->query('SELECT * FROM relationship_creation_signals ORDER BY confidence_score DESC LIMIT 8')->fetchAll() as $target) {
        $owner = regionOwner($db, (int)$target['region_id']);
        $summary = 'Influence target identifies a public role gap, not a verified contact.';
        $package->execute(['Verify influence role: ' . ($target['organization_name'] ?: $target['title']), 'Influence', (int)$target['region_id'], '', (int)$target['confidence_score'], 70, 55, 'Decide whether to manually verify the public person holding this role.', $summary, 'Verify named public contact before creating relationship or outreach.', 'Jackson may lack access to who influences work if role targets remain unmapped.', $owner, 'relationship_creation_signal', (int)$target['id']]);
        $id = (int)$db->lastInsertId();
        $timeline->execute([$id, 'Real hunt enrichment created package', $summary, $owner]);
        $count++;
    }

    return $count;
}

function enrichReviewQueue(PDO $db): int
{
    $stmt = $db->prepare('INSERT INTO data_review_items (review_type, linked_record_type, linked_record_id, region_id, title, issue_summary, severity, status, assigned_owner, recommended_resolution) VALUES ("Data Quality", ?, ?, ?, ?, ?, ?, "Open", ?, ?)');
    $count = 0;
    foreach ($db->query("SELECT * FROM real_hunt_import_records WHERE review_status != 'Verified' OR confidence_score < 75")->fetchAll() as $row) {
        $severity = (int)$row['confidence_score'] < 60 ? 'High' : 'Medium';
        $stmt->execute(['real_hunt_' . $row['dataset'], (int)$row['id'], null, 'Review real hunt enrichment: ' . $row['dataset'] . ' row ' . $row['source_row'], 'Public research needs human review before trusted operating use. source_url=' . $row['source_url'], $severity, 'Admin', 'Verify source, fill missing fields, resolve confidence/review status, then promote only if appropriate.']);
        $count++;
    }
    return $count;
}

function classify(PDO $db, string $type, int $id, string $category, int $regionId, string $reason): void
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO acquisition_classifications (entity_type, entity_id, category, region_id, reason) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$type, $id, $category, $regionId, $reason]);
}

function score(PDO $db, string $type, int $id, int $regionId, int $work, int $capacity, int $need, int $influence): void
{
    $priority = max($work, $capacity, $need, $influence);
    $stmt = $db->prepare('INSERT OR REPLACE INTO acquisition_scores (entity_type, entity_id, region_id, work_score, capacity_score, need_score, influence_score, acquisition_priority_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$type, $id, $regionId, $work, $capacity, $need, $influence, $priority]);
}

function watch(PDO $db, string $watchType, string $entityType, int $entityId, int $regionId, string $change, string $action): void
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO acquisition_watchlists (watchlist_type, entity_type, entity_id, region_id, recent_change, escalation_level, recommended_action, status) VALUES (?, ?, ?, ?, ?, ?, ?, "Monitoring")');
    $stmt->execute([$watchType, $entityType, $entityId, $regionId, $change, 'Medium', $action]);
}

function provenance(PDO $db, string $type, string $sourceType, int $sourceId, ?string $enrichedType, ?int $enrichedId, int $confidence, string $reviewStatus, string $sourceUrl, string $notes): void
{
    $stmt = $db->prepare('INSERT INTO real_hunt_enrichment_records (enrichment_type, source_record_type, source_record_id, enriched_record_type, enriched_record_id, confidence_score, review_status, source_url, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$type, $sourceType, $sourceId, $enrichedType, $enrichedId, $confidence, $reviewStatus, $sourceUrl, $notes]);
}

function signalCount(PDO $db, string $name, int $regionId): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM signals WHERE region_id = ? AND (organization_name LIKE ? OR title LIKE ? OR description LIKE ?)');
    $like = '%' . strtok($name, ' ') . '%';
    $stmt->execute([$regionId, $like, $like, $like]);
    return (int)$stmt->fetchColumn();
}

function regionOwner(PDO $db, int $regionId): string
{
    $stmt = $db->prepare('SELECT owner FROM regions WHERE id = ?');
    $stmt->execute([$regionId]);
    $owner = (string)$stmt->fetchColumn();
    return $owner ?: 'Admin';
}

function sourceFromNotes(string $notes): string
{
    if (preg_match('/source_url=([^;]+)/', $notes, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function reviewStatusFromNotes(string $notes): string
{
    if (preg_match('/review_status=([^;]+)/', $notes, $matches)) {
        return trim($matches[1]);
    }
    return 'Pending Review';
}
