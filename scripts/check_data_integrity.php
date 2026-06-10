<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$checks = [];
$productionDataMode = in_array(strtolower((string)(getenv('JIP_SEED_MODE') ?: '')), ['production', 'minimal'], true)
    || file_exists(__DIR__ . '/../storage/production_data_mode');

$add = function (string $level, string $label, int $count, string $detail = '') use (&$checks): void {
    $checks[] = compact('level', 'label', 'count', 'detail');
};

$count = function (string $sql) use ($db): int {
    return (int)$db->query($sql)->fetchColumn();
};

$tables = ['signals','signal_quality_profiles','acquisition_targets','capacity_profiles','capacity_trust_scores','subcontractors','subcontractor_compliance_profiles','relationship_intelligence_profiles','relationship_objectives','strategic_alignment_profiles','pursuit_scores','opportunity_pursuit_decisions','opportunity_watchlists','preconstruction_profiles','bid_decisions','capacity_consumption_plans','subcontractor_fit_plans','margin_forecasts','preconstruction_risks','scenario_plans','project_packages','erp_readiness_profiles','capacity_allocation_snapshots','relationship_context_snapshots','preconstruction_snapshots','integration_statuses','outcome_records','relationship_performance_profiles','subcontractor_performance_profiles','hunt_performance_profiles','demand_performance_profiles','pursuit_performance_profiles','regional_learning_profiles','lessons_learned','learning_insights','work_intelligence','capacity_intelligence','need_intelligence','influence_intelligence','acquisition_classifications','acquisition_scores','acquisition_watchlists','communication_records','network_relationships','forecast_records','ownership_assignments','ownership_change_log','owner_profiles','responsibility_roles','owner_responsibility_roles','region_ownership_defaults','strategic_accounts','workforce_profiles','competitor_profiles','operating_rhythms','review_instances','rhythm_compliance_scores','workforce_movements','workforce_influence_relationships','workforce_forecasts','competitor_movements','competitive_pressure_indexes','competitor_forecasts','win_loss_intelligence','audit_logs','password_reset_tokens','data_quality_issues','connectors','connector_run_logs','enrichment_sources','scheduled_enrichment_runs','search_query_registry','manual_research_tasks','source_confidence_rules','intelligence_growth_targets','intelligence_streams','organization_classifications','contact_role_access_profiles','source_evidence_records','intelligence_stream_import_records','subcontractor_onboarding','workforce_onboarding','strategic_account_onboarding','market_onboarding','onboarding_reviews','onboarding_documents','onboarding_intake_links','data_review_items','operator_pilot_feedback','recommendation_tuning_rules','erp_contract_validation_items','regional_dominance_scores','strategic_recommendations','executive_packages','work_packages','capacity_packages','need_packages','influence_packages','decision_packages','package_timeline_events','executive_briefs','package_actions','platform_health_checks','operator_modes','market_intelligence_sources','market_intelligence_profiles','market_readiness_scores','recommended_actions','daily_actions','outreach_intelligence','outreach_scripts','outreach_discovery_questions','outreach_outcomes','content_drafts','distribution_plans','channels','activities'];
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
$add('WARN', 'open opportunities without strategic alignment', $count("SELECT COUNT(*) FROM opportunities op LEFT JOIN strategic_alignment_profiles sap ON sap.opportunity_id = op.id WHERE op.stage NOT IN ('Awarded','Lost') AND sap.id IS NULL"));
$add('WARN', 'open opportunities without pursuit decision', $count("SELECT COUNT(*) FROM opportunities op LEFT JOIN opportunity_pursuit_decisions opd ON opd.opportunity_id = op.id WHERE op.stage NOT IN ('Awarded','Lost') AND opd.id IS NULL"));
$add('WARN', 'pursuit decisions without watchlist', $count('SELECT COUNT(*) FROM opportunity_pursuit_decisions opd LEFT JOIN opportunity_watchlists ow ON ow.opportunity_id = opd.opportunity_id WHERE ow.id IS NULL'));
$add('WARN', 'preconstruction profiles without bid decision', $count('SELECT COUNT(*) FROM preconstruction_profiles pp LEFT JOIN bid_decisions bd ON bd.preconstruction_profile_id = pp.id WHERE bd.id IS NULL'));
$add('WARN', 'preconstruction profiles without margin forecast', $count('SELECT COUNT(*) FROM preconstruction_profiles pp LEFT JOIN margin_forecasts mf ON mf.preconstruction_profile_id = pp.id WHERE mf.id IS NULL'));
$add('WARN', 'preconstruction profiles without capacity plan', $count('SELECT COUNT(*) FROM preconstruction_profiles pp LEFT JOIN capacity_consumption_plans ccp ON ccp.preconstruction_profile_id = pp.id WHERE ccp.id IS NULL'));
$add('FAIL', 'outcome records without source module', $count("SELECT COUNT(*) FROM outcome_records WHERE source_module IS NULL OR source_module = ''"));
$add('WARN', 'regions without learning profile', $count('SELECT COUNT(*) FROM regions r LEFT JOIN regional_learning_profiles rlp ON rlp.region_id = r.id WHERE rlp.id IS NULL'));
$add('WARN', 'learning insights without recommended action', $count("SELECT COUNT(*) FROM learning_insights WHERE recommended_action IS NULL OR recommended_action = ''"));
$add('WARN', 'work intelligence without readiness score', $count('SELECT COUNT(*) FROM work_intelligence WHERE work_readiness_score <= 0'));
$add('WARN', 'capacity intelligence without deployable score', $count('SELECT COUNT(*) FROM capacity_intelligence WHERE deployable_capacity_score <= 0'));
$add('WARN', 'need intelligence without need score', $count('SELECT COUNT(*) FROM need_intelligence WHERE need_score <= 0'));
$add('WARN', 'influence intelligence without influence score', $count('SELECT COUNT(*) FROM influence_intelligence WHERE final_influence_score <= 0'));
$add('WARN', 'acquisition scores without category classification', $count('SELECT COUNT(*) FROM acquisition_scores acs WHERE NOT EXISTS (SELECT 1 FROM acquisition_classifications acl WHERE acl.entity_type = acs.entity_type AND acl.entity_id = acs.entity_id)'));
$add('WARN', 'acquisition doctrine recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'Acquisition Command Center'"));
$add('WARN', 'platform health checks missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM platform_health_checks'));
$add('WARN', 'operator modes missing', $count('SELECT CASE WHEN COUNT(*) < 5 THEN 1 ELSE 0 END FROM operator_modes WHERE active = 1'));
$add('WARN', 'market intelligence sources missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM market_intelligence_sources'));
$add('WARN', 'market profiles without readiness score', $count('SELECT COUNT(*) FROM market_intelligence_profiles mip LEFT JOIN market_readiness_scores mrs ON mrs.market_profile_id = mip.id WHERE mrs.id IS NULL'));
$add('WARN', 'market intelligence recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'Market Intelligence Network'"));
$add('WARN', 'project packages missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM project_packages'));
$add('WARN', 'project packages without readiness profile', $count('SELECT COUNT(*) FROM project_packages pp LEFT JOIN erp_readiness_profiles erp ON erp.project_package_id = pp.id WHERE erp.id IS NULL'));
$add('WARN', 'project packages without capacity snapshot', $count('SELECT COUNT(*) FROM project_packages pp LEFT JOIN capacity_allocation_snapshots cas ON cas.project_package_id = pp.id WHERE cas.id IS NULL'));
$add('WARN', 'project packages without relationship snapshot', $count('SELECT COUNT(*) FROM project_packages pp LEFT JOIN relationship_context_snapshots rcs ON rcs.project_package_id = pp.id WHERE rcs.id IS NULL'));
$add('WARN', 'project packages without preconstruction snapshot', $count('SELECT COUNT(*) FROM project_packages pp LEFT JOIN preconstruction_snapshots ps ON ps.project_package_id = pp.id WHERE ps.id IS NULL'));
$add('WARN', 'project packages without integration status', $count('SELECT COUNT(*) FROM project_packages pp LEFT JOIN integration_statuses ist ON ist.project_package_id = pp.id WHERE ist.id IS NULL'));
$add('WARN', 'syncerp integration recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'SyncERP Integration Layer'"));
$add('FAIL', 'outreach records without owner', $count("SELECT COUNT(*) FROM outreach_intelligence WHERE owner IS NULL OR owner = ''"));
$add('WARN', 'outreach records without script', $count('SELECT COUNT(*) FROM outreach_intelligence oi LEFT JOIN outreach_scripts os ON os.outreach_intelligence_id = oi.id WHERE os.id IS NULL'));
$add('WARN', 'outreach scripts without human review flag', $count('SELECT COUNT(*) FROM outreach_scripts WHERE human_review_required != 1'));
$add('WARN', 'content drafts without review status', $count("SELECT COUNT(*) FROM content_drafts WHERE review_status IS NULL OR review_status = ''"));
$add('FAIL', 'distribution plans without channel', $count('SELECT COUNT(*) FROM distribution_plans WHERE channel_id IS NULL OR channel_id NOT IN (SELECT id FROM channels)'));
$add('WARN', 'communication records missing owner', $count("SELECT COUNT(*) FROM communication_records WHERE owner IS NULL OR owner = ''"));
$add('WARN', 'communication drafts without human review', $count("SELECT COUNT(*) FROM communication_records WHERE communication_type IN ('Email Draft','LinkedIn Draft','Text Draft') AND human_review_required != 1"));
$add('WARN', 'network relationships without influence score', $count('SELECT COUNT(*) FROM network_relationships WHERE network_influence_score <= 0'));
$add('WARN', 'forecast records missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM forecast_records'));
$add('WARN', 'ownership assignments missing primary owner', $count("SELECT COUNT(*) FROM ownership_assignments WHERE primary_owner IS NULL OR primary_owner = ''"));
$add('FAIL', 'ownership profiles missing', $count('SELECT CASE WHEN COUNT(*) < 5 THEN 1 ELSE 0 END FROM owner_profiles WHERE active = 1'));
$add('FAIL', 'responsibility roles missing', $count('SELECT CASE WHEN COUNT(*) < 6 THEN 1 ELSE 0 END FROM responsibility_roles WHERE active = 1'));
$add('FAIL', 'region ownership defaults missing', $count('SELECT CASE WHEN COUNT(*) < 4 THEN 1 ELSE 0 END FROM region_ownership_defaults WHERE active = 1'));
$add('WARN', 'active owners without responsibility role', $count('SELECT COUNT(*) FROM owner_profiles op WHERE op.owner_type IN ("person","shared","system") AND op.active = 1 AND NOT EXISTS (SELECT 1 FROM owner_responsibility_roles orr WHERE orr.owner_profile_id = op.id AND orr.active = 1)'));
$add('WARN', 'strategic accounts missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM strategic_accounts'));
$add('WARN', 'workforce profiles missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM workforce_profiles'));
$add('WARN', 'competitor profiles missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM competitor_profiles'));
$add('WARN', 'strategic account intelligence recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'Strategic Workforce Competitive Intelligence'"));
$add('WARN', 'operating rhythms missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM operating_rhythms WHERE active = 1'));
$add('WARN', 'review instances missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM review_instances'));
$add('WARN', 'rhythm compliance scores missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM rhythm_compliance_scores'));
$add('WARN', 'workforce movements missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM workforce_movements'));
$add('WARN', 'workforce forecasts missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM workforce_forecasts'));
$add('WARN', 'competitive pressure index missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM competitive_pressure_indexes'));
$add('WARN', 'competitor forecasts missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM competitor_forecasts'));
$add('WARN', 'win/loss intelligence missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM win_loss_intelligence'));
$add('WARN', 'operational maturity recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'Operational Maturity Engine'"));
$add('WARN', 'subcontractor onboarding missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM subcontractor_onboarding'));
$add('WARN', 'workforce onboarding missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM workforce_onboarding'));
$add('WARN', 'strategic account onboarding missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM strategic_account_onboarding'));
$add('WARN', 'market onboarding missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM market_onboarding'));
$add('WARN', 'onboarding reviews missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM onboarding_reviews'));
$add('WARN', 'onboarding documents missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM onboarding_documents'));
$add('WARN', 'onboarding recommendations missing', $count("SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommended_actions WHERE source_module = 'Onboarding Workspace'"));
$add('WARN', 'data review queue missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM data_review_items'));
$add('WARN', 'data quality issues missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM data_quality_issues'));
$add('WARN', 'connector registry missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM connectors'));
$add('WARN', 'connector run logs missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM connector_run_logs'));
$add('FAIL', 'enrichment sources missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM enrichment_sources WHERE active = 1'));
$add('FAIL', 'search query registry missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM search_query_registry WHERE active = 1'));
$add('FAIL', 'source confidence rules missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM source_confidence_rules'));
$add('FAIL', 'intelligence growth targets missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM intelligence_growth_targets'));
$add('FAIL', 'scheduled enrichment runs without source', $count('SELECT COUNT(*) FROM scheduled_enrichment_runs WHERE enrichment_source_id NOT IN (SELECT id FROM enrichment_sources)'));
$add('FAIL', 'manual research tasks without instructions', $count("SELECT COUNT(*) FROM manual_research_tasks WHERE instructions IS NULL OR instructions = ''"));
$add('FAIL', 'intelligence stream config missing', $count('SELECT CASE WHEN COUNT(*) < 5 THEN 1 ELSE 0 END FROM intelligence_streams WHERE active = 1'));
$add('FAIL', 'organization classifications without organization', $count('SELECT COUNT(*) FROM organization_classifications WHERE organization_id NOT IN (SELECT id FROM organizations)'));
$add('FAIL', 'contact role profiles without contact', $count('SELECT COUNT(*) FROM contact_role_access_profiles WHERE contact_id NOT IN (SELECT id FROM contacts)'));
$add('FAIL', 'stream import contacts without organization', $count('SELECT COUNT(*) FROM intelligence_stream_import_records WHERE contact_id IS NOT NULL AND organization_id IS NULL'));
$add('FAIL', 'stream import opportunities without organization', $count('SELECT COUNT(*) FROM intelligence_stream_import_records WHERE opportunity_id IS NOT NULL AND organization_id IS NULL'));
$add('WARN', 'audit logs missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM audit_logs'));
$add('WARN', 'password reset token metadata missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM password_reset_tokens'));
$add('WARN', 'operator feedback missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM operator_pilot_feedback'));
$add('WARN', 'recommendation tuning rules missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM recommendation_tuning_rules WHERE active = 1'));
$add('WARN', 'recommendation governance columns unavailable', $count("SELECT CASE WHEN COUNT(*) < 4 THEN 1 ELSE 0 END FROM pragma_table_info('recommended_actions') WHERE name IN ('usefulness_score','not_useful_count','suppressed_at','suppression_reason')"));
$add('WARN', 'SyncERP contract validation items missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM erp_contract_validation_items'));
$add('WARN', 'contract validation missing source field', $count("SELECT COUNT(*) FROM erp_contract_validation_items WHERE required_for_handoff = 1 AND (source_record_type IS NULL OR source_record_type = '' OR source_field IS NULL OR source_field = '')"));
$add('WARN', 'regional dominance scores missing', $count('SELECT COUNT(*) FROM regions r LEFT JOIN regional_dominance_scores rds ON rds.region_id = r.id WHERE rds.id IS NULL'));
$add('WARN', 'strategic recommendations missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM strategic_recommendations'));
$add('WARN', 'executive packages missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM executive_packages'));
$add('WARN', 'executive packages without action', $count('SELECT COUNT(*) FROM executive_packages ep WHERE NOT EXISTS (SELECT 1 FROM package_actions pa WHERE pa.executive_package_id = ep.id)'));
$add('WARN', 'executive packages without timeline', $count('SELECT COUNT(*) FROM executive_packages ep WHERE NOT EXISTS (SELECT 1 FROM package_timeline_events pte WHERE pte.executive_package_id = ep.id)'));
$add('WARN', 'executive packages without risk of inaction', $count("SELECT COUNT(*) FROM executive_packages WHERE risk_of_inaction IS NULL OR risk_of_inaction = ''"));
$add('WARN', 'executive briefs missing', $count('SELECT CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END FROM executive_briefs'));

