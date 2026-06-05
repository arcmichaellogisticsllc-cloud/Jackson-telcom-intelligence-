<?php

require __DIR__ . '/../../vendor_autoload.php';

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;

$db = Database::connection();
$db->beginTransaction();

foreach (['activities','recommended_actions','outreach_sequences','outreach_targets','content_ideas','keywords','intelligence_records','signals','opportunities','subcontractors','contacts','organizations','users','capacity_targets','regions'] as $table) {
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

$db->commit();

RecommendationEngine::regenerate();

echo "Seeded national footprint, three theaters, traffic engine records, signals, outreach workflows, and recommendations.\n";
