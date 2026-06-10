<?php

require __DIR__ . '/../../vendor_autoload.php';

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;
use App\Services\SignalProcessingService;
use App\Services\SignalQualityService;
use App\Services\AcquisitionTargetService;
use App\Services\AcquisitionCommandService;
use App\Services\CapacityGapService;
use App\Services\DemandDistributionService;
use App\Services\DecisionSupportService;
use App\Services\ExecutiveOperatingService;
use App\Services\ExecutivePackagingService;
use App\Services\IntelligenceWarehouseService;
use App\Services\MarketIntelligenceService;
use App\Services\OutreachIntelligenceService;
use App\Services\OnboardingService;
use App\Services\OwnerModelService;
use App\Services\OpportunityPursuitService;
use App\Services\OperationalMaturityService;
use App\Services\PlatformReviewService;
use App\Services\PreconstructionIntelligenceService;
use App\Services\ProjectPackageAssemblyService;
use App\Services\ProductionReadinessService;
use App\Services\StrategicWorkforceCompetitiveService;
use App\Services\SubcontractorAcquisitionService;
use App\Services\RelationshipIntelligenceService;
use App\Services\ScheduledEnrichmentService;

$db = Database::connection();
$db->exec('PRAGMA foreign_keys = OFF');
$db->beginTransaction();
$seedMode = strtolower((string)(getenv('JIP_SEED_MODE') ?: 'production'));
$demoAllowed = in_array($seedMode, ['demo','training'], true);
$productionMarker = __DIR__ . '/../../storage/production_data_mode';
$hasRealData = (int)$db->query("SELECT COALESCE((SELECT COUNT(*) FROM real_hunt_import_records),0) + COALESCE((SELECT COUNT(*) FROM organizations),0) + COALESCE((SELECT COUNT(*) FROM contacts),0) + COALESCE((SELECT COUNT(*) FROM subcontractors),0) + COALESCE((SELECT COUNT(*) FROM opportunities),0)")->fetchColumn();
if ($demoAllowed && (is_file($productionMarker) || $hasRealData > 0) && getenv('JIP_ALLOW_DEMO_SEED') !== 'YES') {
    $db->rollBack();
    $db->exec('PRAGMA foreign_keys = ON');
    fwrite(STDERR, "FAIL demo seed blocked. Existing production/real operating data was detected.\n");
    fwrite(STDERR, "Use JIP_SEED_MODE=production for safe baseline config, or set JIP_ALLOW_DEMO_SEED=YES only on a throwaway local database.\n");
    exit(1);
}

if (in_array($seedMode, ['production', 'minimal'], true)) {
    $db->commit();
    $db->exec('PRAGMA foreign_keys = ON');
    seedProductionBaseline($db);
    echo "Seeded minimal production baseline without deleting operating data: regions, users, capacity targets, operator modes, platform health definitions, connector registry, scheduled enrichment configuration, recommendation governance, and ERP contract validation only. No demo acquisition data was inserted.\n";
    exit;
}

