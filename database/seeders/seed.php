<?php

require __DIR__ . '/../../vendor_autoload.php';

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;
use App\Services\SignalProcessingService;
use App\Services\AcquisitionTargetService;

$db = Database::connection();
$db->beginTransaction();

foreach (['activities','recommended_actions','acquisition_targets','raw_signal_items','harvester_runs','signal_sources','outreach_sequences','outreach_targets','content_ideas','keywords','intelligence_records','signals','opportunities','subcontractors','contacts','organizations','users','capacity_targets','regions'] as $table) {
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
RecommendationEngine::regenerate();

echo "Seeded national footprint, traffic records, harvesting sources, raw items, processed signals, outreach workflows, and recommendations.\n";
