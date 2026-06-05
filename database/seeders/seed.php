<?php

require __DIR__ . '/../../vendor_autoload.php';

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;
use App\Services\SignalProcessingService;
use App\Services\SignalQualityService;
use App\Services\AcquisitionTargetService;
use App\Services\CapacityGapService;

$db = Database::connection();
$db->beginTransaction();

foreach (['activities','recommended_actions','watchlist_items','source_quality_profiles','signal_quality_profiles','signal_accumulation_profiles','hunt_tasks','hunt_targets','playbook_steps','acquisition_playbooks','hunts','capacity_trust_scores','capacity_equipment','capacity_discipline_counts','capacity_profiles','regional_capacity_targets','acquisition_targets','raw_signal_items','harvester_runs','signal_sources','outreach_sequences','outreach_targets','content_ideas','keywords','intelligence_records','signals','opportunities','subcontractors','contacts','organizations','users','capacity_targets','regions'] as $table) {
    $db->exec("DELETE FROM {$table}");
    $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
}

$regionStmt = $db->prepare('INSERT INTO regions (name, owner, owner_name, owner_email, hub_city, hub_state, states, states_covered, priority_tier, operating_status, strategic_notes, coverage_score, capacity_score, relationship_score, opportunity_score, traffic_score, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
$regionRows = [
    'National' => ['National', 'National', 'National', 'admin@jacksontelcom.com', '', '', 'National', 'National / Multi-region', 'National', 'Active', 'National layer for organizations, content, signals, and opportunities that span multiple theaters.', 82, 64, 66, 58, 62],
    'Southeast' => ['Southeast', 'Mike', 'Mike', 'mike@jacksontlcom.com', 'Atlanta', 'GA', 'GA, AL, FL, TN, NC, SC', 'GA, AL, FL, TN, NC, SC', 'Tier 1', 'Active', 'Tier 1 growth theater for broadband, aerial, underground, splicing, and restoration capacity.', 76, 58, 61, 64, 54],
    'Great Lakes' => ['Great Lakes', 'Ron', 'Ron', 'ron@jacksontelcom.com', 'Detroit', 'MI', 'MI, OH, IN, WI, IL', 'MI, OH, IN, WI, IL', 'Tier 1', 'Active', 'Tier 1 relationship and opportunity theater across Great Lakes broadband and utility markets.', 72, 55, 63, 60, 52],
    'Southwest' => ['Southwest', 'Unassigned', '', '', 'Houston', 'TX', 'TX, OK, LA, NM', 'TX, OK, LA, NM', 'Tier 2', 'Expansion', 'Tier 2 Houston-centered capacity and traffic foundation theater.', 38, 34, 28, 32, 26],
];
$regions = [];
foreach ($regionRows as $key => $row) {
    $regionStmt->execute($row);
    $regions[$key] = (int)$db->lastInsertId();
}

$userStmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, region_id) VALUES (?, ?, ?, ?, ?)');
$password = password_hash('password', PASSWORD_DEFAULT);
$userStmt->execute(['Admin', 'admin@jacksontelcom.com', $password, 'Admin', null]);
$userStmt->execute(['Mike', 'mike@jacksontlcom.com', $password, 'Southeast Owner', $regions['Southeast']]);
$userStmt->execute(['Ron', 'ron@jacksontelcom.com', $password, 'Great Lakes Owner', $regions['Great Lakes']]);

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

(new CapacityGapService())->recalculateTrustScores();
RecommendationEngine::regenerate();

echo "Seeded national footprint, traffic records, harvesting sources, raw items, processed signals, acquisition targets, hunts, playbooks, capacity radar, hunt tasks, and recommendations.\n";
