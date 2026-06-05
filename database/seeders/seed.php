<?php

require __DIR__ . '/../../vendor_autoload.php';

use App\Core\Database;
use App\Core\RecommendationEngine;
use App\Core\SignalScoring;

$db = Database::connection();
$db->beginTransaction();

foreach (['activities','recommended_actions','intelligence_records','signals','opportunities','subcontractors','contacts','organizations','users','capacity_targets','regions'] as $table) {
    $db->exec("DELETE FROM {$table}");
    $db->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
}

$regionStmt = $db->prepare('INSERT INTO regions (name, owner, states, active) VALUES (?, ?, ?, 1)');
$regionStmt->execute(['Southeast', 'Mike', 'GA, AL, FL, TN, NC, SC']);
$southeast = (int)$db->lastInsertId();
$regionStmt->execute(['Great Lakes', 'Ron', 'MI, OH, IN, WI, IL']);
$greatLakes = (int)$db->lastInsertId();

$userStmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, region_id) VALUES (?, ?, ?, ?, ?)');
$password = password_hash('password', PASSWORD_DEFAULT);
$userStmt->execute(['Admin', 'admin@jacksontelcom.com', $password, 'Admin', null]);
$userStmt->execute(['Mike', 'mike@jacksontlcom.com', $password, 'Southeast Owner', $southeast]);
$userStmt->execute(['Ron', 'ron@jacksontelcom.com', $password, 'Great Lakes Owner', $greatLakes]);

$targetStmt = $db->prepare('INSERT INTO capacity_targets (region_id, service_type, target_crews, active) VALUES (?, ?, ?, 1)');
$targets = [
    $southeast => [
        'Aerial' => 10,
        'Underground' => 6,
        'Fiber Splicing' => 5,
        'Emergency Restoration' => 3,
        'Traffic Control' => 3,
    ],
    $greatLakes => [
        'Aerial' => 8,
        'Underground' => 5,
        'Fiber Splicing' => 4,
        'Emergency Restoration' => 3,
        'Traffic Control' => 2,
    ],
];

foreach ($targets as $regionId => $services) {
    foreach ($services as $service => $target) {
        $targetStmt->execute([$regionId, $service, $target]);
    }
}