function seedProductionBaseline(PDO $db): void
{
    $regionRows = [
        'National' => ['National', 'National', 'National', 'admin@jacksontelcom.com', '', '', 'National', 'National / Multi-region', 'National', 'Active', 'National layer for organizations, content, signals, and opportunities that span multiple theaters.', 82, 64, 66, 58, 62],
        'Southeast' => ['Southeast', 'Mike', 'Mike', 'mike@jacksontlcom.com', 'Atlanta', 'GA', 'GA, AL, FL, TN, NC, SC', 'GA, AL, FL, TN, NC, SC', 'Tier 1', 'Active', 'Tier 1 growth theater for broadband, aerial, underground, splicing, and restoration capacity.', 76, 58, 61, 64, 54],
        'Great Lakes' => ['Great Lakes', 'Ron', 'Ron', 'ron@jacksontelcom.com', 'Detroit', 'MI', 'MI, OH, IN, WI, IL', 'MI, OH, IN, WI, IL', 'Tier 1', 'Active', 'Tier 1 relationship and opportunity theater across Great Lakes broadband and utility markets.', 72, 55, 63, 60, 52],
        'Southwest' => ['Southwest', 'Mike/Ron Shared', 'Mike/Ron Shared', '', 'Houston', 'TX', 'TX, OK, LA, NM', 'TX, OK, LA, NM', 'Tier 2', 'Expansion', 'Tier 2 Houston-centered capacity and traffic foundation theater.', 38, 34, 28, 32, 26],
    ];
    $regions = [];
    foreach ($regionRows as $key => $row) {
        $existing = $db->prepare('SELECT id FROM regions WHERE name = ? LIMIT 1');
        $existing->execute([$key]);
        $id = (int)$existing->fetchColumn();
        if (!$id) {
            $stmt = $db->prepare('INSERT INTO regions (name, owner, owner_name, owner_email, hub_city, hub_state, states, states_covered, priority_tier, operating_status, strategic_notes, coverage_score, capacity_score, relationship_score, opportunity_score, traffic_score, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute($row);
            $id = (int)$db->lastInsertId();
        }
        $regions[$key] = $id;
    }

    $password = password_hash('password', PASSWORD_DEFAULT);
    $users = [
        ['Admin', 'admin@jacksontelcom.com', 'Admin', null],
        ['Executive', 'executive@jacksontelcom.com', 'Executive', null],
        ['Mike', 'mike@jacksontlcom.com', 'Mike', $regions['Southeast']],
        ['Ron', 'ron@jacksontelcom.com', 'Ron', $regions['Great Lakes']],
        ['Southeast Operator', 'operator@jacksontelcom.com', 'Operator', $regions['Southeast']],
        ['Southeast Viewer', 'viewer@jacksontelcom.com', 'Viewer', $regions['Southeast']],
    ];
    foreach ($users as [$name, $email, $role, $regionId]) {
        $exists = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $db->prepare('INSERT INTO users (name, email, password_hash, role, region_id, must_change_password) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$name, $email, $password, $role, $regionId]);
    }

    (new OwnerModelService())->ensureBaseline($db);
    (new ScheduledEnrichmentService())->ensureBaseline($db);

    $targets = [
        'Southeast' => ['Aerial' => 10, 'Underground' => 6, 'Fiber Splicing' => 5, 'Emergency Restoration' => 3, 'Traffic Control' => 3],
        'Great Lakes' => ['Aerial' => 8, 'Underground' => 5, 'Fiber Splicing' => 4, 'Emergency Restoration' => 3, 'Traffic Control' => 2],
        'Southwest' => ['Aerial' => 8, 'Underground' => 6, 'Fiber Splicing' => 4, 'Emergency Restoration' => 3, 'Traffic Control' => 3],
    ];
    foreach ($targets as $regionName => $services) {
        foreach ($services as $service => $target) {
            $exists = $db->prepare('SELECT id FROM capacity_targets WHERE region_id = ? AND service_type = ? LIMIT 1');
            $exists->execute([$regions[$regionName], $service]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $db->prepare('INSERT INTO capacity_targets (region_id, service_type, target_crews, active) VALUES (?, ?, ?, 1)')
                ->execute([$regions[$regionName], $service, $target]);
        }
    }

    (new PlatformReviewService())->rebuild();

    $connectorExists = $db->prepare('SELECT id FROM connectors WHERE connector_name = ? LIMIT 1');
    $connectorExists->execute(['Official Broadband Source Connector']);
    if (!$connectorExists->fetchColumn()) {
        $db->prepare('INSERT INTO connectors (connector_name, source_type, run_mode, source_url, status, notes, region_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute(['Official Broadband Source Connector', 'Industry News', 'Manual', 'https://broadbandusa.ntia.gov/', 'Ready', 'Production connector registry entry. Imported raw items remain review-gated and must pass Signal Quality.', $regions['National'] ?? null]);
    }

    $tuningRows = [
        ['Executive top five guardrail', '', '', 'All', null, 72, 5, 1, 'Daily Actions remain executive priorities; recommendations can be broader.'],
        ['Southeast capacity priority', 'Capacity / Subcontractor Acquisition', 'Capacity', 'Mike', $regions['Southeast'], 65, 3, 1, 'Keep Southeast capacity blockers visible.'],
        ['Great Lakes relationship priority', 'Relationship & Influence', 'Relationship', 'Ron', $regions['Great Lakes'], 68, 3, 1, 'Keep Great Lakes relationship actions visible.'],
    ];
    foreach ($tuningRows as $row) {
        $exists = $db->prepare('SELECT id FROM recommendation_tuning_rules WHERE rule_name = ? LIMIT 1');
        $exists->execute([$row[0]]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
            ->execute($row);
    }

    $erpRows = [
        ['Customer','customer_name',1,'project_packages','customer_name','Pending','Required customer handoff field.'],
        ['Project','package_name',1,'project_packages','package_name','Pending','Future SyncERP project name.'],
        ['Project','market',1,'project_packages','market','Pending','Confirm market naming with SyncERP import requirements.'],
        ['Capacity','crews_assigned',1,'capacity_allocation_snapshots','crews_assigned','Pending','Confirm total count vs discipline-level assignment.'],
        ['Subcontractors','subcontractors_selected',1,'capacity_allocation_snapshots','subcontractors_selected','Pending','Confirm subcontractor entity matching rules.'],
        ['Margin Forecast','estimated_margin',1,'project_packages','estimated_margin','Pending','Forecast only; not billing/accounting.'],
        ['Risk','risk_assessment',1,'preconstruction_snapshots','risk_assessment','Pending','Confirm risk format for execution handoff.'],
        ['Scenario','scenario_selection',0,'preconstruction_snapshots','scenario_selection','Pending','Optional if SyncERP does not consume scenarios.'],
        ['Relationships','key_contacts',1,'relationship_context_snapshots','key_contacts','Pending','Confirm relationship context handoff format.'],
        ['Package Metadata','package_status',1,'project_packages','package_status','Pending','Readiness status owned by integration layer.'],
    ];
    foreach ($erpRows as $row) {
        $exists = $db->prepare('SELECT id FROM erp_contract_validation_items WHERE contract_area = ? AND field_name = ? LIMIT 1');
        $exists->execute([$row[0], $row[1]]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $db->prepare('INSERT INTO erp_contract_validation_items (contract_area, field_name, required_for_handoff, source_record_type, source_field, validation_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute($row);
    }

    file_put_contents(__DIR__ . '/../../storage/production_data_mode', 'production-seed=' . date('c') . PHP_EOL);
}

foreach (['real_hunt_enrichment_records','real_hunt_import_records','ownership_change_log','audit_logs','password_reset_tokens','connector_run_logs','connectors','data_quality_issues','onboarding_intake_links','onboarding_documents','onboarding_reviews','market_onboarding','strategic_account_onboarding','workforce_onboarding','subcontractor_onboarding','activities','erp_contract_validation_items','recommendation_tuning_rules','operator_pilot_feedback','data_review_items','win_loss_intelligence','competitor_forecasts','competitive_pressure_indexes','competitor_movements','workforce_forecasts','workforce_influence_relationships','workforce_movements','rhythm_compliance_scores','review_instances','operating_rhythms','package_actions','executive_briefs','package_timeline_events','decision_packages','influence_packages','need_packages','capacity_packages','work_packages','executive_packages','competitor_profiles','workforce_profiles','communication_records','strategic_recommendations','regional_dominance_scores','strategic_accounts','ownership_assignments','forecast_records','network_relationships','integration_statuses','preconstruction_snapshots','relationship_context_snapshots','capacity_allocation_snapshots','erp_readiness_profiles','project_packages','platform_health_checks','operator_modes','market_readiness_scores','market_intelligence_profiles','market_intelligence_sources','acquisition_watchlists','acquisition_scores','acquisition_classifications','influence_intelligence','need_intelligence','capacity_intelligence','work_intelligence','learning_insights','lessons_learned','regional_learning_profiles','pursuit_performance_profiles','demand_performance_profiles','hunt_performance_profiles','subcontractor_performance_profiles','relationship_performance_profiles','outcome_records','outreach_outcomes','outreach_scripts','outreach_discovery_questions','outreach_intelligence','daily_actions','regional_strategy_scorecards','growth_blockers','opportunity_decisions','capacity_recruitment_recommendations','content_decisions','relationship_decisions','scenario_plans','preconstruction_risks','margin_forecasts','subcontractor_fit_plans','capacity_consumption_plans','bid_decisions','preconstruction_profiles','opportunity_watchlists','opportunity_pursuit_decisions','pursuit_scores','strategic_alignment_profiles','recommended_actions','content_attributions','distribution_plans','content_drafts','content_opportunities','demand_signals','channels','relationship_actions','relationship_risks','relationship_wins','influence_roles','relationship_objectives','relationship_creation_signals','relationship_intelligence_profiles','watchlist_items','source_quality_profiles','signal_quality_profiles','signal_accumulation_profiles','hunt_tasks','hunt_targets','playbook_steps','acquisition_playbooks','hunts','subcontractor_network_scores','subcontractor_documents','subcontractor_compliance_profiles','subcontractor_qualification_scorecards','capacity_trust_scores','capacity_equipment','capacity_discipline_counts','capacity_profiles','regional_capacity_targets','acquisition_targets','raw_signal_items','harvester_runs','signal_sources','outreach_sequences','outreach_targets','content_ideas','keywords','intelligence_records','signals','opportunities','subcontractors','contacts','organizations','users','capacity_targets','regions'] as $table) {
    $db->exec("DELETE FROM {$table}");
    $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
}
$db->commit();
$db->exec('PRAGMA foreign_keys = ON');
$db->beginTransaction();

$regionStmt = $db->prepare('INSERT INTO regions (name, owner, owner_name, owner_email, hub_city, hub_state, states, states_covered, priority_tier, operating_status, strategic_notes, coverage_score, capacity_score, relationship_score, opportunity_score, traffic_score, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
$regionRows = [
    'National' => ['National', 'National', 'National', 'admin@jacksontelcom.com', '', '', 'National', 'National / Multi-region', 'National', 'Active', 'National layer for organizations, content, signals, and opportunities that span multiple theaters.', 82, 64, 66, 58, 62],
    'Southeast' => ['Southeast', 'Mike', 'Mike', 'mike@jacksontlcom.com', 'Atlanta', 'GA', 'GA, AL, FL, TN, NC, SC', 'GA, AL, FL, TN, NC, SC', 'Tier 1', 'Active', 'Tier 1 growth theater for broadband, aerial, underground, splicing, and restoration capacity.', 76, 58, 61, 64, 54],
    'Great Lakes' => ['Great Lakes', 'Ron', 'Ron', 'ron@jacksontelcom.com', 'Detroit', 'MI', 'MI, OH, IN, WI, IL', 'MI, OH, IN, WI, IL', 'Tier 1', 'Active', 'Tier 1 relationship and opportunity theater across Great Lakes broadband and utility markets.', 72, 55, 63, 60, 52],
    'Southwest' => ['Southwest', 'Mike/Ron Shared', 'Mike/Ron Shared', '', 'Houston', 'TX', 'TX, OK, LA, NM', 'TX, OK, LA, NM', 'Tier 2', 'Expansion', 'Tier 2 Houston-centered capacity and traffic foundation theater.', 38, 34, 28, 32, 26],
];
$regions = [];
foreach ($regionRows as $key => $row) {
    $regionStmt->execute($row);
    $regions[$key] = (int)$db->lastInsertId();
}

$userStmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, region_id, must_change_password) VALUES (?, ?, ?, ?, ?, 1)');
$password = password_hash('password', PASSWORD_DEFAULT);
$userStmt->execute(['Admin', 'admin@jacksontelcom.com', $password, 'Admin', null]);
$userStmt->execute(['Executive', 'executive@jacksontelcom.com', $password, 'Executive', null]);
$userStmt->execute(['Mike', 'mike@jacksontlcom.com', $password, 'Mike', $regions['Southeast']]);
$userStmt->execute(['Ron', 'ron@jacksontelcom.com', $password, 'Ron', $regions['Great Lakes']]);
$userStmt->execute(['Southeast Operator', 'operator@jacksontelcom.com', $password, 'Operator', $regions['Southeast']]);
$userStmt->execute(['Southeast Viewer', 'viewer@jacksontelcom.com', $password, 'Viewer', $regions['Southeast']]);

$targetStmt = $db->prepare('INSERT INTO capacity_targets (region_id, service_type, target_crews, active) VALUES (?, ?, ?, 1)');
$targets = [
    'Southeast' => ['Aerial' => 10, 'Underground' => 6, 'Fiber Splicing' => 5, 'Emergency Restoration' => 3, 'Traffic Control' => 3],
    'Great Lakes' => ['Aerial' => 8, 'Underground' => 5, 'Fiber Splicing' => 4, 'Emergency Restoration' => 3, 'Traffic Control' => 2],
    'Southwest' => ['Aerial' => 8, 'Underground' => 6, 'Fiber Splicing' => 4, 'Emergency Restoration' => 3, 'Traffic Control' => 3],
];
foreach ($targets as $regionName => $services) {
    foreach ($services as $service => $target) {
        $targetStmt->execute([$regions[$regionName], $service, $target]);
    }
}

if (in_array($seedMode, ['production', 'minimal'], true)) {
    (new PlatformReviewService())->rebuild();
    (new ScheduledEnrichmentService())->ensureBaseline($db);

    $connectorStmt = $db->prepare('INSERT INTO connectors (connector_name, source_type, run_mode, source_url, status, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $connectorStmt->execute(['Official Broadband Source Connector', 'Industry News', 'Manual', 'https://broadbandusa.ntia.gov/', 'Ready', 'Production connector registry entry. Imported raw items remain review-gated and must pass Signal Quality.']);

    $tuningStmt = $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    $tuningStmt->execute(['Executive top five guardrail', '', '', 'All', null, 72, 5, 1, 'Daily Actions remain executive priorities; recommendations can be broader.']);
    $tuningStmt->execute(['Southeast capacity priority', 'Capacity / Subcontractor Acquisition', 'Capacity', 'Mike', $regions['Southeast'], 65, 3, 1, 'Keep Southeast capacity blockers visible during operations.']);
    $tuningStmt->execute(['Great Lakes relationship priority', 'Relationship & Influence', 'Relationship', 'Ron', $regions['Great Lakes'], 68, 3, 1, 'Keep Great Lakes relationship actions visible during operations.']);

    $erpStmt = $db->prepare('INSERT INTO erp_contract_validation_items (contract_area, field_name, required_for_handoff, source_record_type, source_field, validation_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ([
        ['Customer','customer_name',1,'project_packages','customer_name','Pending','Required customer handoff field.'],
        ['Project','package_name',1,'project_packages','package_name','Pending','Future SyncERP project name.'],
        ['Project','market',1,'project_packages','market','Pending','Confirm market naming with SyncERP import requirements.'],
        ['Capacity','crews_assigned',1,'capacity_allocation_snapshots','crews_assigned','Pending','Confirm total count vs discipline-level assignment.'],
        ['Subcontractors','subcontractors_selected',1,'capacity_allocation_snapshots','subcontractors_selected','Pending','Confirm subcontractor entity matching rules.'],
        ['Margin Forecast','estimated_margin',1,'project_packages','estimated_margin','Pending','Forecast only; not billing/accounting.'],
        ['Risk','risk_assessment',1,'preconstruction_snapshots','risk_assessment','Pending','Confirm risk format for execution handoff.'],
        ['Scenario','scenario_selection',0,'preconstruction_snapshots','scenario_selection','Pending','Optional if SyncERP does not consume scenarios.'],
        ['Relationships','key_contacts',1,'relationship_context_snapshots','key_contacts','Pending','Confirm relationship context handoff format.'],
        ['Package Metadata','package_status',1,'project_packages','package_status','Pending','Readiness status owned by integration layer.'],
    ] as $row) {
        $erpStmt->execute($row);
    }

    $db->commit();
    $marker = __DIR__ . '/../../storage/production_data_mode';
    file_put_contents($marker, 'production-seed=' . date('c') . PHP_EOL);
    echo "Seeded minimal production baseline: regions, users, capacity targets, operator modes, platform health definitions, connector registry, scheduled enrichment configuration, recommendation governance, and ERP contract validation only. No demo acquisition data was inserted.\n";
    exit;
}

echo "Seeding demo acquisition data for development/operator training. Use JIP_SEED_MODE=production for a minimal production baseline.\n";

$keywordStmt = $db->prepare('INSERT INTO keywords (keyword, intent_type, region_id, state, city, priority, current_rank, target_rank, search_intent_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$contentStmt = $db->prepare('INSERT INTO content_ideas (title, content_type, region_id, target_keyword, audience, status, recommended_channel, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$outreachStmt = $db->prepare('INSERT INTO outreach_targets (name, organization, target_type, region_id, state, source, status, recommended_message, next_action, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$signalStmt = $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, city, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, recommended_next_action, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

$regionData = [
    'National' => ['state' => '', 'city' => '', 'owner' => 'Unassigned', 'keywords' => ['telecom construction subcontractors nationwide','fiber construction contractor national','broadband infrastructure contractor USA','national fiber splicing subcontractor','telecom aerial construction crews','underground utility contractor national','emergency fiber restoration crews','prime contractor telecom capacity','utility fiber construction partner','telecom workforce recruiting'], 'audience' => 'Prime Contractors'],
    'Southeast' => ['state' => 'GA', 'city' => 'Atlanta', 'owner' => 'Mike', 'keywords' => ['fiber construction contractor Georgia','aerial fiber subcontractor Atlanta','underground telecom contractor Georgia','Comcast expansion Georgia','fiber splicing contractor Florida','telecom subcontractor Tennessee','municipal fiber North Carolina','bucket truck crews Alabama','emergency fiber restoration South Carolina','telecom workforce recruiting Southeast'], 'audience' => 'Subcontractors'],
    'Great Lakes' => ['state' => 'MI', 'city' => 'Detroit', 'owner' => 'Ron', 'keywords' => ['fiber splicing contractor Michigan','municipal fiber Michigan','aerial fiber subcontractor Ohio','underground utility contractor Indiana','broadband grant Wisconsin','telecom subcontractor Illinois','bucket truck crews Michigan','fiber restoration Great Lakes','prime contractor broadband Ohio','telecom workforce recruiting Michigan'], 'audience' => 'Utilities'],
    'Southwest' => ['state' => 'TX', 'city' => 'Houston', 'owner' => 'Unassigned', 'keywords' => ['telecom subcontractor Houston','underground utility contractor Houston','bucket truck for sale Texas','fiber construction contractor Houston TX','aerial fiber subcontractor Texas','broadband grant Oklahoma','fiber splicing contractor Louisiana','telecom equipment seller Houston','municipal fiber New Mexico','telecom workforce recruiting Texas'], 'audience' => 'Subcontractors'],
];

foreach ($regionData as $regionName => $data) {
    foreach ($data['keywords'] as $i => $keyword) {
        $keywordStmt->execute([$keyword, ['Contractor Recruitment','Utility Opportunity','Prime Contractor','Workforce Recruiting','Equipment / Capacity','Local SEO','National SEO'][$i % 7], $regions[$regionName], $data['state'], $data['city'], $i < 3 ? 'High' : 'Medium', $i < 4 ? 0 : 25 + $i, 3, 'Search intent supports acquisition traffic for ' . $regionName . '.', $i < 5 ? 'Targeted' : 'Researching']);
        $contentStmt->execute([
            ($i < 5 ? 'Landing page: ' : 'Content idea: ') . $keyword,
            ['Landing Page','Blog','LinkedIn Post','Email','Service Page','Case Study','VSL Script'][$i % 7],
            $regions[$regionName],
            $i < 5 ? $keyword : '',
            $data['audience'],
            ['Idea','Drafting','Ready','Published','Repurposed'][$i % 5],
            ['Website','LinkedIn','Facebook','Email','YouTube','Direct Outreach'][$i % 6],
            'Demand generation asset for ' . $keyword . '.',
        ]);
        $outreachStmt->execute([
            ucwords(str_replace(['contractor','subcontractor','fiber','telecom'], ['Contractor','Subcontractor','Fiber','Telecom'], explode(' ', $keyword)[0] . ' acquisition target ' . ($i + 1))),
            $regionName . ' Acquisition Source ' . ($i + 1),
            ['Subcontractor','Utility','Prime Contractor','Vendor','Equipment Seller','Workforce Candidate'][$i % 6],
            $regions[$regionName],
            $data['state'],
            ['Google Search','Facebook Marketplace','LinkedIn','Referral','Industry Forum','New Business Filing','Equipment Listing','Manual'][$i % 8],
            ['New','Researched','Ready for Outreach','Contacted','Responded','Converted','Not Fit'][$i % 7],
            'Introduce Jackson Telcom as a broadband infrastructure construction partner with national footprint and regional theater focus.',
            'Research, verify fit, and assign outreach owner.',
            $data['owner'],
        ]);
    }
}

$signalTemplates = [
    ['Capacity', 'Equipment Listing', 'Bucket truck for sale', 'Contact equipment seller and determine whether crews or equipment are available.'],
    ['Opportunity', 'Broadband Grant', 'Broadband grant award', 'Identify awardee, prime, procurement timeline, and capacity requirements.'],
    ['Relationship', 'LinkedIn', 'Construction manager promotion', 'Send relationship outreach and map decision influence.'],
    ['Market', 'Utility Announcement', 'Utility fiber expansion', 'Track market, likely contractors, and bid timing.'],
    ['SEO', 'Google Search', 'Search demand for fiber contractor page', 'Create or improve regional landing page around this search intent.'],
    ['Content', 'YouTube', 'Content angle for telecom subcontractor recruiting', 'Create content asset and route to website/social channel.'],
    ['Outreach', 'New Business Filing', 'New contractor filing', 'Research company and add to outreach target list.'],
    ['Capacity', 'Hiring Activity', 'Underground contractor hiring operators', 'Qualify crew count, service territory, and subcontract availability.'],
    ['Opportunity', 'Industry Forum', 'Municipal fiber discussion', 'Research owner and create opportunity if procurement is active.'],
    ['Outreach', 'Facebook Marketplace', 'Equipment seller contact opportunity', 'Contact seller and determine capacity/recruitment fit.'],
];

foreach ($regionData as $regionName => $data) {
    foreach ($signalTemplates as $i => [$type, $source, $title, $nextAction]) {
        $fullTitle = "{$title} - {$regionName}";
        $description = "{$title} detected for {$regionName}. This is an acquisition signal for telecom construction growth.";
        $payload = [
            'title' => $fullTitle,
            'description' => $description,
            'signal_type' => $type,
            'source_type' => $source,
            'source_url' => '',
            'region_id' => $regions[$regionName],
            'state' => $data['state'],
            'city' => $data['city'],
            'organization_name' => $regionName . ' Signal Source ' . ($i + 1),
            'contact_name' => '',
            'owner' => $data['owner'],
            'status' => ['New','Reviewed','Assigned','New','New'][$i % 5],
            'recommended_next_action' => $nextAction,
            'notes' => 'Seeded acquisition signal for ' . $regionName . '.',
        ];
        $score = SignalScoring::score($payload);
        $created = date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days'));
        $signalStmt->execute([$payload['title'], $payload['description'], $payload['signal_type'], $payload['source_type'], $payload['source_url'], $payload['region_id'], $payload['state'], $payload['city'], $payload['organization_name'], $payload['contact_name'], $score['confidence_score'], $score['impact_score'], $score['priority'], $payload['owner'], $payload['status'], $payload['recommended_next_action'], $payload['notes'], $created, $created]);
    }
}

$qualityExamples = [
    ['Southeast', 'ABC Fiber Services', '', 'Capacity', 'Hiring Activity', 'ABC Fiber hiring fiber splicers', 'Hiring activity for fiber splicers in Georgia.', 'Watch', '-20 days'],
    ['Southeast', 'ABC Fiber Services', '', 'Capacity', 'Equipment Listing', 'ABC Fiber bucket truck listing', 'Equipment listing shows bucket truck and reel trailer movement.', 'Hunt', '-12 days'],
    ['Southeast', 'ABC Fiber Services', '', 'Relationship', 'LinkedIn', 'ABC Fiber new office expansion', 'Office expansion and operations manager activity in Atlanta.', 'Hunt', '-6 days'],
    ['Southeast', 'ABC Fiber Services', '', 'Opportunity', 'Industry News', 'ABC Fiber project award', 'Project award and multiple bucket trucks indicate escalation-worthy subcontractor capacity.', 'Escalate', '-1 days'],
    ['Great Lakes', 'Michigan Municipal Fiber Authority', 'Dana Collins', 'Opportunity', 'Broadband Grant', 'Michigan broadband grant awarded', 'Broadband grant awarded for municipal fiber expansion with procurement path.', 'Escalate', '-2 days'],
    ['Great Lakes', 'Michigan Municipal Fiber Authority', 'Dana Collins', 'Relationship', 'LinkedIn', 'Construction manager promoted Michigan utility', 'Construction manager promoted into OSP leadership role.', 'Hunt', '-14 days'],
    ['Southwest', 'Houston Underground Pros', '', 'Capacity', 'Google Search', 'New telecom contractor Houston underground', 'New telecom contractor discovered in Houston underground utility searches.', 'Hunt', '-8 days'],
    ['Southwest', 'Houston Underground Pros', '', 'Capacity', 'Equipment Listing', 'Directional drill crew listing Houston', 'Directional drill, vacuum trailer, and underground crew capacity listing.', 'Escalate', '-3 days'],
    ['National', 'NorthStar Prime Broadband', 'Marcus Reid', 'Opportunity', 'Industry News', 'Prime contractor award national broadband', 'Prime contractor award creates subcontracting path across multiple regions.', 'Escalate', '-4 days'],
    ['Southeast', 'Southeast Workforce Candidate Pool', 'Field Candidate', 'Outreach', 'LinkedIn', 'Workforce candidate aerial lineman', 'Workforce candidate with aerial lineman and bucket truck experience.', 'Watch', '-5 days'],
    ['National', 'Telecom Marketing Digest', '', 'Market', 'Industry News', 'General telecom news roundup', 'General telecom news and irrelevant marketing content with no acquisition path.', 'Archive', '-1 days'],
    ['Great Lakes', 'Consumer Wireless Blog', '', 'Market', 'Industry News', 'Unrelated announcement about retail phones', 'Unrelated announcement and duplicate content not tied to construction acquisition.', 'Archive', '-1 days'],
];
foreach ($qualityExamples as [$regionName, $org, $contact, $type, $source, $title, $description, $expected, $age]) {
    $payload = [
        'title' => $title,
        'description' => $description,
        'signal_type' => $type,
        'source_type' => $source,
        'source_url' => 'https://example.local/quality/' . sha1($title),
        'region_id' => $regions[$regionName],
        'state' => $regionData[$regionName]['state'] ?? '',
        'city' => $regionData[$regionName]['city'] ?? '',
        'organization_name' => $org,
        'contact_name' => $contact,
        'owner' => $regionData[$regionName]['owner'] ?? 'Admin',
        'status' => 'New',
        'recommended_next_action' => $expected === 'Archive' ? 'Archive as noise unless reinforced by a more specific acquisition signal.' : 'Review quality classification and decide whether to watch, hunt, or escalate.',
        'notes' => 'Seeded Signal Quality example expected to classify near ' . $expected . '.',
    ];
    $score = SignalScoring::score($payload);
    $created = date('Y-m-d H:i:s', strtotime($age));
    $signalStmt->execute([$payload['title'], $payload['description'], $payload['signal_type'], $payload['source_type'], $payload['source_url'], $payload['region_id'], $payload['state'], $payload['city'], $payload['organization_name'], $payload['contact_name'], $score['confidence_score'], $score['impact_score'], $score['priority'], $payload['owner'], $payload['status'], $payload['recommended_next_action'], $payload['notes'], $created, $created]);
}

$sequenceStmt = $db->prepare('INSERT INTO outreach_sequences (name, target_type, region_id, purpose, step_number, channel, message_template, delay_days, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$sequenceTemplates = [
    ['Subcontractor Recruitment', 'Subcontractor', 'Recruit Subcontractor', ['Email','Phone','LinkedIn']],
    ['Equipment Seller Outreach', 'Equipment Seller', 'Equipment Seller Outreach', ['Facebook Message','Phone','SMS']],
    ['Utility Relationship Introduction', 'Utility', 'Build Utility Relationship', ['Email','Phone','LinkedIn']],
    ['Prime Contractor Capacity Introduction', 'Prime Contractor', 'Prime Contractor Outreach', ['Email','LinkedIn','Phone']],
    ['Workforce Candidate Outreach', 'Workforce Candidate', 'Workforce Recruiting', ['LinkedIn','SMS','Phone']],
];
foreach ($sequenceTemplates as [$name, $targetType, $purpose, $channels]) {
    foreach ($channels as $step => $channel) {
        $sequenceStmt->execute([$name, $targetType, $regions['National'], $purpose, $step + 1, $channel, "{$name} step " . ($step + 1) . ": introduce Jackson Telcom and request qualification conversation.", $step * 3, 'Planned']);
    }
}

$sourceStmt = $db->prepare('INSERT INTO signal_sources (name, source_type, region_id, state, city, target_category, collection_method, source_url, search_query, frequency, status, last_run_at, next_run_at, records_found_last_run, records_created_last_run, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$sourceRows = [
    ['Southeast Google - fiber construction contractor Georgia','Google Search','Southeast','GA','Atlanta','SEO','Search Query','','fiber construction contractor Georgia','Weekly','Active'],
    ['Southeast Google - directional boring contractor Florida','Google Search','Southeast','FL','Orlando','Capacity','Search Query','','directional boring contractor Florida','Weekly','Active'],
    ['Southeast Job Board - fiber splicer jobs Atlanta','Job Board','Southeast','GA','Atlanta','Capacity','Automated','','fiber splicer jobs Atlanta','Daily','Active'],
    ['Southeast Equipment - bucket truck Georgia','Equipment Listing','Southeast','GA','Atlanta','Capacity','Semi-Automated','','bucket truck Georgia','Daily','Active'],
    ['Southeast Utility Announcements','Utility Announcement','Southeast','NC','Charlotte','Opportunity','RSS','','municipal fiber North Carolina','Weekly','Active'],
    ['Great Lakes Google - fiber splicing contractor Michigan','Google Search','Great Lakes','MI','Detroit','SEO','Search Query','','fiber splicing contractor Michigan','Weekly','Active'],
    ['Great Lakes Job Board - OSP manager Michigan','Job Board','Great Lakes','MI','Detroit','Relationship','Automated','','OSP manager Michigan','Daily','Active'],
    ['Great Lakes Broadband Grants','Broadband Grant','Great Lakes','MI','Lansing','Opportunity','RSS','','Michigan broadband funding','Weekly','Active'],
    ['Great Lakes Equipment - splicing trailer Michigan','Equipment Listing','Great Lakes','MI','Detroit','Capacity','Semi-Automated','','splicing trailer Michigan','Daily','Active'],
    ['Great Lakes Industry News','Industry News','Great Lakes','OH','Columbus','Market','RSS','','Ohio broadband construction awards','Weekly','Active'],
    ['Southwest Google - telecom subcontractor Houston','Google Search','Southwest','TX','Houston','SEO','Search Query','','telecom subcontractor Houston','Weekly','Active'],
    ['Southwest Equipment - bucket truck Texas','Equipment Listing','Southwest','TX','Houston','Capacity','Semi-Automated','','bucket truck Texas','Daily','Active'],
    ['Southwest Job Board - aerial lineman Houston','Job Board','Southwest','TX','Houston','Capacity','Automated','','aerial lineman Houston','Daily','Active'],
    ['Southwest Broadband Grants','Broadband Grant','Southwest','TX','Austin','Opportunity','RSS','','Texas broadband funding','Weekly','Active'],
    ['Southwest Secretary of State - new utility contractors','Secretary of State','Southwest','TX','Houston','Outreach','Semi-Automated','','new telecom contractor filing Texas','Weekly','Active'],
    ['National Prime Contractor Awards','Prime Contractor Award','National','','','Opportunity','RSS','','telecom prime contractor award','Weekly','Active'],
    ['National Industry News','Industry News','National','','','Market','RSS','','broadband infrastructure construction news','Daily','Active'],
    ['National Broadband Funding','Broadband Grant','National','','','Market','RSS','','BEAD broadband funding awards','Weekly','Active'],
    ['National LinkedIn Leadership Changes','LinkedIn','National','','','Relationship','Semi-Automated','','telecom construction manager promoted','Weekly','Active'],
    ['National Google - telecom subcontractor search','Google Search','National','','','SEO','Search Query','','telecom construction subcontractors nationwide','Monthly','Active'],
    ['Manual Physical - referrals','Referral','National','','','Relationship','Manual Physical','','referrals from field conversations','On Demand','Active'],
    ['Manual Physical - conferences','Conference','National','','','Relationship','Manual Physical','','conference contacts telecom construction','On Demand','Active'],
    ['Industry Forum - OSP contractor mentions','Industry Forum','National','','','Outreach','Semi-Automated','','OSP contractor subcontractor discussion','Weekly','Active'],
    ['Facebook Marketplace - telecom equipment national','Facebook Marketplace','National','','','Capacity','Semi-Automated','','bucket truck splicing trailer telecom','Daily','Active'],
    ['Google Business Profile - local contractor reviews','Google Business Profile','National','','','Outreach','Semi-Automated','','fiber contractor Google Business Profile','Monthly','Active'],
    ['Operations RSS - FCC broadband news feed','Industry News','National','','','Market','RSS','https://www.fcc.gov/news-events/headlines/rss','broadband infrastructure policy and funding','Weekly','Paused'],
];
$sourceIds = [];
foreach ($sourceRows as $row) {
    [$name, $type, $regionName, $state, $city, $category, $method, $url, $query, $frequency, $status] = $row;
    $sourceStmt->execute([$name, $type, $regions[$regionName], $state, $city, $category, $method, $url, $query, $frequency, $status, null, null, 0, 0, 'Seeded acquisition harvesting source.']);
    $sourceIds[] = (int)$db->lastInsertId();
}

$runStmt = $db->prepare('INSERT INTO harvester_runs (signal_source_id, started_at, finished_at, status, records_found, records_created, records_updated, errors_count, summary, raw_payload_text, created_by) VALUES (?, ?, ?, "Completed", 10, 10, 0, 0, ?, ?, "Seeder")');
$rawStmt = $db->prepare('INSERT INTO raw_signal_items (harvester_run_id, signal_source_id, raw_title, raw_description, raw_url, raw_company_name, raw_contact_name, raw_phone, raw_email, raw_location, raw_state, raw_city, raw_source_date, raw_payload_json, processing_status, duplicate_key, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "New", ?, ?)');
$rawTemplates = [
    ['Bucket truck fleet for sale', 'Company selling multiple bucket trucks and reel trailer in target market.', 'Capacity'],
    ['Fiber splicer hiring activity', 'Contractor hiring fiber splicer and aerial lineman crews.', 'Capacity'],
    ['Municipal fiber RFP watch', 'Municipal fiber bid opportunity and RFP discussion detected.', 'Opportunity'],
    ['Broadband grant award notice', 'BEAD broadband grant funding in target region.', 'Opportunity'],
    ['Construction manager promoted', 'OSP construction manager promoted at utility or prime.', 'Relationship'],
    ['Prime contractor award posted', 'Prime contractor award creates subcontracting path.', 'Opportunity'],
    ['Landing page keyword gap', 'SEO keyword search ranking gap for regional landing page.', 'SEO'],
    ['Industry forum contractor mention', 'Subcontractor mentioned in industry forum discussion.', 'Outreach'],
    ['Directional drill crew listing', 'Directional drill and underground utility contractor capacity found.', 'Capacity'],
    ['Utility expansion announcement', 'Utility expansion and municipal fiber market signal.', 'Market'],
];
for ($runIndex = 0; $runIndex < 5; $runIndex++) {
    $sourceId = $sourceIds[$runIndex];
    $sourceMeta = $sourceRows[$runIndex];
    $started = date('Y-m-d H:i:s', strtotime('-' . (5 - $runIndex) . ' days'));
    $runStmt->execute([$sourceId, $started, date('Y-m-d H:i:s', strtotime($started . ' +15 minutes')), 'Seeded completed run with realistic raw acquisition items.', json_encode(['seeded' => true, 'source' => $sourceMeta[0]])]);
    $runId = (int)$db->lastInsertId();
    foreach ($rawTemplates as $itemIndex => [$title, $description, $category]) {
        $state = $sourceMeta[3];
        $city = $sourceMeta[4];
        $rawTitle = $title . ' - ' . $sourceMeta[0] . ' #' . ($itemIndex + 1);
        $rawUrl = 'https://example.local/harvest/' . sha1($rawTitle);
        $duplicateKey = sha1($sourceId . '|' . $rawUrl . '|' . $rawTitle);
        $rawStmt->execute([$runId, $sourceId, $rawTitle, $description, $rawUrl, $sourceMeta[2] . ' ' . $category . ' Source', $category === 'Relationship' ? 'OSP Manager' : '', '', '', trim($city . ' ' . $state), $state, $city, date('Y-m-d'), json_encode(['category' => $category, 'seeded' => true]), $duplicateKey, 'Seeded raw harvested acquisition item.']);
    }
    $db->prepare('UPDATE signal_sources SET last_run_at = ?, next_run_at = ?, records_found_last_run = 10, records_created_last_run = 10 WHERE id = ?')->execute([$started, date('Y-m-d H:i:s', strtotime('+1 week')), $sourceId]);
}

$db->commit();

(new SignalProcessingService())->processNew();
(new SignalQualityService())->rebuild();
(new AcquisitionTargetService())->buildFromSignals();

$targetStmt = $db->prepare('INSERT INTO acquisition_targets (target_name, target_type, source_type, source_url, organization_name, contact_name, email, phone, website, region_id, state, city, owner, acquisition_score, confidence_score, strategic_value_score, urgency_score, capacity_value_score, relationship_value_score, opportunity_value_score, status, priority, reason_to_pursue, recommended_next_action, notes, duplicate_key, next_action_due_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$manualTargets = [
    ['Michigan Fiber Splicing Contractor', 'Subcontractor', 'Google Search', 'Great Lakes', 'MI', 'Detroit', 'Ron', 86, 'Fiber splicing subcontractor target in Michigan for Great Lakes capacity.', 'Research splicing crew count, trailers, OTDR capability, and availability.'],
    ['Frontier Michigan Relationship Target', 'Utility', 'LinkedIn', 'Great Lakes', 'MI', 'Lansing', 'Ron', 84, 'Relationship target tied to Michigan broadband and utility construction activity.', 'Identify construction manager and schedule relationship outreach.'],
    ['Ohio Aerial Bucket Crew Target', 'Subcontractor', 'Equipment Listing', 'Great Lakes', 'OH', 'Columbus', 'Ron', 82, 'Aerial crew and bucket truck capacity target in Ohio.', 'Call to qualify aerial capacity and subcontracting interest.'],
    ['Wisconsin Municipal Fiber Contact', 'Municipality', 'Broadband Grant', 'Great Lakes', 'WI', 'Madison', 'Ron', 78, 'Municipal fiber opportunity target for Wisconsin broadband funding.', 'Research project owner and procurement timing.'],
    ['Indiana Prime Broadband Target', 'Prime Contractor', 'Industry News', 'Great Lakes', 'IN', 'Indianapolis', 'Ron', 80, 'Prime contractor target for Indiana broadband construction packages.', 'Identify subcontracting path and field operations contact.'],
    ['Houston Underground Utility Contractor', 'Subcontractor', 'Google Search', 'Southwest', 'TX', 'Houston', 'Future Southwest Owner', 88, 'Houston underground utility contractor target for Southwest capacity foundation.', 'Research boring crews, insurance status, and telecom project history.'],
    ['Texas Bucket Truck Seller', 'Equipment Seller', 'Facebook Marketplace', 'Southwest', 'TX', 'Houston', 'Future Southwest Owner', 85, 'Equipment seller may be a contractor upgrading, downsizing, or exiting market.', 'Call seller and determine whether crews or equipment are acquisition targets.'],
    ['MasTec Southwest Prime Contractor Target', 'Prime Contractor', 'Prime Contractor Award', 'Southwest', 'TX', 'Dallas', 'Future Southwest Owner', 83, 'Prime contractor target for Southwest subcontracting path.', 'Prepare capacity introduction and identify regional subcontracting contact.'],
    ['Louisiana Fiber Splicing Crew Target', 'Subcontractor', 'Job Board', 'Southwest', 'LA', 'Baton Rouge', 'Future Southwest Owner', 79, 'Fiber splicing workforce and subcontractor target in Louisiana.', 'Qualify splicing crew availability and markets served.'],
    ['Oklahoma Rural Broadband Utility Target', 'Utility', 'Broadband Grant', 'Southwest', 'OK', 'Oklahoma City', 'Future Southwest Owner', 77, 'Utility opportunity target tied to rural broadband funding.', 'Research award recipient and construction decision makers.'],
];
foreach ($manualTargets as [$name, $type, $source, $regionName, $state, $city, $owner, $score, $reason, $next]) {
    $duplicate = sha1(strtolower($name . '||||' . $regions[$regionName] . '|' . $type));
    $targetStmt->execute([$name, $type, $source, '', $name, '', '', '', '', $regions[$regionName], $state, $city, $owner, $score, 72, 78, 74, in_array($type, ['Subcontractor','Equipment Seller'], true) ? 88 : 45, in_array($type, ['Utility','Prime Contractor','Municipality'], true) ? 70 : 36, in_array($type, ['Utility','Prime Contractor','Municipality'], true) ? 84 : 42, 'New', $score >= 85 ? 'High' : 'Medium', $reason, $next, 'Seeded supplemental acquisition hunting target.', $duplicate, date('Y-m-d', strtotime('+2 days'))]);
}

$huntStmt = $db->prepare('INSERT INTO hunts (hunt_name, hunt_type, region_id, owner, objective, target_count_goal, start_date, end_date, status, success_metric, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$huntRows = [
    ['Southeast Aerial Capacity Expansion', 'Capacity Hunt', 'Southeast', 'Mike', 'Build deployable aerial subcontractor and bucket truck capacity across GA, AL, FL, TN, NC, and SC.', 25, 'Qualified aerial subcontractors with verified crew and equipment capacity.', 'Active'],
    ['Southeast Underground Contractor Hunt', 'Capacity Hunt', 'Southeast', 'Mike', 'Recruit underground telecom construction and directional boring capacity for Southeast work.', 20, 'Qualified underground contractors ready for document review.', 'Active'],
    ['Great Lakes Fiber Splicing Capacity Hunt', 'Capacity Hunt', 'Great Lakes', 'Ron', 'Develop fiber splicing subcontractor capacity across MI, OH, IN, WI, and IL.', 20, 'Fiber splicing crews qualified with trailer, OTDR, and availability notes.', 'Active'],
    ['Great Lakes Utility Influence Hunt', 'Influence Hunt', 'Great Lakes', 'Ron', 'Identify utility construction, OSP, procurement, and broadband influence paths.', 15, 'Decision-maker relationships mapped and next action assigned.', 'Active'],
    ['Southwest Houston Underground Contractor Hunt', 'Capacity Hunt', 'Southwest', 'Future Southwest Owner', 'Create the Houston underground contractor foundation for Southwest expansion.', 20, 'Houston-area underground targets qualified for future owner follow-up.', 'Active'],
    ['National Prime Contractor Relationship Hunt', 'Prime Contractor Hunt', 'National', 'Admin', 'Build national prime contractor relationship paths for capacity introductions.', 15, 'Prime contractor relationship targets researched and routed.', 'Active'],
];
$huntIds = [];
foreach ($huntRows as [$name, $type, $regionName, $owner, $objective, $goal, $metric, $status]) {
    $huntStmt->execute([$name, $type, $regions[$regionName], $owner, $objective, $goal, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+30 days')), $status, $metric, 'Seeded hunt for acquisition execution workflow validation.']);
    $huntIds[$name] = (int)$db->lastInsertId();
}

$playbookStmt = $db->prepare('INSERT INTO acquisition_playbooks (playbook_name, playbook_type, target_type, region_id, objective, opening_script, qualification_questions, disqualification_rules, required_documents, conversion_goal, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$playbookRows = [
    'Subcontractor Recruitment' => ['Subcontractor Recruitment', 'Subcontractor', 'Subcontractor', 'Qualify subcontractor capacity, service fit, documents, and readiness.', 'Are you currently taking on additional telecom construction work in this region?', "What services do you self-perform?\nHow many crews can you deploy?\nWhat equipment do you own?\nHow fast can you mobilize?\nDo you carry active COI and W9?", 'Reject if no telecom experience, no insurance path, poor communication, or unavailable capacity.', 'W9, COI, service list, equipment list, references', 'Subcontractor Profile'],
    'Equipment Seller Outreach' => ['Equipment Seller Outreach', 'Equipment Seller', 'Equipment Seller', 'Determine whether equipment seller represents usable capacity, contractor relationship, or acquisition lead.', 'Are you selling because you are upgrading, downsizing, or exiting a market?', "What equipment is available?\nAre you a telecom or utility contractor?\nDo you still have crews?\nAre you open to subcontract work?", 'Reject if equipment is unrelated, seller is broker-only with no contractor insight, or listing is bad data.', 'Equipment photos, VIN/unit details, seller contact verification', 'Outreach Target'],
    'Prime Contractor Introduction' => ['Prime Contractor Introduction', 'Prime Contractor', 'Prime Contractor', 'Open prime contractor capacity conversations and identify subcontracting paths.', 'Jackson Telcom is building deployable telecom construction capacity across your region.', "Who manages subcontractor onboarding?\nWhat capacity is hardest to source?\nWhere are upcoming builds concentrated?\nWhat documentation is required?", 'Disqualify if no active regional work or no path to subcontractor onboarding.', 'Vendor packet requirements, insurance requirements, procurement contact', 'Organization'],
    'Utility Relationship Development' => ['Utility Relationship Development', 'Utility', 'Utility', 'Map utility construction influence and upcoming broadband or fiber needs.', 'We support telecom construction capacity and would like to understand upcoming build needs.', "Who owns OSP construction decisions?\nWhat build programs are active?\nWhich primes are involved?\nWhere are capacity gaps showing up?", 'Disqualify if no regional construction activity and no relationship path.', 'Decision-maker map, procurement process, project notes', 'Contact'],
    'Workforce Recruiting' => ['Workforce Recruiting', 'Workforce Candidate', 'Workforce Candidate', 'Screen field talent for current and future crew formation.', 'Are you open to field telecom construction opportunities with Jackson Telcom?', "What roles have you performed?\nCan you travel?\nDo you have CDL, OSHA, or rescue certifications?\nWhen are you available?", 'Reject if unsafe history, no role fit, or no availability.', 'Application, certifications, driver license details', 'Workforce Candidate'],
    'Vendor Qualification' => ['Vendor Qualification', 'Vendor', 'Vendor', 'Qualify vendors who support telecom construction, safety, equipment, material, or compliance needs.', 'We are building vendor support for telecom construction operations.', "What services or products do you provide?\nWhat regions do you support?\nWhat lead times should we expect?\nCan you support multiple theaters?", 'Reject if no construction relevance, poor coverage, or unreliable response.', 'Vendor profile, insurance if required, pricing or catalog notes', 'Organization'],
];
$playbookIds = [];
foreach ($playbookRows as $key => $row) {
    [$name, $type, $targetType, $objective, $script, $questions, $rules, $docs, $goal] = $row;
    $playbookStmt->execute([$name, $type, $targetType, $regions['National'], $objective, $script, $questions, $rules, $docs, $goal, 'Seeded acquisition playbook. Outreach is prepared only; no messages are sent.']);
    $playbookIds[$key] = (int)$db->lastInsertId();
}

$stepStmt = $db->prepare('INSERT INTO playbook_steps (playbook_id, step_number, step_name, channel, instructions, expected_outcome, delay_days, required_before_next_step, creates_task) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stepRows = [
    'Subcontractor Recruitment' => [
        ['Research company', 'Research', 'Verify services, markets, website/listing quality, and telecom construction fit.', 'Company profile and fit notes completed.', 0, 'Target has credible telecom construction relevance.'],
        ['Call owner', 'Phone', 'Call owner or operations lead and confirm interest in subcontract work.', 'First contact attempted or completed.', 1, 'Phone or direct contact path identified.'],
        ['Verify services and crew count', 'Phone', 'Confirm aerial, underground, splicing, restoration, traffic control, crew count, and equipment.', 'Service and capacity profile captured.', 2, 'Owner confirms self-performed capacity.'],
        ['Request W9 and COI', 'Document Request', 'Request W9, certificate of insurance, and basic subcontractor onboarding documents.', 'Documents requested with due date.', 3, 'Target is possible fit.'],
        ['Schedule qualification meeting', 'Meeting', 'Schedule qualification call or meeting to approve, reject, or park target.', 'Qualification meeting scheduled.', 5, 'Core documents or capacity notes available.'],
    ],
    'Equipment Seller Outreach' => [
        ['Research listing', 'Research', 'Verify equipment type, location, seller identity, and signs of contractor ownership.', 'Listing research complete.', 0, 'Listing appears telecom/utility relevant.'],
        ['Message seller', 'Facebook Message', 'Ask why equipment is being sold and whether the seller performs telecom or utility work.', 'Seller contacted.', 1, 'Direct seller contact available.'],
        ['Call seller', 'Phone', 'Call to qualify whether this is equipment-only, subcontractor capacity, or acquisition lead.', 'Seller fit determined.', 2, 'Seller responds or phone is available.'],
        ['Capture equipment details', 'Email', 'Collect model, condition, photos, location, price, and any crew relationship.', 'Equipment details captured.', 3, 'Seller is credible.'],
        ['Route to target outcome', 'Research', 'Convert to subcontractor, outreach target, vendor, or mark not fit.', 'Outcome recorded.', 4, 'Fit decision is clear.'],
    ],
    'Prime Contractor Introduction' => [
        ['Research active work', 'Research', 'Identify current awards, regional builds, procurement contacts, and subcontractor needs.', 'Prime profile completed.', 0, 'Prime has regional telecom activity.'],
        ['Identify decision path', 'LinkedIn', 'Find construction, procurement, or subcontractor onboarding contacts.', 'Decision path identified.', 1, 'Contact path exists.'],
        ['Send capacity introduction prep', 'Email', 'Prepare capacity introduction and request onboarding conversation. Do not automate send.', 'Introduction prepared.', 2, 'Message reviewed by owner.'],
        ['Call regional contact', 'Phone', 'Call and ask about subcontracting needs, vendor requirements, and upcoming work.', 'Prime conversation attempted.', 3, 'Phone path available.'],
        ['Document onboarding requirements', 'Document Request', 'Capture insurance, safety, compliance, and vendor packet requirements.', 'Requirements documented.', 5, 'Prime confirms onboarding path.'],
    ],
    'Utility Relationship Development' => [
        ['Map utility contacts', 'Research', 'Identify OSP, construction, broadband, procurement, and field operations contacts.', 'Influence map drafted.', 0, 'Utility has regional build activity.'],
        ['Validate build activity', 'Research', 'Confirm active or planned broadband/fiber programs, grants, or utility expansions.', 'Build activity verified.', 1, 'Project or funding signal exists.'],
        ['Relationship introduction prep', 'Email', 'Prepare relationship introduction for the owner. No automated sending.', 'Introduction prepared.', 2, 'Decision contact known.'],
        ['Call construction contact', 'Phone', 'Call to understand current construction needs, primes, and capacity gaps.', 'Relationship conversation attempted.', 4, 'Contact path available.'],
        ['Record influence notes', 'Research', 'Update relationship strength, next action, and possible opportunity path.', 'Influence notes recorded.', 5, 'Conversation or research produced useful intelligence.'],
    ],
    'Workforce Recruiting' => [
        ['Research candidate fit', 'Research', 'Review role history, location, certifications, and travel fit.', 'Candidate fit notes completed.', 0, 'Candidate appears field-relevant.'],
        ['Make first contact', 'Phone', 'Ask about role interest, travel, availability, and safety expectations.', 'First contact attempted.', 1, 'Phone or direct channel exists.'],
        ['Screen experience', 'Phone', 'Screen aerial, underground, splicing, equipment, CDL, OSHA, and restoration experience.', 'Experience captured.', 2, 'Candidate is responsive.'],
        ['Request application', 'Document Request', 'Ask candidate to complete application and provide certifications if applicable.', 'Application requested.', 3, 'Candidate is possible fit.'],
        ['Set hiring outcome', 'Meeting', 'Move candidate to application, future follow-up, or not fit.', 'Recruiting outcome recorded.', 5, 'Application or decision needed.'],
    ],
    'Vendor Qualification' => [
        ['Research vendor capability', 'Research', 'Verify products, services, service area, telecom relevance, and response quality.', 'Vendor profile completed.', 0, 'Vendor appears relevant.'],
        ['Contact vendor', 'Phone', 'Call to verify coverage, lead times, account setup, and support model.', 'Vendor contacted.', 1, 'Contact path available.'],
        ['Request capabilities', 'Email', 'Request capability summary, pricing/catalog, terms, and support area.', 'Capabilities requested.', 3, 'Vendor is possible fit.'],
        ['Review requirements', 'Document Request', 'Collect any insurance, account, or compliance requirements.', 'Requirements captured.', 4, 'Vendor may support operations.'],
        ['Approve or park vendor', 'Research', 'Convert to organization, mark future follow-up, or mark not fit.', 'Vendor outcome recorded.', 5, 'Fit decision is clear.'],
    ],
];
foreach ($stepRows as $playbookName => $steps) {
    foreach ($steps as $index => [$stepName, $channel, $instructions, $expected, $delay, $required]) {
        $stepStmt->execute([$playbookIds[$playbookName], $index + 1, $stepName, $channel, $instructions, $expected, $delay, $required, 1]);
    }
}

$allTargets = $db->query('SELECT at.*, r.name region_name FROM acquisition_targets at LEFT JOIN regions r ON r.id = at.region_id ORDER BY CASE at.priority WHEN "Critical" THEN 1 WHEN "High" THEN 2 WHEN "Medium" THEN 3 ELSE 4 END, at.acquisition_score DESC, at.id ASC LIMIT 50')->fetchAll();
$assignmentStmt = $db->prepare('INSERT INTO hunt_targets (hunt_id, acquisition_target_id, playbook_id, assigned_owner, hunt_status, current_step_id, qualification_score, qualification_result, outcome, outcome_date, outcome_notes, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$taskSeedStmt = $db->prepare('INSERT INTO hunt_tasks (hunt_target_id, acquisition_target_id, task_title, task_type, owner, due_date, status, instructions, outcome_notes, playbook_step_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$activityStmt = $db->prepare('INSERT INTO activities (entity_type, entity_id, region_id, activity_type, title, notes, activity_date, owner) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)');
$huntTargetIds = [];
foreach ($allTargets as $index => $target) {
    $regionName = $target['region_name'] ?: 'National';
    $playbookName = match ($target['target_type']) {
        'Equipment Seller' => 'Equipment Seller Outreach',
        'Prime Contractor' => 'Prime Contractor Introduction',
        'Utility', 'Municipality' => 'Utility Relationship Development',
        'Workforce Candidate' => 'Workforce Recruiting',
        'Vendor' => 'Vendor Qualification',
        default => 'Subcontractor Recruitment',
    };
    $huntName = match (true) {
        $regionName === 'Southeast' && str_contains(strtolower($target['reason_to_pursue'] ?? ''), 'underground') => 'Southeast Underground Contractor Hunt',
        $regionName === 'Southeast' => 'Southeast Aerial Capacity Expansion',
        $regionName === 'Great Lakes' && in_array($target['target_type'], ['Utility','Municipality'], true) => 'Great Lakes Utility Influence Hunt',
        $regionName === 'Great Lakes' => 'Great Lakes Fiber Splicing Capacity Hunt',
        $regionName === 'Southwest' => 'Southwest Houston Underground Contractor Hunt',
        default => 'National Prime Contractor Relationship Hunt',
    };
    $firstStep = $db->query('SELECT * FROM playbook_steps WHERE playbook_id = ' . (int)$playbookIds[$playbookName] . ' ORDER BY step_number LIMIT 1')->fetch();
    $score = min(100, max(25, (int)round(((int)$target['acquisition_score'] * 0.55) + ((int)$target['capacity_value_score'] * 0.15) + ((int)$target['relationship_value_score'] * 0.15) + ((int)$target['opportunity_value_score'] * 0.15))));
    $result = $score >= 80 ? 'Strong Fit' : ($score >= 55 ? 'Possible Fit' : ($score >= 30 ? 'Weak Fit' : 'Not Fit'));
    $status = ['Added','Researching','First Contact','Qualifying','Documents Requested','Meeting Scheduled'][$index % 6];
    $updated = date('Y-m-d H:i:s', strtotime('-' . ($index % 10) . ' days'));
    $assignmentStmt->execute([$huntIds[$huntName], $target['id'], $playbookIds[$playbookName], $target['owner'], $status, $firstStep['id'] ?? null, $score, $result, null, null, null, 'Seeded hunt assignment and scorecard result.', $updated]);
    $huntTargetId = (int)$db->lastInsertId();
    $huntTargetIds[] = [$huntTargetId, $target, $playbookIds[$playbookName]];
    $taskType = match ($firstStep['channel'] ?? 'Research') {
        'Phone' => 'Call',
        'Email' => 'Email',
        'LinkedIn' => 'LinkedIn',
        'Facebook Message' => 'Facebook Message',
        'In Person' => 'Meeting',
        'Document Request' => 'Document Request',
        default => 'Research',
    };
    $taskStatus = $index % 9 === 0 ? 'In Progress' : 'Open';
    $due = date('Y-m-d', strtotime(($index % 7 < 2 ? '-' : '+') . (($index % 5) + 1) . ' days'));
    $taskSeedStmt->execute([$huntTargetId, $target['id'], $firstStep['step_name'] ?? 'Research target', $taskType, $target['owner'], $due, $taskStatus, $firstStep['instructions'] ?? 'Research and qualify target.', '', $firstStep['id'] ?? null]);
    $activityStmt->execute(['hunt_target', $huntTargetId, $target['region_id'], 'Task', 'Target added to hunt', 'Seeded assignment to ' . $huntName . ' using ' . $playbookName . '.', $target['owner']]);
}

foreach (array_slice($huntTargetIds, 0, 25) as [$huntTargetId, $target, $playbookId]) {
    $secondStep = $db->query('SELECT * FROM playbook_steps WHERE playbook_id = ' . (int)$playbookId . ' AND step_number = 2 LIMIT 1')->fetch();
    if (!$secondStep) {
        continue;
    }
    $taskType = match ($secondStep['channel']) {
        'Phone' => 'Call',
        'Email' => 'Email',
        'LinkedIn' => 'LinkedIn',
        'Facebook Message' => 'Facebook Message',
        'In Person' => 'Meeting',
        'Document Request' => 'Document Request',
        default => 'Research',
    };
    $taskSeedStmt->execute([$huntTargetId, $target['id'], $secondStep['step_name'], $taskType, $target['owner'], date('Y-m-d', strtotime('+' . (int)$secondStep['delay_days'] . ' days')), 'Open', $secondStep['instructions'], '', $secondStep['id']]);
}

$capacityProfileStmt = $db->prepare('INSERT INTO capacity_profiles (profile_name, profile_type, region_id, market, state, city, owner, status, primary_mobilization_readiness, max_travel_radius_miles, states_served, markets_served, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$disciplineStmt = $db->prepare('INSERT INTO capacity_discipline_counts (capacity_profile_id, discipline, total_crews, available_now, available_24_hours, available_72_hours, available_1_week, available_2_weeks, available_30_days, available_60_days, booked_count, unknown_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$equipmentStmt = $db->prepare('INSERT INTO capacity_equipment (capacity_profile_id, equipment_type, count, condition, notes) VALUES (?, ?, ?, ?, ?)');
$trustStmt = $db->prepare('INSERT INTO capacity_trust_scores (capacity_profile_id, safety_score, quality_score, communication_score, responsiveness_score, production_score, documentation_score, relationship_history_score, trust_score, trust_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$regionalTargetStmt = $db->prepare('INSERT INTO regional_capacity_targets (region_id, market, discipline, target_crews_now, target_crews_30_days, target_crews_60_days, strategic_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');

$targetDefaults = [
    'Southeast' => [
        'Aerial' => [12, 14, 16], 'Underground' => [7, 9, 11], 'Fiber Splicing' => [7, 9, 10], 'Emergency Restoration' => [4, 5, 6], 'Traffic Control' => [4, 5, 6], 'Directional Boring' => [4, 6, 7], 'Mowing / ROW' => [2, 3, 4], 'Inspection' => [3, 5, 6], 'QC' => [3, 4, 5], 'Engineering' => [2, 3, 4], 'Make Ready' => [3, 4, 5], 'Drop Crews' => [6, 8, 10],
    ],
    'Great Lakes' => [
        'Aerial' => [8, 10, 12], 'Underground' => [5, 7, 8], 'Fiber Splicing' => [5, 6, 8], 'Emergency Restoration' => [3, 4, 5], 'Traffic Control' => [3, 4, 5], 'Directional Boring' => [3, 4, 5], 'Mowing / ROW' => [1, 2, 3], 'Inspection' => [6, 8, 9], 'QC' => [5, 7, 8], 'Engineering' => [2, 3, 4], 'Make Ready' => [4, 5, 6], 'Drop Crews' => [4, 6, 7],
    ],
    'Southwest' => [
        'Aerial' => [6, 8, 9], 'Underground' => [9, 12, 14], 'Fiber Splicing' => [4, 5, 6], 'Emergency Restoration' => [3, 4, 5], 'Traffic Control' => [3, 4, 5], 'Directional Boring' => [8, 10, 12], 'Mowing / ROW' => [2, 3, 4], 'Inspection' => [2, 3, 4], 'QC' => [2, 3, 4], 'Engineering' => [2, 3, 4], 'Make Ready' => [3, 4, 5], 'Drop Crews' => [5, 7, 9],
    ],
    'National' => [
        'Aerial' => [8, 10, 12], 'Underground' => [8, 10, 12], 'Fiber Splicing' => [6, 8, 10], 'Emergency Restoration' => [5, 6, 8], 'Traffic Control' => [4, 5, 6], 'Directional Boring' => [5, 7, 8], 'Mowing / ROW' => [3, 4, 5], 'Inspection' => [5, 7, 8], 'QC' => [5, 7, 8], 'Engineering' => [4, 6, 7], 'Make Ready' => [5, 6, 8], 'Drop Crews' => [8, 10, 12],
    ],
];
foreach ($targetDefaults as $regionName => $disciplineTargets) {
    foreach ($disciplineTargets as $discipline => [$now, $d30, $d60]) {
        $regionalTargetStmt->execute([$regions[$regionName], 'Broadband Infrastructure', $discipline, $now, $d30, $d60, 'Seeded target for Capacity Radar gap calculations in ' . $regionName . '.']);
    }
}

$capacityProfiles = [
    ['National', 'Jackson Telcom National Response Core', 'Internal', 'Admin', 'Strategic Partner', '24 Hours', 1200, ['Emergency Restoration' => [3,2,2,2,3,3], 'Fiber Splicing' => [2,1,1,2,2,2], 'QC' => [2,1,1,2,2,2]], ['Pickup Trucks' => 5, 'Fusion Splicers' => 2, 'Splicing Trailers' => 1], [94,92,88,90,91,86,90]],
    ['National', 'Jackson Telcom Shared Engineering Bench', 'Internal', 'Admin', 'Preferred', '1 Week', 1200, ['Engineering' => [3,1,1,2,3,3], 'Make Ready' => [3,1,1,2,3,3], 'Inspection' => [2,1,1,1,2,2]], ['Pickup Trucks' => 3, 'Trailers' => 1], [88,86,85,84,82,88,84]],
    ['Southeast', 'Jackson Telcom Southeast Field Core', 'Internal', 'Mike', 'Approved', '72 Hours', 500, ['Aerial' => [3,2,2,2,3,3], 'Fiber Splicing' => [1,1,1,1,1,1], 'Emergency Restoration' => [2,1,1,2,2,2]], ['Bucket Trucks' => 3, 'Reel Trailers' => 2, 'Fusion Splicers' => 1], [82,80,78,80,76,74,78]],
    ['Great Lakes', 'Jackson Telcom Great Lakes Field Core', 'Internal', 'Ron', 'Approved', '72 Hours', 450, ['Aerial' => [2,1,1,2,2,2], 'Fiber Splicing' => [1,1,1,1,1,1], 'Inspection' => [2,1,1,2,2,2]], ['Bucket Trucks' => 2, 'Pickup Trucks' => 3, 'Fusion Splicers' => 1], [80,78,79,78,76,72,76]],
    ['Southwest', 'Jackson Telcom Southwest Starter Bench', 'Internal', 'Mike/Ron Shared', 'Qualified', '2 Weeks', 550, ['Underground' => [1,0,0,1,1,1], 'Directional Boring' => [1,0,0,0,1,1], 'Traffic Control' => [1,0,0,1,1,1]], ['Pickup Trucks' => 2, 'Trailers' => 1], [68,66,64,62,60,66,58]],
];

$regionalProviderNames = [
    'Southeast' => ['Peachtree Aerial Fiber', 'Carolina Splice Group', 'Gulf Underground Telecom', 'Volunteer Traffic Control', 'Atlanta Drop Crew Network', 'Blue Ridge Make Ready', 'Florida Restoration Partners', 'Georgia ROW Services', 'Southeast QC Inspectors', 'Metro Bucket Truck Supply'],
    'Great Lakes' => ['Michigan Fiber Splicing', 'Ohio Pole Transfer Group', 'Indiana Inspection Services', 'Lakeshore QC Partners', 'Wisconsin Make Ready', 'Illinois Drop Crew Network', 'Detroit Emergency Fiber', 'Great Lakes Traffic Control', 'Toledo Underground Utility', 'Midwest Bucket Fleet'],
    'Southwest' => ['Houston Underground Pros', 'Texas Directional Boring', 'Bayou Fiber Splicing', 'Oklahoma Drop Crew Network', 'New Mexico Make Ready', 'Southwest Traffic Control', 'Houston Vac Truck Supply', 'Louisiana ROW Services', 'Dallas Inspection Partners', 'Gulf Coast Emergency Fiber'],
    'National' => ['National Storm Restoration Pool', 'Interstate Splicing Bench', 'Prime Vendor Equipment Network', 'National QC Inspection Desk', 'Shared ROW Contractor Pool', 'Multi-State Drop Crew Bench', 'National Directional Drill Exchange', 'Strategic Make Ready Partners'],
];
$disciplineRotations = [
    ['Aerial', 'Fiber Splicing'], ['Underground', 'Directional Boring'], ['Traffic Control'], ['Inspection', 'QC'], ['Make Ready'], ['Drop Crews'], ['Emergency Restoration'], ['Mowing / ROW'], ['Engineering'], ['Aerial', 'Emergency Restoration'],
];
$profileTypes = ['Subcontractor', 'Subcontractor', 'Vendor', 'Specialty Provider', 'Equipment Provider'];
$statuses = ['Prospect', 'Qualified', 'Approved', 'Preferred', 'Strategic Partner'];
$readiness = ['24 Hours', '72 Hours', '1 Week', '2 Weeks', '30 Days', '60 Days'];
foreach ($regionalProviderNames as $regionName => $names) {
    foreach ($names as $i => $name) {
        $owner = match ($regionName) {
            'Southeast' => 'Mike',
            'Great Lakes' => 'Ron',
            'Southwest' => 'Mike/Ron Shared',
            default => 'Admin',
        };
        $profileType = $profileTypes[$i % count($profileTypes)];
        $status = $statuses[$i % count($statuses)];
        $capacityProfiles[] = [$regionName, $name, $profileType, $owner, $status, $readiness[$i % count($readiness)], 150 + ($i * 35), array_fill_keys($disciplineRotations[$i % count($disciplineRotations)], null), [], [55 + (($i * 7) % 42), 54 + (($i * 6) % 44), 52 + (($i * 5) % 45), 50 + (($i * 8) % 45), 51 + (($i * 9) % 44), 48 + (($i * 6) % 45), 50 + (($i * 7) % 45)]];
    }
}

foreach ($capacityProfiles as $index => [$regionName, $name, $profileType, $owner, $status, $ready, $radius, $disciplines, $equipment, $trust]) {
    $regionId = $regions[$regionName];
    $state = $regionData[$regionName]['state'] ?? '';
    $city = $regionData[$regionName]['city'] ?? '';
    $capacityProfileStmt->execute([$name, $profileType, $regionId, 'Broadband Infrastructure', $state, $city, $owner, $status, $ready, $radius, $regionRows[$regionName][6], 'Aerial, underground, splicing, restoration, utility construction', 'Seeded Capacity Radar provider.']);
    $profileId = (int)$db->lastInsertId();
    foreach ($disciplines as $discipline => $preset) {
        if ($preset === null) {
            $total = 1 + (($index + strlen($discipline)) % 4);
            $now = max(0, $total - (($index + strlen($discipline)) % 3));
            $preset = [$total, min($now, $total), min($now + 1, $total), min($now + 1, $total), min($now + 2, $total), $total];
        }
        [$total, $now, $h24, $h72, $w1, $w2] = $preset;
        $disciplineStmt->execute([$profileId, $discipline, $total, $now, $h24, $h72, $w1, $w2, min($total, $w2 + 1), $total, max(0, $total - $now), 0]);
    }
    $equipment = $equipment ?: [
        'Bucket Trucks' => ($index % 3),
        'Directional Drills' => str_contains($name, 'Underground') || str_contains($name, 'Boring') ? 2 : 0,
        'Splicing Trailers' => str_contains($name, 'Splic') ? 2 : 0,
        'Pickup Trucks' => 1 + ($index % 4),
        'Trailers' => 1 + ($index % 2),
    ];
    foreach ($equipment as $type => $count) {
        if ($count > 0) {
            $equipmentStmt->execute([$profileId, $type, $count, ['Fair','Good','Excellent'][$index % 3], 'Seeded equipment count for Capacity Radar.']);
        }
    }
    $trustScore = (int)round(array_sum($trust) / count($trust));
    $trustStmt->execute([$profileId, $trust[0], $trust[1], $trust[2], $trust[3], $trust[4], $trust[5], $trust[6], $trustScore, (new CapacityGapService())->trustCategory($trustScore)]);
}

$subOrgStmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, "Subcontractor", ?, ?, ?, ?, ?, ?, "Prospect")');
$subStmt = $db->prepare('INSERT INTO subcontractors (organization_id, region_id, company_name, legal_name, years_in_business, website, phone, email, owner_name, primary_contact, contact_title, states_served, markets_served, services_offered, crew_count, available_crew_count, aerial_crew_count, underground_crew_count, fiber_splicing_crew_count, directional_boring_crew_count, emergency_restoration_crew_count, traffic_control_crew_count, mowing_row_crew_count, inspection_crew_count, qc_crew_count, engineering_crew_count, make_ready_crew_count, drop_crew_count, bucket_trucks, digger_derricks, directional_drills, splicing_trailers, fusion_splicers, reel_trailers, vac_trucks, insurance_status, w9_status, approval_stage, availability, performance_score, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$scoreStmt = $db->prepare('INSERT INTO subcontractor_qualification_scorecards (subcontractor_id, service_fit, geographic_fit, crew_capacity, mobilization_speed, equipment_availability, insurance_readiness, w9_readiness, communication, experience, safety, qualification_score, qualification_result, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$complianceStmt = $db->prepare('INSERT INTO subcontractor_compliance_profiles (subcontractor_id, document_type, status, expiration_date, review_date, reviewed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
$documentStmt = $db->prepare('INSERT INTO subcontractor_documents (subcontractor_id, file_name, document_type, uploaded_date, expiration_date, status, storage_path, notes) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)');

$candidateNames = [
    'Southeast' => ['Georgia Aerial Fiber', 'Peachtree Underground', 'Carolina Splicing', 'Florida Directional Boring', 'Volunteer Traffic Control', 'Blue Ridge Drop Crews', 'Atlanta Make Ready', 'Gulf Restoration', 'Savannah Fiber Services', 'Birmingham OSP Crews', 'Raleigh QC Inspection', 'Tampa Fiber Build', 'Charleston ROW Services', 'Knoxville Pole Transfer', 'Orlando Splice Partners', 'Macon Bucket Crews', 'Jacksonville Underground'],
    'Great Lakes' => ['Michigan Splice Pros', 'Ohio Aerial Network', 'Indiana Underground Fiber', 'Wisconsin QC Group', 'Illinois Traffic Control', 'Detroit Restoration Crews', 'Toledo Directional Boring', 'Madison Inspection Partners', 'Lansing Make Ready', 'Cleveland Drop Crews', 'Fort Wayne Fiber Services', 'Grand Rapids OSP', 'Milwaukee Splicing', 'Columbus Bucket Crews', 'Chicago ROW Services', 'South Bend Underground'],
    'Southwest' => ['Houston Underground Telecom', 'Texas Directional Drill', 'Bayou Fiber Splicing', 'Oklahoma Aerial Fiber', 'New Mexico Make Ready', 'Dallas Traffic Control', 'Austin Drop Crews', 'Baton Rouge Restoration', 'San Antonio Bucket Crews', 'Tulsa Underground Utility', 'El Paso Fiber Services', 'Fort Worth QC Group', 'Lafayette ROW Services', 'Houston Splice Bench', 'Corpus Aerial Network', 'Albuquerque Inspection', 'Waco Boring Crews'],
];
$docTypes = ['W9','COI','Business License','Safety Program','MSA','NDA'];
$candidateIndex = 0;
foreach ($candidateNames as $regionName => $names) {
    foreach ($names as $name) {
        $candidateIndex++;
        if ($candidateIndex > 50) {
            break 2;
        }
        $regionId = $regions[$regionName];
        $state = $regionData[$regionName]['state'];
        $city = $regionData[$regionName]['city'];
        $owner = $regionData[$regionName]['owner'] === 'Unassigned' ? 'Mike/Ron Shared' : $regionData[$regionName]['owner'];
        $stage = ['Prospect','Researching','Qualified','Documents Requested','Compliance Review','Approved','Preferred','Strategic Partner','Rejected','Inactive'][$candidateIndex % 10];
        $availability = ['Available Now','Available Soon','Limited','Not Available'][$candidateIndex % 4];
        $serviceSet = $disciplineRotations[$candidateIndex % count($disciplineRotations)];
        $crewCounts = array_fill_keys(['Aerial','Underground','Fiber Splicing','Directional Boring','Emergency Restoration','Traffic Control','Mowing / ROW','Inspection','QC','Engineering','Make Ready','Drop Crews'], 0);
        foreach ($serviceSet as $discipline) {
            $crewCounts[$discipline] = 1 + (($candidateIndex + strlen($discipline)) % 4);
        }
        $crewTotal = array_sum($crewCounts);
        $available = in_array($availability, ['Available Now','Available Soon'], true) ? max(1, $crewTotal - ($candidateIndex % 3)) : max(0, (int)floor($crewTotal / 2));
        $bucket = $crewCounts['Aerial'] ? 1 + ($candidateIndex % 3) : 0;
        $drills = $crewCounts['Directional Boring'] || $crewCounts['Underground'] ? ($candidateIndex % 3) : 0;
        $splicingTrailers = $crewCounts['Fiber Splicing'] ? 1 + ($candidateIndex % 2) : 0;
        $fusion = $crewCounts['Fiber Splicing'] ? 1 + ($candidateIndex % 3) : 0;
        $reel = ($crewCounts['Aerial'] || $crewCounts['Underground']) ? 1 : 0;
        $vac = $crewCounts['Directional Boring'] ? 1 : 0;
        $performance = 45 + (($candidateIndex * 7) % 52);
        $subOrgStmt->execute([$name, $regionId, $state, $city, 'https://example.local/' . strtolower(str_replace(' ', '-', $name)), '555-01' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT), 'Seeded subcontractor candidate organization.']);
        $orgId = (int)$db->lastInsertId();
        $subStmt->execute([$orgId, $regionId, $name, $name . ' LLC', 2 + ($candidateIndex % 18), 'https://example.local/' . strtolower(str_replace(' ', '-', $name)), '555-01' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT), 'ops' . $candidateIndex . '@example.local', $name . ' Owner', $name . ' Ops', 'Operations Manager', $regionRows[$regionName][6], 'Broadband, OSP, fiber construction', implode(', ', $serviceSet), $crewTotal, $available, $crewCounts['Aerial'], $crewCounts['Underground'], $crewCounts['Fiber Splicing'], $crewCounts['Directional Boring'], $crewCounts['Emergency Restoration'], $crewCounts['Traffic Control'], $crewCounts['Mowing / ROW'], $crewCounts['Inspection'], $crewCounts['QC'], $crewCounts['Engineering'], $crewCounts['Make Ready'], $crewCounts['Drop Crews'], $bucket, $candidateIndex % 2, $drills, $splicingTrailers, $fusion, $reel, $vac, in_array($stage, ['Approved','Preferred','Strategic Partner'], true) ? 'Approved' : ($candidateIndex % 3 === 0 ? 'Submitted' : 'Missing'), in_array($stage, ['Approved','Preferred','Strategic Partner'], true) ? 'Approved' : ($candidateIndex % 2 === 0 ? 'Submitted' : 'Missing'), $stage, $availability, $performance, 'Seeded subcontractor acquisition candidate.']);
        $subId = (int)$db->lastInsertId();

        $scores = [
            min(10, 5 + count($serviceSet)),
            min(10, 5 + ($candidateIndex % 5)),
            min(10, $crewTotal),
            $availability === 'Available Now' ? 10 : ($availability === 'Available Soon' ? 8 : 5),
            min(10, $bucket + $drills + $splicingTrailers + $fusion + 4),
            in_array($stage, ['Approved','Preferred','Strategic Partner'], true) ? 10 : 5 + ($candidateIndex % 4),
            in_array($stage, ['Approved','Preferred','Strategic Partner'], true) ? 10 : 5 + ($candidateIndex % 4),
            min(10, 5 + ($performance % 6)),
            min(10, 4 + ($candidateIndex % 7)),
            min(10, 5 + ($performance % 5)),
        ];
        $qScore = array_sum($scores);
        $qResult = match (true) {
            $qScore >= 90 => 'Strategic Candidate',
            $qScore >= 78 => 'Preferred Candidate',
            $qScore >= 65 => 'Qualified',
            $qScore >= 45 => 'Weak',
            default => 'Not Fit',
        };
        $scoreStmt->execute([$subId, ...$scores, $qScore, $qResult, 'Seeded qualification scorecard.']);
        foreach ($docTypes as $docIndex => $docType) {
            $status = in_array($stage, ['Approved','Preferred','Strategic Partner'], true) || $docIndex < ($candidateIndex % 6) ? 'Approved' : (['Missing','Requested','Submitted'][$docIndex % 3]);
            $expires = $status === 'Approved' ? date('Y-m-d', strtotime('+' . (45 + $docIndex * 30) . ' days')) : null;
            if ($candidateIndex % 17 === 0 && $docType === 'COI') {
                $status = 'Expired';
                $expires = date('Y-m-d', strtotime('-5 days'));
            }
            $complianceStmt->execute([$subId, $docType, $status, $expires, $status === 'Approved' ? date('Y-m-d') : null, $status === 'Approved' ? 'Seeder' : '', 'Seeded compliance status.']);
            if (in_array($status, ['Submitted','Approved','Expired'], true)) {
                $file = strtolower(str_replace(' ', '-', $name)) . '-' . strtolower(str_replace(' ', '-', $docType)) . '.pdf';
                $documentStmt->execute([$subId, $file, $docType, $expires, $status, 'storage/subcontractor_documents/' . $subId . '/' . $file, 'Seeded document storage record.']);
            }
        }

        $capacityProfileStmt->execute([$name . ' Capacity Profile', 'Subcontractor', $regionId, 'Broadband Infrastructure', $state, $city, $owner, in_array($stage, ['Approved','Preferred','Strategic Partner'], true) ? $stage : 'Qualified', $availability === 'Available Now' ? '72 Hours' : '2 Weeks', 200 + ($candidateIndex * 8), $regionRows[$regionName][6], implode(', ', $serviceSet), 'Linked to subcontractor candidate #' . $subId . '.']);
        $profileId = (int)$db->lastInsertId();
        $db->prepare('UPDATE capacity_profiles SET subcontractor_id = ?, organization_id = ? WHERE id = ?')->execute([$subId, $orgId, $profileId]);
        foreach ($serviceSet as $discipline) {
            $total = $crewCounts[$discipline];
            $now = min($total, $available);
            $disciplineStmt->execute([$profileId, $discipline, $total, $now, min($total, $now), min($total, $now + 1), min($total, $now + 1), min($total, $now + 2), $total, $total, max(0, $total - $now), 0]);
        }
        foreach (['Bucket Trucks' => $bucket, 'Digger Derricks' => ($candidateIndex % 2), 'Directional Drills' => $drills, 'Splicing Trailers' => $splicingTrailers, 'Fusion Splicers' => $fusion, 'Reel Trailers' => $reel, 'Vac Trucks' => $vac] as $equipmentType => $count) {
            if ($count > 0) {
                $equipmentStmt->execute([$profileId, $equipmentType, $count, ['Fair','Good','Excellent'][$candidateIndex % 3], 'Seeded subcontractor equipment.']);
            }
        }
        $trust = [$performance, max(40, $performance - 4), max(40, $performance - 2), max(40, $performance - 3), max(40, $performance - 1), max(40, $performance - 5), max(40, $performance - 2)];
        $trustScore = (int)round(array_sum($trust) / count($trust));
        $trustStmt->execute([$profileId, $trust[0], $trust[1], $trust[2], $trust[3], $trust[4], $trust[5], $trust[6], $trustScore, (new CapacityGapService())->trustCategory($trustScore)]);
    }
}

$relationshipOrgStmt = $db->prepare('INSERT INTO organizations (name, type, region_id, state, city, website, phone, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Active")');
$relationshipContactStmt = $db->prepare('INSERT INTO contacts (first_name, last_name, title, email, phone, organization_id, region_id, relationship_owner, influence_level, relationship_strength, last_contact_date, next_action, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$relationshipGroups = [
    'Southeast' => [
        ['Comcast Southeast Construction', 'Prime Contractor', 'GA', 'Atlanta', ['Caleb Martin','Project Manager','Decision Maker','Warm'], ['Denise Walker','Construction Manager','High','Developing'], ['Andre Phillips','OSP Manager','High','Warm'], ['Natalie Brown','Procurement Manager','Medium','Developing'], ['Victor Hayes','Program Manager','High','Strong']],
        ['Charter Southeast Fiber', 'Utility', 'NC', 'Charlotte', ['Maya Collins','Project Manager','Decision Maker','Strong'], ['Reed Johnson','Construction Manager','High','Warm'], ['Tasha King','OSP Manager','High','Developing'], ['Owen Brooks','Field Supervisor','Medium','Developing'], ['Jill Morgan','Procurement','Medium','Cold']],
        ['Ansco Southeast Operations', 'Prime Contractor', 'GA', 'Norcross', ['Eric Foster','Project Manager','High','Warm'], ['Paula Reed','Subcontractor Coordinator','High','Developing'], ['Shawn Davis','Operations Manager','High','Warm'], ['Monica Lane','Estimator','Medium','Developing'], ['Harold Scott','Executive Director','Decision Maker','Cold']],
        ['Georgia Rural Fiber Cooperative', 'Utility', 'GA', 'Macon', ['Laura Jenkins','Program Manager','High','Developing'], ['Chris Newton','Project Manager','High','Warm'], ['Nina Bell','Procurement Manager','Medium','Developing'], ['Wes Carter','Engineer','Medium','Cold'], ['Jordan Miles','Operations Manager','High','Developing']],
        ['Carolina Broadband Authority', 'Municipality', 'SC', 'Columbia', ['Felicia Price','Project Manager','High','Warm'], ['Tim Owens','Construction Manager','High','Developing'], ['Alicia Stone','Program Manager','Decision Maker','Developing'], ['Cory Long','Engineer','Medium','Cold'], ['Dana Hill','Procurement','Medium','Cold']],
    ],
    'Great Lakes' => [
        ['Frontier Michigan Construction', 'Utility', 'MI', 'Detroit', ['Dana Collins','Project Manager','Decision Maker','Warm'], ['Mark Edison','OSP Manager','High','Strong'], ['Priya Shah','Construction Manager','High','Developing'], ['Ellen Marsh','Procurement Manager','Medium','Developing'], ['Scott Young','Operations Manager','High','Warm']],
        ['Congruex Great Lakes', 'Prime Contractor', 'OH', 'Columbus', ['Marcus Reid','Project Manager','High','Warm'], ['Terry Lawson','Subcontractor Coordinator','High','Developing'], ['Pamela West','Program Manager','Decision Maker','Cold'], ['Jon Keller','Estimator','Medium','Developing'], ['Rachel Ames','Operations Manager','High','Warm']],
        ['Ervin Cable Midwest', 'Prime Contractor', 'IN', 'Indianapolis', ['Devin Gray','Project Manager','High','Developing'], ['Angela Cross','Construction Manager','High','Warm'], ['Brad Ellis','OSP Manager','High','Developing'], ['Mina Torres','Procurement','Medium','Cold'], ['Greg Warren','Executive VP','Decision Maker','Cold']],
        ['Michigan Municipal Fiber Authority', 'Municipality', 'MI', 'Lansing', ['Sharon Lee','Program Manager','Decision Maker','Warm'], ['Peter Grant','Project Manager','High','Developing'], ['Ivy Chen','Engineer','Medium','Developing'], ['Noah Ford','Procurement Manager','Medium','Cold'], ['Lena Brooks','Operations Manager','High','Developing']],
        ['Ohio Rural Electric Broadband', 'Utility', 'OH', 'Akron', ['Keith Miller','Project Manager','High','Warm'], ['Rosa Diaz','Construction Manager','High','Developing'], ['Miles Parker','OSP Manager','High','Cold'], ['Janet Kim','Procurement','Medium','Developing'], ['Alan Price','Field Supervisor','Medium','Warm']],
    ],
    'Southwest' => [
        ['MasTec Southwest Fiber', 'Prime Contractor', 'TX', 'Dallas', ['Jose Ramirez','Project Manager','Decision Maker','Warm'], ['Rebecca Stone','Subcontractor Coordinator','High','Developing'], ['Derek Vaughn','Construction Manager','High','Warm'], ['Sonia Patel','Procurement Manager','Medium','Cold'], ['Will Nash','Operations Manager','High','Developing']],
        ['Houston Utility Broadband Office', 'Utility', 'TX', 'Houston', ['April Turner','Program Manager','Decision Maker','Developing'], ['Evan Moore','Project Manager','High','Warm'], ['Bianca Wells','OSP Manager','High','Developing'], ['Grant Ellis','Engineer','Medium','Cold'], ['Nora Diaz','Procurement','Medium','Cold']],
        ['Texas Rural Fiber Cooperative', 'Utility', 'TX', 'Austin', ['Malik Turner','Project Manager','High','Warm'], ['Samantha Ruiz','Construction Manager','High','Developing'], ['Phillip Shaw','Operations Manager','High','Warm'], ['Grace Morgan','Procurement Manager','Medium','Developing'], ['Eli Harper','Engineer','Medium','Cold']],
        ['Houston Underground Telecom Alliance', 'Subcontractor', 'TX', 'Houston', ['Ramon Garcia','Owner','Decision Maker','Strong'], ['Diana Flores','Operations Manager','High','Warm'], ['Carl Benton','Field Supervisor','Medium','Warm'], ['Melanie Fox','Subcontractor Coordinator','High','Developing'], ['Steve Holt','Estimator','Medium','Cold']],
        ['Louisiana Broadband Build Partners', 'Prime Contractor', 'LA', 'Baton Rouge', ['Trent Boudreaux','Project Manager','High','Developing'], ['Amelia King','Construction Manager','High','Warm'], ['Omar Lewis','OSP Manager','High','Developing'], ['Cynthia Ross','Procurement','Medium','Cold'], ['James Grant','Program Manager','Decision Maker','Developing']],
    ],
];

$relationshipContactIndex = 0;
foreach ($relationshipGroups as $regionName => $groups) {
    foreach ($groups as $group) {
        [$orgName, $orgType, $state, $city] = array_slice($group, 0, 4);
        $people = array_slice($group, 4);
        $relationshipOrgStmt->execute([$orgName, $orgType, $regions[$regionName], $state, $city, 'https://example.local/' . strtolower(str_replace(' ', '-', $orgName)), '555-20' . str_pad((string)$relationshipContactIndex, 2, '0', STR_PAD_LEFT), 'Seeded relationship influence account for ' . $regionName . '.']);
        $orgId = (int)$db->lastInsertId();
        foreach ($people as [$fullName, $title, $influence, $strength]) {
            $relationshipContactIndex++;
            [$first, $last] = explode(' ', $fullName, 2);
            $owner = $regionData[$regionName]['owner'] === 'Unassigned' ? 'Mike/Ron Shared' : $regionData[$regionName]['owner'];
            $lastContact = $relationshipContactIndex % 5 === 0 ? null : date('Y-m-d', strtotime('-' . (($relationshipContactIndex * 11) % 120) . ' days'));
            $next = str_contains($title, 'Project Manager') ? 'Ask what active projects need field capacity or subcontractor support.' : (str_contains($title, 'Procurement') ? 'Map vendor/subcontractor onboarding path.' : 'Confirm influence path and next useful introduction.');
            $relationshipContactStmt->execute([$first, $last, $title, strtolower($first . '.' . $last) . '@example.local', '555-30' . str_pad((string)$relationshipContactIndex, 2, '0', STR_PAD_LEFT), $orgId, $regions[$regionName], $owner, $influence, $strength, $lastContact, $next, 'Seeded relationship asset for influence graph.']);
        }
    }
}

(new RelationshipIntelligenceService())->rebuild();

$profileIds = $db->query('SELECT id FROM relationship_intelligence_profiles ORDER BY relationship_value_score DESC')->fetchAll();
$objectiveTypes = ['Project Access','Prime Access','Utility Access','Market Intelligence','Capacity Access','Future Opportunity'];
foreach (array_slice($profileIds, 0, 25) as $index => $profile) {
    $db->prepare('INSERT INTO relationship_objectives (relationship_profile_id, objective_type, priority, status, notes) VALUES (?, ?, "Secondary", "Active", ?)')->execute([(int)$profile['id'], $objectiveTypes[$index % count($objectiveTypes)], 'Seeded secondary objective to show multi-purpose relationship value.']);
}
foreach (array_slice($profileIds, 0, 25) as $index => $profile) {
    $db->prepare('INSERT INTO relationship_actions (relationship_profile_id, action_type, owner, due_date, status, recommended_script, notes) SELECT id, ?, owner, ?, "Open", ?, "Seeded additional relationship action." FROM relationship_intelligence_profiles WHERE id = ?')->execute([
        ['Call','Meeting','Ask for Work','Ask for Capacity','Ask for Market Intelligence'][$index % 5],
        date('Y-m-d', strtotime('+' . (($index % 7) + 1) . ' days')),
        'Use this relationship to clarify work access, capacity access, or market intelligence.',
        (int)$profile['id'],
    ]);
}

$creationSignalStmt = $db->prepare('INSERT INTO relationship_creation_signals (source, region_id, organization_name, contact_name, title, notes, confidence_score, recommended_next_action, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$creationSources = ['LinkedIn Engagement','Direct Outreach','Conference Attendance','Convention Attendance','Thought Leadership','Cold Call','Website Visit','Content Interaction','Referral','Manual Physical Traffic'];
for ($i = 1; $i <= 50; $i++) {
    $regionName = ['Southeast','Great Lakes','Southwest','National'][$i % 4];
    $source = $creationSources[$i % count($creationSources)];
    $orgName = ($regionName === 'National' ? 'National' : $regionName) . ' Influence Prospect ' . $i;
    $title = ['Project Manager','Construction Manager','OSP Manager','Procurement Manager','Operations Manager'][$i % 5];
    $creationSignalStmt->execute([$source, $regions[$regionName], $orgName, 'Relationship Prospect ' . $i, $title, 'Aggressive relationship creation signal from ' . $source . '.', 50 + (($i * 7) % 48), 'Research current role and create contact if access path is valid.', $i % 6 === 0 ? 'Researched' : 'New']);
}

$channelStmt = $db->prepare('INSERT INTO channels (channel_name, channel_type, audience_type, region_id, quality_score, relationship_generation_score, capacity_generation_score, opportunity_generation_score, quality_category, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$channelRows = [
    ['Jackson Telcom Website - National Capacity Pages','Website','General Industry','National',82,74,86,70,'High Value','Active'],
    ['LinkedIn Broadband Infrastructure Network','LinkedIn','Prime Contractor','National',88,90,50,84,'Elite','Active'],
    ['Georgia Fiber Construction LinkedIn Audience','LinkedIn','Subcontractor','Southeast',78,62,88,52,'High Value','Active'],
    ['Southeast Utility Broadband Forum','Industry Forum','Utility','Southeast',74,82,42,76,'High Value','Testing'],
    ['Carolinas Telecom Construction Facebook Group','Facebook Group','Subcontractor','Southeast',68,48,82,36,'Moderate','Testing'],
    ['Michigan Broadband Expansion Newsletter','Email Newsletter','Utility','Great Lakes',86,84,40,88,'Elite','Active'],
    ['Great Lakes Fiber Splicing Contractor Search','Website','Subcontractor','Great Lakes',80,58,90,48,'High Value','Active'],
    ['Ohio Broadband Prime Contractor LinkedIn','LinkedIn','Prime Contractor','Great Lakes',76,78,46,80,'High Value','Testing'],
    ['Houston Telecom Construction Capacity Page','Website','Subcontractor','Southwest',84,62,92,50,'High Value','Active'],
    ['Texas Utility Construction Forum','Industry Forum','Utility','Southwest',70,76,48,74,'High Value','Testing'],
    ['Southwest Equipment Seller Facebook Marketplace','Facebook Group','Vendor','Southwest',64,42,76,38,'Moderate','Testing'],
    ['National Broadband Funding Publication','Industry Publication','Prime Contractor','National',90,86,42,92,'Elite','Active'],
    ['Telecom Construction YouTube Shorts','YouTube','Workforce','National',58,36,62,28,'Moderate','Testing'],
    ['Reddit Broadband Construction Watch','Reddit','General Industry','National',24,18,12,16,'Noise','Testing'],
    ['Fiber Connect Conference Follow-Up','Conference','General Industry','National',92,94,72,88,'Elite','Active'],
];
foreach ($channelRows as [$name, $type, $audience, $regionName, $quality, $relationship, $capacity, $opportunity, $category, $status]) {
    $channelStmt->execute([$name, $type, $audience, $regions[$regionName], $quality, $relationship, $capacity, $opportunity, $category, $status, 'Seeded demand distribution channel.']);
}

$demandSignalStmt = $db->prepare('INSERT INTO demand_signals (topic, demand_score, trend_direction, region_id, audience, suggested_content, suggested_distribution) VALUES (?, ?, ?, ?, ?, ?, ?)');
$demandRows = [
    ['Georgia Fiber Construction', 88, 'Rising', 'Southeast', 'Prime Contractor', 'Georgia fiber construction capacity partner article', 'Website and LinkedIn prime contractor audience'],
    ['Georgia aerial subcontractor recruitment', 84, 'Rising', 'Southeast', 'Subcontractor', 'Landing page for aerial fiber subcontractors in Georgia', 'Website, LinkedIn, and Facebook group'],
    ['Michigan Broadband Expansion', 92, 'Rising', 'Great Lakes', 'Utility', 'Michigan broadband expansion intelligence report', 'Newsletter and LinkedIn utility audience'],
    ['Michigan fiber splicing contractor opportunities', 87, 'Rising', 'Great Lakes', 'Subcontractor', 'Fiber splicing contractor opportunities in Michigan', 'Website and contractor search distribution'],
    ['Houston Telecom Construction Capacity', 90, 'Rising', 'Southwest', 'Subcontractor', 'Houston telecom construction capacity landing page', 'Website and Southwest groups'],
    ['Texas underground utility contractor demand', 83, 'Rising', 'Southwest', 'Subcontractor', 'Houston underground telecom contractor opportunity post', 'Website and industry forum'],
    ['Prime Contractor Capacity Partner', 86, 'Stable', 'National', 'Prime Contractor', 'Jackson Telcom as broadband construction capacity partner', 'LinkedIn and industry publication'],
    ['Fiber splicing contractor opportunities', 80, 'Rising', 'National', 'Subcontractor', 'National fiber splicing subcontractor opportunity article', 'Website and LinkedIn'],
    ['Broadband grant construction capacity', 89, 'Rising', 'National', 'Utility', 'Broadband grant construction capacity intelligence report', 'Industry publication and email newsletter'],
    ['Telecom workforce field careers', 71, 'Stable', 'National', 'Workforce', 'Field career content for telecom construction crews', 'Website and YouTube'],
];
foreach ($demandRows as [$topic, $score, $trend, $regionName, $audience, $contentSuggestion, $distribution]) {
    $demandSignalStmt->execute([$topic, $score, $trend, $regions[$regionName], $audience, $contentSuggestion, $distribution]);
}

$contentOppStmt = $db->prepare('INSERT INTO content_opportunities (title, content_type, audience, region_id, source_type, strategic_value, expected_capacity_impact, expected_relationship_impact, expected_opportunity_impact, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$contentRows = [
    ['Georgia Fiber Construction Capacity Partner', 'Article', 'Prime Contractor', 'Southeast', 'Demand Trends', 86, 48, 82, 86, 'Draft Needed'],
    ['Aerial Fiber Subcontractor Opportunities in Georgia', 'Landing Page', 'Subcontractor', 'Southeast', 'Capacity Gaps', 84, 92, 58, 44, 'Draft Needed'],
    ['Michigan Broadband Expansion Intelligence Report', 'Regional Intelligence Report', 'Utility', 'Great Lakes', 'Broadband Grants', 91, 44, 88, 92, 'Human Review'],
    ['Fiber Splicing Contractor Opportunities in Michigan', 'Landing Page', 'Subcontractor', 'Great Lakes', 'Demand Trends', 85, 94, 54, 46, 'Draft Needed'],
    ['Houston Telecom Construction Capacity', 'Landing Page', 'Subcontractor', 'Southwest', 'Regional Expansion', 90, 96, 60, 52, 'Draft Needed'],
    ['Prime Contractor Capacity Partner Overview', 'Case Study', 'Prime Contractor', 'National', 'Relationship Trends', 88, 60, 84, 86, 'Human Review'],
    ['Broadband Grant Construction Capacity Report', 'Newsletter', 'Utility', 'National', 'Broadband Grants', 87, 48, 82, 90, 'Draft Ready'],
    ['Fiber Splicing Contractor Opportunity Post', 'LinkedIn Post', 'Subcontractor', 'National', 'Demand Trends', 80, 88, 62, 40, 'Draft Needed'],
];
foreach ($contentRows as [$title, $type, $audience, $regionName, $source, $strategic, $capacity, $relationship, $opportunity, $status]) {
    $contentOppStmt->execute([$title, $type, $audience, $regions[$regionName], $source, $strategic, $capacity, $relationship, $opportunity, $status, 'Seeded content opportunity for closed-loop acquisition flywheel.']);
}

(new DemandDistributionService())->rebuild();

$draftIds = $db->query('SELECT id FROM content_drafts ORDER BY id LIMIT 4')->fetchAll();
foreach ($draftIds as $draft) {
    $db->prepare('UPDATE content_drafts SET review_status = "Review Needed" WHERE id = ?')->execute([(int)$draft['id']]);
}
$plansForAttribution = $db->query('SELECT id, content_id, channel_id FROM distribution_plans ORDER BY audience_match_score DESC LIMIT 8')->fetchAll();
foreach ($plansForAttribution as $index => $plan) {
    $status = $index < 3 ? 'Approved' : ($index < 5 ? 'Scheduled' : 'Planned');
    $db->prepare('UPDATE distribution_plans SET status = ? WHERE id = ?')->execute([$status, (int)$plan['id']]);
    if ($index < 5) {
        $db->prepare('INSERT INTO content_attributions (content_id, channel_id, signals_created, targets_created, relationships_created, subcontractors_created, opportunities_created, attribution_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            (int)$plan['content_id'],
            (int)$plan['channel_id'],
            1 + ($index % 3),
            $index % 2,
            1 + ($index % 2),
            $index % 3 === 0 ? 1 : 0,
            $index % 4 === 0 ? 1 : 0,
            'Seeded attribution example showing content/distribution flywheel output.',
        ]);
    }
}
(new DemandDistributionService())->rebuild();

$findOrg = $db->prepare('SELECT id FROM organizations WHERE name = ? LIMIT 1');
$opportunitySeedStmt = $db->prepare('INSERT INTO opportunities (name, organization_id, region_id, market, opportunity_type, customer_type, funding_source, estimated_value, estimated_margin, probability, stage, capacity_required, decision_makers, next_action, owner, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$opportunityRows = [
    ['Comcast Georgia Backbone Expansion', 'Comcast Southeast Construction', 'Southeast', 'Fiber Backbone Construction', 'Prime Contractor', 'Private Capital', 4200000, 24, 72, 'Qualified', 8, 'Project Manager Caleb Martin; OSP Manager Andre Phillips', 'Call Comcast PM and validate construction package timing.', 'Mike', 'Core fiber backbone expansion across Georgia. Strong strategic fit and relationship path.'],
    ['Charter Carolinas Backbone Upgrade', 'Charter Southeast Fiber', 'Southeast', 'Backbone Expansion', 'Utility', 'Utility Capital Plan', 3600000, 22, 68, 'Pursuit', 7, 'Project Manager Maya Collins; Construction Manager Reed Johnson', 'Confirm capacity requirements and prime/subcontracting path.', 'Mike', 'Core backbone upgrade with aerial, underground, and splicing scope.'],
    ['Georgia Small Commercial Cabling Package', 'Georgia Rural Fiber Cooperative', 'Southeast', 'Structured Cabling', 'Enterprise', 'Private Capital', 95000, 18, 55, 'Intelligence', 2, '', 'Archive unless bundled into strategic backbone access.', 'Mike', 'Small commercial cabling and low voltage scope. Non-strategic distraction.'],
    ['Frontier Michigan Middle Mile Fiber Build', 'Frontier Michigan Construction', 'Great Lakes', 'Middle Mile Fiber', 'Utility', 'BEAD', 5100000, 26, 74, 'Qualified', 9, 'Project Manager Dana Collins; OSP Manager Mark Edison', 'Strengthen Frontier PM relationship and confirm splicing crew requirements.', 'Ron', 'BEAD-funded middle mile fiber build with strong backbone alignment.'],
    ['Michigan Municipal Fiber Ring', 'Michigan Municipal Fiber Authority', 'Great Lakes', 'Metro Fiber', 'Municipality', 'Broadband Grant', 2800000, 21, 62, 'Pursuit', 6, 'Program Manager Sharon Lee; Project Manager Peter Grant', 'Review municipal ring procurement path and decision calendar.', 'Ron', 'Municipal fiber ring supports backbone and metro expansion strategy.'],
    ['Ohio Retail Security Camera Upgrade', 'Ohio Rural Electric Broadband', 'Great Lakes', 'Security Systems', 'Enterprise', 'Maintenance Budget', 140000, 16, 52, 'Intelligence', 1, '', 'Avoid and keep attention on fiber backbone opportunities.', 'Ron', 'Security systems and small low-voltage work. Outside fiber backbone mission.'],
    ['Houston Metro Fiber Backbone Route', 'Houston Utility Broadband Office', 'Southwest', 'Metro Fiber', 'Utility', 'Municipal Bond', 4600000, 23, 58, 'Intelligence', 10, 'Project Manager Evan Moore; OSP Manager Bianca Wells', 'Resolve Southwest underground and boring capacity gap before pursuit commitment.', 'Mike/Ron Shared', 'Strong fiber backbone opportunity, but Southwest capacity fit needs work.'],
    ['Texas BEAD Backbone Project', 'Texas Rural Fiber Cooperative', 'Southwest', 'Long Haul Fiber', 'Utility', 'BEAD', 6200000, 25, 64, 'Qualified', 12, 'Project Manager Malik Turner; Construction Manager Samantha Ruiz', 'Recruit underground and directional boring capacity before proposal decision.', 'Mike/Ron Shared', 'Large BEAD-funded long haul fiber opportunity with capacity gap risk.'],
    ['MasTec Southwest Prime Award Path', 'MasTec Southwest Fiber', 'Southwest', 'Backbone Restoration', 'Prime Contractor', 'Prime Contractor Award', 2400000, 20, 60, 'Pursuit', 5, 'Project Manager Jose Ramirez; Subcontractor Coordinator Rebecca Stone', 'Open subcontracting path and qualify restoration crew needs.', 'Mike/Ron Shared', 'Prime award creates restoration and maintenance path in Southwest.'],
    ['National Emergency Fiber Restoration Bench', 'NorthStar Prime Broadband', 'National', 'Backbone Restoration', 'Prime Contractor', 'Emergency Restoration', 1800000, 28, 70, 'Qualified', 4, 'Marcus Reid', 'Confirm national restoration coverage and theater handoff requirements.', 'Admin', 'National backbone restoration pursuit suited for shared response capacity.'],
    ['Southeast Fiber Testing Support Package', 'Southeast Testing Authority', 'Southeast', 'Fiber Testing', 'Prime Contractor', 'Maintenance Budget', 550000, 17, 48, 'Intelligence', 2, '', 'Monitor fit and only pursue if tied to larger backbone maintenance.', 'Mike', 'Supporting fiber testing work. Useful if connected to larger backbone maintenance, but not enough by itself to consume major capacity.'],
    ['Great Lakes Make Ready Support Work', 'Great Lakes Make Ready Buyer', 'Great Lakes', 'Make Ready', 'Utility', 'Utility Capital Plan', 720000, 18, 50, 'Intelligence', 3, '', 'Pursue selectively if it opens utility access or future fiber work.', 'Ron', 'Supporting make ready work with possible relationship value but moderate direct backbone value.'],
    ['Southwest Traffic Control Standalone Contract', 'Southwest Traffic Control Buyer', 'Southwest', 'Traffic Control', 'Municipality', 'Maintenance Budget', 180000, 12, 42, 'Intelligence', 1, '', 'Monitor only. Do not pursue unless attached to strategic fiber construction.', 'Mike/Ron Shared', 'Adjacent traffic control scope. Potential support value, but not a standalone fiber backbone pursuit.'],
    ['National Home Automation Rollout', 'NorthStar Prime Broadband', 'National', 'Home Automation', 'Enterprise', 'Private Capital', 300000, 15, 45, 'Intelligence', 3, '', 'Avoid. Not fiber backbone construction, maintenance, or restoration.', 'Admin', 'Home automation rollout is non-strategic and should not consume pursuit attention.'],
];
foreach ($opportunityRows as [$name, $orgName, $regionName, $type, $customer, $funding, $value, $margin, $probability, $stage, $capacity, $decisionMakers, $nextAction, $owner, $notes]) {
    $findOrg->execute([$orgName]);
    $orgId = (int)$findOrg->fetchColumn();
    if (!$orgId) {
        $relationshipOrgStmt->execute([$orgName, $customer, $regions[$regionName], $regionData[$regionName]['state'] ?? '', $regionData[$regionName]['city'] ?? '', 'https://example.local/' . strtolower(str_replace(' ', '-', $orgName)), '555-9000', 'Seeded opportunity organization.']);
        $orgId = (int)$db->lastInsertId();
    }
    $opportunitySeedStmt->execute([$name, $orgId, $regions[$regionName], 'Fiber Backbone Infrastructure', $type, $customer, $funding, $value, $margin, $probability, $stage, $capacity, $decisionMakers, $nextAction, $owner, $notes]);
}

(new SubcontractorAcquisitionService())->recalculateAll();
(new CapacityGapService())->recalculateTrustScores();
RecommendationEngine::regenerate();
(new OpportunityPursuitService())->rebuild();
(new PreconstructionIntelligenceService())->rebuild();
(new IntelligenceWarehouseService())->rebuild();
(new AcquisitionCommandService())->rebuild();
(new MarketIntelligenceService())->rebuild();
(new PlatformReviewService())->rebuild();
(new ProjectPackageAssemblyService())->rebuild();
(new ExecutiveOperatingService())->rebuild();
(new StrategicWorkforceCompetitiveService())->rebuild();
(new ExecutivePackagingService())->rebuild();
(new DecisionSupportService())->rebuild();
(new OperationalMaturityService())->rebuild();
(new OutreachIntelligenceService())->rebuild();
(new OnboardingService())->rebuild();

$reviewStmt = $db->prepare('INSERT INTO onboarding_reviews (onboarding_type, onboarding_id, review_type, region_id, status, reviewer, review_notes, follow_up_action, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
$docStmt = $db->prepare('INSERT INTO onboarding_documents (onboarding_type, onboarding_id, region_id, document_type, file_name, status, expires_at, reviewed_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($db->query('SELECT id, region_id FROM subcontractor_onboarding ORDER BY onboarding_score DESC LIMIT 10')->fetchAll() as $index => $row) {
    $reviewStmt->execute(['Subcontractor', (int)$row['id'], $index % 2 === 0 ? 'Compliance Review' : 'Capacity Review', $row['region_id'], $index % 3 === 0 ? 'Needs Information' : 'Pending', 'Seeder', 'Seeded onboarding review for subcontractor readiness.', $index % 2 === 0 ? 'Request COI/W9 and verify crew counts.' : 'Confirm available crews and equipment.',]);
    $docStmt->execute(['Subcontractor', (int)$row['id'], $row['region_id'], ['W9','COI','MSA','Safety Program'][$index % 4], 'seeded_subcontractor_document_' . ((int)$row['id']) . '.pdf', $index % 3 === 0 ? 'Requested' : 'Submitted', date('Y-m-d', strtotime('+90 days')), 'Seeder', 'Seeded onboarding document placeholder.']);
}
foreach ($db->query('SELECT id, region_id FROM workforce_onboarding ORDER BY onboarding_score DESC LIMIT 8')->fetchAll() as $index => $row) {
    $reviewStmt->execute(['Workforce', (int)$row['id'], 'Strategic Review', $row['region_id'], 'Pending', 'Seeder', 'Seeded workforce onboarding review.', 'Schedule interview/evaluation and confirm availability.']);
    $docStmt->execute(['Workforce', (int)$row['id'], $row['region_id'], 'Certifications', 'seeded_workforce_cert_' . ((int)$row['id']) . '.pdf', $index % 2 === 0 ? 'Submitted' : 'Requested', null, 'Seeder', 'Seeded workforce document placeholder.']);
}
foreach ($db->query('SELECT id, region_id FROM strategic_account_onboarding ORDER BY account_readiness_score DESC LIMIT 6')->fetchAll() as $row) {
    $reviewStmt->execute(['Strategic Account', (int)$row['id'], 'Relationship Review', $row['region_id'], 'Pending', 'Seeder', 'Seeded strategic account onboarding review.', 'Complete influence and opportunity mapping.']);
}
foreach ($db->query('SELECT id, region_id FROM market_onboarding ORDER BY market_readiness_score DESC LIMIT 6')->fetchAll() as $row) {
    $reviewStmt->execute(['Market', (int)$row['id'], 'Strategic Review', $row['region_id'], 'Pending', 'Seeder', 'Seeded market onboarding review.', 'Complete utility, prime, capacity, and relationship maps.']);
}
(new OnboardingService())->rebuild();

$outcomeRows = $db->query('SELECT id FROM outreach_intelligence ORDER BY id LIMIT 12')->fetchAll();
foreach ($outcomeRows as $index => $row) {
    $db->prepare('INSERT INTO outreach_outcomes (outreach_intelligence_id, outcome_type, outcome_notes, follow_up_date, created_by) VALUES (?, ?, ?, ?, "Seeder")')->execute([
        (int)$row['id'],
        ['Left Message','Interested','Needs Follow-Up','Meeting Scheduled'][$index % 4],
        'Seeded outreach outcome for operator workflow validation. No message was sent automatically.',
        $index % 3 === 0 ? date('Y-m-d', strtotime('+3 days')) : null,
    ]);
}

$feedbackStmt = $db->prepare('INSERT INTO operator_pilot_feedback (owner, region_id, feedback_area, feedback_summary, friction_score, impact_score, status, recommended_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$feedbackStmt->execute(['Mike', $regions['Southeast'], 'Daily Actions', 'Top actions are useful, but capacity recruiting actions should stay above content review during morning execution.', 38, 84, 'Triaged', 'Tune Southeast capacity actions above demand/content unless demand item is Critical.']);
$feedbackStmt->execute(['Ron', $regions['Great Lakes'], 'Data Quality', 'Relationship records need fast review when a project manager changes company.', 45, 78, 'New', 'Add job-change signals to weekly relationship review.']);
$feedbackStmt->execute(['Admin', $regions['National'], 'Security', 'Operations require clear role access rules before external team members are added.', 30, 90, 'Planned', 'Validate role/region access before operational expansion.']);

$qualityStmt = $db->prepare('INSERT INTO data_quality_issues (issue_type, linked_record_type, linked_record_id, region_id, title, description, severity, assigned_owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$qualityStmt->execute(['Missing Contact Info', 'contact', 1, $regions['Southeast'], 'Comcast Southeast PM missing direct phone', 'Operator should verify the phone number before using this contact for pursuit action.', 'High', 'Mike']);
$qualityStmt->execute(['Source Reliability Concern', 'signal_source', 1, $regions['Great Lakes'], 'Michigan broadband source needs review', 'Confirm source quality before using harvested items for executive decisions.', 'Medium', 'Ron']);
$qualityStmt->execute(['Duplicate Entity', 'organization', 2, $regions['Southwest'], 'Potential duplicate Houston contractor organization', 'Review before creating capacity provider records.', 'Medium', 'Admin']);

$connectorStmt = $db->prepare('INSERT INTO connectors (connector_name, source_type, run_mode, source_url, status, notes) VALUES (?, ?, ?, ?, ?, ?)');
$connectorStmt->execute(['Official Broadband RSS Connector', 'Industry News', 'Manual', 'https://broadbandusa.ntia.gov/', 'Ready', 'First safe connector path. Uses RSS/static-source contract or local fallback rows; all imports require review.']);
$connectorId = (int)$db->lastInsertId();
$db->prepare('INSERT INTO connector_run_logs (connector_id, connector_name, started_at, finished_at, status, imported_count, skipped_count, error_count, review_status) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, "Completed", 2, 0, 0, "Needs Data Review")')
    ->execute([$connectorId, 'Official Broadband RSS Connector']);

$db->prepare('INSERT INTO audit_logs (user_name, role, action, record_type, record_id, outcome, details) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute(['System', 'Admin', 'seed_audit_baseline', 'production_readiness', null, 'Success', 'Seeded audit baseline for operational readiness view.']);
$db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at, requested_ip) VALUES (1, ?, datetime("now", "-1 hour"), datetime("now", "-30 minutes"), "127.0.0.1")')
    ->execute([hash('sha256', 'expired-demo-token')]);

$tuningStmt = $db->prepare('INSERT INTO recommendation_tuning_rules (rule_name, source_module, category, owner_scope, region_id, min_priority_score, max_daily_actions, promote_to_daily_action, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
$tuningStmt->execute(['Executive top five guardrail', '', '', 'All', null, 72, 5, 1, 'Daily Actions should remain executive priorities, not every open recommendation.']);
$tuningStmt->execute(['Southeast capacity priority', 'Capacity / Subcontractor Acquisition', 'Capacity', 'Mike', $regions['Southeast'], 65, 3, 1, 'Keep capacity blockers visible for Mike during operations.']);
$tuningStmt->execute(['Great Lakes relationship priority', 'Relationship & Influence', 'Relationship', 'Ron', $regions['Great Lakes'], 68, 3, 1, 'Keep project manager and utility relationship actions visible for Ron.']);

$erpStmt = $db->prepare('INSERT INTO erp_contract_validation_items (contract_area, field_name, required_for_handoff, source_record_type, source_field, validation_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach ([
    ['Customer','customer_name',1,'project_packages','customer_name','Validated','Required customer handoff field is present in package.'],
    ['Project','package_name',1,'project_packages','package_name','Validated','Project package name maps to future SyncERP project name.'],
    ['Project','market',1,'project_packages','market','Pending','Confirm market naming with SyncERP import requirements.'],
    ['Capacity','crews_assigned',1,'capacity_allocation_snapshots','crews_assigned','Pending','Confirm whether SyncERP expects total count or discipline-level assignment.'],
    ['Subcontractors','subcontractors_selected',1,'capacity_allocation_snapshots','subcontractors_selected','Needs SyncERP Review','Confirm subcontractor entity matching rules before import.'],
    ['Margin Forecast','estimated_margin',1,'project_packages','estimated_margin','Pending','Forecast only; not billing/accounting.'],
    ['Risk','risk_assessment',1,'preconstruction_snapshots','risk_assessment','Pending','Confirm risk format for execution handoff.'],
    ['Scenario','scenario_selection',0,'preconstruction_snapshots','scenario_selection','Pending','Optional if SyncERP does not consume scenarios.'],
    ['Relationships','key_contacts',1,'relationship_context_snapshots','key_contacts','Needs SyncERP Review','Confirm whether relationship context is imported or attached as handoff notes.'],
    ['Package Metadata','package_status',1,'project_packages','package_status','Validated','Readiness status is owned by integration layer.'],
] as $row) {
    $erpStmt->execute($row);
}

(new ProductionReadinessService())->rebuildReviewQueue();

echo "Seeded national footprint, traffic records, harvesting sources, raw items, processed signals, acquisition targets, hunts, playbooks, capacity radar, subcontractor acquisition, relationship influence, demand distribution, decision support, outreach intelligence, executive operating system, operational maturity, production readiness, onboarding readiness, strategic account/workforce/competitive intelligence, executive intelligence packages, and recommendations.\n";
