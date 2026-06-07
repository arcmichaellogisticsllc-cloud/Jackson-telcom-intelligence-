<section class="page-header">
  <p class="eyebrow">Execution Package View</p>
  <h1><?= htmlspecialchars($package['package_name']) ?></h1>
  <p><?= htmlspecialchars($package['customer_name']) ?> · <?= htmlspecialchars($package['region_name'] ?? '') ?> · <?= htmlspecialchars($package['readiness_category'] ?? 'Not Ready') ?></p>
</section>

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