$signalStmt = $db->prepare('INSERT INTO signals (title, description, signal_type, source_type, source_url, region_id, state, organization_name, contact_name, confidence_score, impact_score, priority, owner, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$signals = [
    [$southeast, 'GA', 'Mike', 'Fiber splicing trailer listed near Atlanta', 'Equipment listing indicates a fiber splicing trailer available in the Atlanta market. Potential lead for a splicing subcontractor or equipment-backed crew.', 'Capacity', 'Equipment Listing', '', 'Atlanta fiber contractor seller', '', 'New', 'Validate seller and ask whether crews are available with the trailer.', '-1 days'],
    [$southeast, 'AL', 'Mike', 'Bucket trucks listed by Alabama contractor', 'Contractor equipment listing includes multiple bucket trucks. Possible aerial capacity signal if crews are downsizing or available.', 'Capacity', 'Facebook Marketplace', '', 'Alabama aerial contractor', '', 'New', 'Contact seller and determine whether aerial crews are available for subcontract work.', '-2 days'],
    [$southeast, 'FL', 'Mike', 'Underground contractor hiring boring operators', 'LinkedIn hiring activity references directional boring, fiber construction, and telecom utility work in Central Florida.', 'Capacity', 'LinkedIn', '', 'Central Florida underground contractor', '', 'Reviewed', 'Track as potential underground capacity source.', '-4 days'],
    [$southeast, 'TN', 'Mike', 'Broadband grant award announced for rural Tennessee', 'Government broadband funding notice suggests upcoming rural fiber construction demand in Tennessee.', 'Opportunity', 'Government Data', '', 'Tennessee broadband program', '', 'New', 'Identify prime awardees and likely construction package timing.', '-1 days'],
    [$southeast, 'NC', 'Mike', 'Municipal fiber planning discussion in North Carolina', 'Municipal meeting agenda references fiber expansion planning and outside construction support.', 'Opportunity', 'Government Data', '', 'North Carolina municipality', '', 'New', 'Research procurement timeline and engineering firm involvement.', '-6 days'],
    [$southeast, 'SC', 'Mike', 'Utility distribution expansion mentions fiber make-ready', 'Industry news references utility expansion that may create make-ready, aerial placement, and restoration opportunities.', 'Market', 'Industry News', '', 'South Carolina utility', '', 'Assigned', 'Monitor utility capital plan and identify contractor contacts.', '-8 days'],
    [$southeast, 'GA', 'Mike', 'Construction manager promoted at regional prime', 'LinkedIn update shows a construction manager promotion at a regional broadband prime contractor.', 'Relationship', 'LinkedIn', '', 'Regional broadband prime', 'Construction Manager', 'New', 'Send congratulations and request intro to construction package lead.', '-3 days'],
    [$southeast, 'FL', 'Mike', 'Referral introduction to aerial supervisor', 'Referral source offered introduction to an aerial supervisor with telecom placement experience in Florida.', 'Relationship', 'Referral', '', 'Florida aerial crew', 'Aerial Supervisor', 'Assigned', 'Schedule intro call and determine crew availability.', '-2 days'],
    [$southeast, 'AL', 'Mike', 'Emergency restoration contractor available after storm season', 'Contractor intelligence indicates restoration crews may be available for backup response work.', 'Capacity', 'Contractor Intelligence', '', 'Alabama restoration contractor', '', 'New', 'Validate emergency response capacity and insurance readiness.', '-5 days'],
    [$southeast, 'SC', 'Mike', 'BEAD-related regional broadband planning activity', 'State broadband planning activity indicates future rural construction packages likely to enter procurement.', 'Market', 'Government Data', '', 'South Carolina broadband office', '', 'Reviewed', 'Track counties, providers, and expected bid windows.', '-9 days'],
    [$greatLakes, 'MI', 'Ron', 'Splicing trailer and OTDR equipment listed in Michigan', 'Equipment listing includes splicing trailer and test equipment. Potential signal for splicer availability.', 'Capacity', 'Equipment Listing', '', 'Michigan splicing contractor seller', '', 'New', 'Contact seller and ask whether certified splicers are taking subcontract work.', '-1 days'],
    [$greatLakes, 'OH', 'Ron', 'Ohio underground crew posting directional drill roles', 'Hiring signal references directional drills, telecom conduit, and fiber underground work.', 'Capacity', 'LinkedIn', '', 'Ohio underground contractor', '', 'New', 'Qualify boring capacity and service area.', '-2 days'],
    [$greatLakes, 'IN', 'Ron', 'Prime contractor award references broadband construction', 'Industry award notice indicates a prime contractor won broadband construction work in Indiana.', 'Opportunity', 'Industry News', '', 'Indiana broadband prime', '', 'New', 'Identify subcontractor package needs and decision makers.', '-3 days'],
    [$greatLakes, 'WI', 'Ron', 'Municipal fiber project planning in Wisconsin', 'Municipal agenda references fiber network planning and possible construction bid packages.', 'Opportunity', 'Government Data', '', 'Wisconsin municipality', '', 'Reviewed', 'Research project owner, engineer, and procurement dates.', '-4 days'],
    [$greatLakes, 'IL', 'Ron', 'Utility capital plan includes regional fiber expansion', 'Utility spending plan references communications infrastructure and regional fiber expansion.', 'Market', 'Industry News', '', 'Illinois utility', '', 'Assigned', 'Map affected service territories and likely contractors.', '-5 days'],
    [$greatLakes, 'MI', 'Ron', 'Construction director change at utility partner', 'LinkedIn activity indicates a construction leadership change at a utility organization.', 'Relationship', 'LinkedIn', '', 'Michigan utility', 'Construction Director', 'New', 'Confirm role and prepare relationship outreach.', '-2 days'],
    [$greatLakes, 'OH', 'Ron', 'Contractor selling bucket trucks in Ohio', 'Equipment listing shows multiple bucket trucks for sale. Possible downsizing or crew availability signal.', 'Capacity', 'Facebook Marketplace', '', 'Ohio aerial contractor seller', '', 'New', 'Contact seller and ask whether aerial crews are available.', '-7 days'],
    [$greatLakes, 'IN', 'Ron', 'Referral to fiber construction manager in Indiana', 'Referral introduction offered to a fiber construction manager with regional prime relationships.', 'Relationship', 'Referral', '', 'Indiana fiber construction contact', 'Construction Manager', 'Assigned', 'Schedule intro call and capture prime relationships.', '-1 days'],
    [$greatLakes, 'WI', 'Ron', 'State broadband funding update for Wisconsin', 'Government broadband funding update points to upcoming rural deployment pressure.', 'Market', 'Government Data', '', 'Wisconsin broadband office', '', 'Reviewed', 'Track award map and eligible project areas.', '-6 days'],
    [$greatLakes, 'IL', 'Ron', 'Traffic control vendor expanding telecom support', 'Contractor intelligence suggests traffic control provider is adding telecom construction support in Illinois.', 'Capacity', 'Contractor Intelligence', '', 'Illinois traffic control provider', '', 'New', 'Qualify insurance, service territory, and availability.', '-4 days'],
];

foreach ($signals as [$regionId, $state, $owner, $title, $description, $type, $source, $url, $organization, $contact, $status, $notes, $relativeDate]) {
    $created = date('Y-m-d H:i:s', strtotime($relativeDate));
    $payload = [
        'title' => $title,
        'description' => $description,
        'signal_type' => $type,
        'source_type' => $source,
        'source_url' => $url,
        'region_id' => $regionId,
        'state' => $state,
        'organization_name' => $organization,
        'contact_name' => $contact,
        'owner' => $owner,
        'status' => $status,
        'notes' => $notes,
    ];
    $score = SignalScoring::score($payload);
    $signalStmt->execute([$title, $description, $type, $source, $url, $regionId, $state, $organization, $contact, $score['confidence_score'], $score['impact_score'], $score['priority'], $owner, $status, $notes, $created, $created]);
}

$db->commit();

// Production seed data intentionally excludes converted organizations, contacts,
// subcontractors, opportunities, and activities. Signal records are seeded as
// acquisition-intelligence examples for Signal Center workflow validation.
RecommendationEngine::regenerate();

echo "Seeded production baseline: regions, users, capacity targets, signal intelligence, and system-generated recommendations.\n";
