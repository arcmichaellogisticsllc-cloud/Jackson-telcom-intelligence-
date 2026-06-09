<?php
$recordEyebrow = 'Project Package Workspace';
$recordName = $package['package_name'];
$recordType = 'Project Package';
$recordRegion = $package['region_name'] ?? 'National';
$recordOwner = $package['package_owner'] ?? 'Unassigned';
$recordStatus = $package['readiness_category'] ?? $package['package_status'];
$recordScore = (int)($package['readiness_score'] ?? 0);
$recordNextAction = $package['blockers'] ? 'Resolve blockers: ' . $package['blockers'] : 'Review package and mark ready for SyncERP handoff.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Package for SyncERP','Mark Reviewed'];
$recordEntityType = 'project_package';
$recordEntityId = (int)$package['id'];
$recordRegionId = (int)($package['region_id'] ?? 0);
$timelineItems = [
  ['type' => 'ERP Readiness', 'title' => 'Readiness score ' . (int)$package['readiness_score'], 'why' => $package['blockers'] ?: 'Package appears ready for handoff review.', 'next' => $recordNextAction, 'owner' => $recordOwner, 'date' => $package['updated_at'] ?? $package['created_at'] ?? ''],
  ['type' => 'Package Snapshot', 'title' => 'Capacity and relationship context preserved', 'why' => 'The handoff package prevents re-entry and protects acquisition context.', 'next' => 'Review snapshots before import.', 'owner' => $recordOwner, 'date' => $package['created_at'] ?? ''],
];
foreach ($recentConversations ?? [] as $conversation) {
  $timelineItems[] = ['type' => $conversation['communication_type'], 'title' => $conversation['summary'], 'why' => $conversation['outcome'] ?: 'Conversation may affect handoff readiness or package blockers.', 'next' => $conversation['next_step'] ?: 'Create follow-up if needed.', 'owner' => $conversation['owner'], 'date' => $conversation['communication_date']];
}
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Contacts / People','Conversations','Capacity','Tasks / Actions','Documents','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$what = 'This is a SyncERP handoff package workspace. It prepares execution context without building execution here.';
$why = 'This package preserves acquisition, pursuit, relationship, capacity, preconstruction, margin, and risk context for future SyncERP handoff.';
$recommended = $recordNextAction;
$next = 'Resolve blockers, record the handoff review, and keep the package in review until it is ready.';
$risk = 'If this package moves forward incomplete, execution can lose the intelligence that justified the pursuit.';
require __DIR__ . '/../components/action_first.php';
?>

<nav class="dash-tabs">
  <a href="/syncerp-integration">SyncERP Integration</a>
  <a href="/preconstruction/detail?id=<?= (int)$package['preconstruction_profile_id'] ?>">Preconstruction</a>
  <a href="/pursuits/detail?id=<?= (int)$package['opportunity_id'] ?>">Pursuit</a>
</nav>

<section class="metrics">
  <div><span>Readiness Score</span><strong><?= (int)$package['readiness_score'] ?></strong></div>
  <div><span>Status</span><strong><?= htmlspecialchars($package['package_status']) ?></strong></div>
  <div><span>Integration</span><strong><?= htmlspecialchars($package['integration_status']) ?></strong></div>
  <div><span>Value</span><strong>$<?= number_format((float)$package['estimated_value']) ?></strong></div>
  <div><span>Margin</span><strong><?= number_format((float)$package['estimated_margin'], 1) ?>%</strong></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="grid two">
  <article class="panel"><h2>Opportunity Summary</h2><p><strong>Customer:</strong> <?= htmlspecialchars($package['customer_name']) ?></p><p><strong>Market:</strong> <?= htmlspecialchars($package['market']) ?></p><p><strong>State:</strong> <?= htmlspecialchars($package['state']) ?></p><p><?= htmlspecialchars($package['notes']) ?></p></article>
  <article class="panel"><h2>ERP Readiness</h2><p><strong>Blockers:</strong> <?= htmlspecialchars($package['blockers'] ?: 'None') ?></p><p>Opportunity <?= $package['opportunity_approved'] ? 'approved' : 'not approved' ?> · Pursuit <?= $package['pursuit_approved'] ? 'approved' : 'not approved' ?> · Preconstruction <?= $package['preconstruction_complete'] ? 'complete' : 'incomplete' ?></p><p>Capacity <?= $package['capacity_assigned'] ? 'assigned' : 'missing' ?> · Subcontractor plan <?= $package['subcontractor_plan_complete'] ? 'complete' : 'incomplete' ?> · Risk review <?= $package['risk_review_complete'] ? 'complete' : 'incomplete' ?></p></article>
</section>

<section class="panel">
  <h2>Capacity Allocation Snapshot</h2>
  <p><strong>Crews Assigned:</strong> <?= (int)($package['capacitySnapshot']['crews_assigned'] ?? 0) ?></p>
  <p><strong>Subcontractors Selected:</strong> <?= htmlspecialchars($package['capacitySnapshot']['subcontractors_selected'] ?? '') ?></p>
  <p><strong>Disciplines Required:</strong> <?= htmlspecialchars($package['capacitySnapshot']['disciplines_required'] ?? '') ?></p>
  <p><?= htmlspecialchars($package['capacitySnapshot']['mobilization_assumptions'] ?? '') ?></p>
</section>

<section class="grid two">
  <div class="panel"><h2>Relationship Context Snapshot</h2><p><strong>Key Contacts:</strong> <?= htmlspecialchars($package['relationshipSnapshot']['key_contacts'] ?? '') ?></p><p><strong>Project Managers:</strong> <?= htmlspecialchars($package['relationshipSnapshot']['project_managers'] ?? '') ?></p><p><strong>Utility Contacts:</strong> <?= htmlspecialchars($package['relationshipSnapshot']['utility_contacts'] ?? '') ?></p><p><strong>Objectives:</strong> <?= htmlspecialchars($package['relationshipSnapshot']['relationship_objectives'] ?? '') ?></p><p><strong>Scores:</strong> <?= htmlspecialchars($package['relationshipSnapshot']['relationship_scores'] ?? '') ?></p></div>
  <div class="panel"><h2>Preconstruction Snapshot</h2><p><strong>Bid:</strong> <?= htmlspecialchars($package['preconstructionSnapshot']['bid_decision'] ?? '') ?></p><p><strong>Margin:</strong> <?= htmlspecialchars($package['preconstructionSnapshot']['margin_forecast'] ?? '') ?></p><p><strong>Scenario:</strong> <?= htmlspecialchars($package['preconstructionSnapshot']['scenario'] ?? '') ?></p><p><strong>Risk:</strong> <?= htmlspecialchars($package['preconstructionSnapshot']['risk_assessment'] ?? '') ?></p></div>
</section>

<section class="grid two">
  <div class="panel"><h2>Decision Support History</h2><div class="action-stack"><?php foreach ($package['decisionHistory'] as $item): ?><article><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span><h3><?= htmlspecialchars($item['action_title']) ?></h3><p><?= htmlspecialchars($item['recommended_next_step']) ?></p></article><?php endforeach; ?><?php if (!$package['decisionHistory']): ?><p>No linked daily actions.</p><?php endif; ?></div></div>
  <div class="panel"><h2>Learning Insights</h2><div class="action-stack"><?php foreach ($package['learningInsights'] as $item): ?><article><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span><h3><?= htmlspecialchars($item['insight_title']) ?></h3><p><?= htmlspecialchars($item['recommended_action']) ?></p></article><?php endforeach; ?></div></div>
</section>