$activityMap = [
    'signal' => 'signals',
    'organization' => 'organizations',
    'contact' => 'contacts',
    'subcontractor' => 'subcontractors',
    'opportunity' => 'opportunities',
    'opportunity_pursuit_decision' => 'opportunity_pursuit_decisions',
    'preconstruction_profile' => 'preconstruction_profiles',
    'preconstruction_risk' => 'preconstruction_risks',
    'recommended_action' => 'recommended_actions',
    'daily_action' => 'daily_actions',
    'outreach_intelligence' => 'outreach_intelligence',
    'acquisition_target' => 'acquisition_targets',
    'hunt_task' => 'hunt_tasks',
    'relationship_action' => 'relationship_actions',
    'content_draft' => 'content_drafts',
    'distribution_plan' => 'distribution_plans',
    'review_instance' => 'review_instances',
    'data_review_item' => 'data_review_items',
    'data_quality_issue' => 'data_quality_issues',
    'subcontractor_onboarding' => 'subcontractor_onboarding',
    'workforce_onboarding' => 'workforce_onboarding',
    'strategic_account_onboarding' => 'strategic_account_onboarding',
    'market_onboarding' => 'market_onboarding',
    'onboarding_review' => 'onboarding_reviews',
    'onboarding_document' => 'onboarding_documents',
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
$emptyBusinessOkInProduction = [
    'signals without quality profile',
    'targets without region',
    'targets without owner',
    'capacity profiles without trust score',
    'subcontractors without compliance profile',
    'relationships without primary objective',
    'open opportunities without strategic alignment',
    'open opportunities without pursuit decision',
    'pursuit decisions without watchlist',
    'preconstruction profiles without bid decision',
    'preconstruction profiles without margin forecast',
    'preconstruction profiles without capacity plan',
    'regions without learning profile',
    'learning insights without recommended action',
    'work intelligence without readiness score',
    'capacity intelligence without deployable score',
    'need intelligence without need score',
    'influence intelligence without influence score',
    'acquisition scores without category classification',
    'acquisition doctrine recommendations missing',
    'market intelligence sources missing',
    'market profiles without readiness score',
    'market intelligence recommendations missing',
    'project packages missing',
    'project packages without readiness profile',
    'project packages without capacity snapshot',
    'project packages without relationship snapshot',
    'project packages without preconstruction snapshot',
    'project packages without integration status',
    'syncerp integration recommendations missing',
    'outreach records without script',
    'content drafts without review status',
    'communication records missing owner',
    'communication drafts without human review',
    'network relationships without influence score',
    'forecast records missing',
    'ownership assignments missing primary owner',
    'strategic accounts missing',
    'workforce profiles missing',
    'competitor profiles missing',
    'strategic account intelligence recommendations missing',
    'operating rhythms missing',
    'review instances missing',
    'rhythm compliance scores missing',
    'workforce movements missing',
    'workforce forecasts missing',
    'competitive pressure index missing',
    'competitor forecasts missing',
    'win/loss intelligence missing',
    'operational maturity recommendations missing',
    'subcontractor onboarding missing',
    'workforce onboarding missing',
    'strategic account onboarding missing',
    'market onboarding missing',
    'onboarding reviews missing',
    'onboarding documents missing',
    'onboarding recommendations missing',
    'data review queue missing',
    'data quality issues missing',
    'connector run logs missing',
    'scheduled enrichment runs without source',
    'manual research tasks without instructions',
    'audit logs missing',
    'password reset token metadata missing',
    'operator feedback missing',
    'regional dominance scores missing',
    'strategic recommendations missing',
    'executive packages missing',
    'executive packages without action',
    'executive packages without timeline',
    'executive packages without risk of inaction',
    'executive briefs missing',
];
foreach ($checks as $check) {
    $active = $check['count'] > 0;
    $level = $active ? $check['level'] : 'PASS';
    if ($productionDataMode && $level === 'WARN' && in_array($check['label'], $emptyBusinessOkInProduction, true)) {
        $level = 'PASS';
    }
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
