<?php
$recordEyebrow = 'Preconstruction Workspace';
$recordName = $profile['project_name'];
$recordType = 'Preconstruction Profile';
$recordRegion = $profile['region_name'] ?? 'National';
$recordOwner = $profile['owner'] ?? 'Unassigned';
$recordStatus = $profile['preconstruction_status'];
$recordScore = (int)($profile['bid_score'] ?? 0);
$recordNextAction = $profile['recommended_decision'] ?? 'Review bid, capacity, margin, and risk.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Package for SyncERP','Mark Reviewed'];
$recordEntityType = 'preconstruction_profile';
$recordEntityId = (int)$profile['id'];
$recordRegionId = (int)($profile['region_id'] ?? 0);
$timelineItems = [
  ['type' => 'Bid Decision', 'title' => $profile['recommended_decision'] ?? 'Hold', 'why' => $profile['bid_reason'] ?? 'Bid/no-bid decision controls whether this moves toward handoff.', 'next' => $recordNextAction, 'owner' => $recordOwner, 'date' => $profile['updated_at'] ?? $profile['created_at'] ?? ''],
  ['type' => 'Risk Review', 'title' => count($profile['risks']) . ' risks tracked', 'why' => 'Open preconstruction risks can block bid readiness or execution handoff.', 'next' => 'Resolve critical risks before handoff.', 'owner' => $recordOwner, 'date' => $profile['updated_at'] ?? ''],
];
foreach ($recentConversations ?? [] as $conversation) {
  $timelineItems[] = ['type' => $conversation['communication_type'], 'title' => $conversation['summary'], 'why' => $conversation['outcome'] ?: 'Conversation may affect bid readiness, capacity planning, or risk review.', 'next' => $conversation['next_step'] ?: 'Create follow-up if needed.', 'owner' => $conversation['owner'], 'date' => $conversation['communication_date']];
}
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Conversations','Capacity','Tasks / Actions','Documents','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$why = $profile['bid_reason'] ?? 'This profile decides whether Jackson can bid, execute, make money, and control risk before award.';
$recommended = $recordNextAction ?: 'Resolve bid, capacity, margin, and risk questions before handoff.';
$next = 'Log preconstruction review activity or package for SyncERP when readiness is complete.';
$risk = 'If preconstruction gaps remain unresolved, Jackson may bid work it cannot execute or hand off incomplete context.';
require __DIR__ . '/../components/action_first.php';
?>

<nav class="dash-tabs">
  <a href="/preconstruction">Preconstruction</a>
  <a href="/pursuits/detail?id=<?= (int)$profile['opportunity_id'] ?>">Pursuit Detail</a>
  <a href="/capacity-radar">Capacity Radar</a>
  <a href="/subcontractor-acquisition">Subcontractors</a>
</nav>

<section class="metrics">
  <div><span>Bid Score</span><strong><?= (int)($profile['bid_score'] ?? 0) ?></strong></div>
  <div><span>No-Bid Score</span><strong><?= (int)($profile['no_bid_score'] ?? 0) ?></strong></div>
  <div><span>Revenue</span><strong>$<?= number_format((float)($profile['estimated_revenue'] ?? 0)) ?></strong></div>
  <div><span>Margin</span><strong><?= number_format((float)($profile['estimated_margin_percent'] ?? 0), 1) ?>%</strong></div>
  <div><span>Confidence</span><strong><?= (int)($profile['confidence_score'] ?? 0) ?></strong></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="grid two">
  <article class="panel">
    <div class="panel-title"><h2>Bid / No-Bid</h2><span class="priority high"><?= htmlspecialchars($profile['recommended_decision'] ?? 'Hold') ?></span></div>
    <p><?= htmlspecialchars($profile['bid_reason'] ?? '') ?></p>
    <div class="mini-metrics">
      <div><span>Customer</span><strong><?= htmlspecialchars($profile['customer_name'] ?? '') ?></strong></div>
      <div><span>Market</span><strong><?= htmlspecialchars($profile['market'] ?? '') ?></strong></div>
      <div><span>Start</span><strong><?= htmlspecialchars($profile['estimated_start_date'] ?? '') ?></strong></div>
      <div><span>Duration</span><strong><?= (int)($profile['estimated_duration_days'] ?? 0) ?> days</strong></div>
    </div>
  </article>
  <article class="panel">
    <div class="panel-title"><h2>Margin Forecast</h2><span class="status">Forecast only</span></div>
    <div class="mini-metrics">
      <div><span>Labor</span><strong>$<?= number_format((float)$profile['estimated_labor_cost']) ?></strong></div>
      <div><span>Subcontractor</span><strong>$<?= number_format((float)$profile['estimated_subcontractor_cost']) ?></strong></div>
      <div><span>Equipment</span><strong>$<?= number_format((float)$profile['estimated_equipment_cost']) ?></strong></div>
      <div><span>Overhead</span><strong>$<?= number_format((float)$profile['estimated_overhead']) ?></strong></div>
    </div>
  </article>
</section>

<section class="panel">
  <div class="panel-title"><h2>Capacity Consumption Plan</h2><span class="status">Can We Execute?</span></div>
  <div class="table-wrap"><table><thead><tr><th>Discipline</th><th>Required</th><th>Duration</th><th>Source</th><th>Available</th><th>Gap</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($profile['capacityPlans'] as $row): ?><tr><td><?= htmlspecialchars($row['discipline']) ?></td><td><?= (int)$row['required_crews'] ?></td><td><?= (int)$row['required_duration_days'] ?></td><td><?= htmlspecialchars($row['preferred_source']) ?></td><td><?= (int)$row['current_available'] ?></td><td><?= (int)$row['projected_gap'] ?></td><td><?= htmlspecialchars($row['recommended_capacity_action']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Subcontractor Fit Plan</h2>
    <div class="table-wrap"><table><thead><tr><th>Subcontractor</th><th>Role</th><th>Fit</th><th>Trust</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($profile['fitPlans'] as $row): ?><tr><td><?= htmlspecialchars($row['company_name']) ?></td><td><?= htmlspecialchars($row['recommended_role']) ?></td><td><?= (int)$row['fit_score'] ?></td><td><?= (int)$row['trust_score'] ?></td><td><?= htmlspecialchars($row['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <h2>Risk Assessment</h2>
    <div class="action-stack"><?php foreach ($profile['risks'] as $row): ?><article><span class="priority <?= strtolower($row['severity']) ?>"><?= htmlspecialchars($row['severity']) ?></span><h3><?= htmlspecialchars($row['risk_type']) ?></h3><p><?= htmlspecialchars($row['reason']) ?></p><small><?= htmlspecialchars($row['mitigation']) ?></small></article><?php endforeach; ?></div>
  </div>
</section>

<section class="panel">
  <h2>Scenario Planning</h2>
  <div class="table-wrap"><table><thead><tr><th>Scenario</th><th>Revenue</th><th>Margin</th><th>Crew Requirement</th><th>Gap</th><th>Recommendation</th></tr></thead><tbody>
    <?php foreach ($profile['scenarios'] as $row): ?><tr><td><?= htmlspecialchars($row['scenario_type']) ?></td><td>$<?= number_format((float)$row['revenue_estimate']) ?></td><td><?= number_format((float)$row['margin_estimate'], 1) ?>%</td><td><?= (int)$row['crew_requirement'] ?></td><td><?= (int)$row['capacity_gap'] ?></td><td><?= htmlspecialchars($row['recommendation']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
