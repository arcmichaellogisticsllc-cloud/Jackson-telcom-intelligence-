<section class="page-header">
  <p class="eyebrow">Executive Overview</p>
  <h1>Capacity acquisition before opportunity execution.</h1>
  <p>Executive view of approved network strength, available crews, pipeline value, critical recommendations, regional capacity gaps, pursuit pressure, and recent activity.</p>
</section>

<nav class="dash-tabs">
  <a class="active" href="/">Executive Overview</a>
  <a href="/command/southeast">Southeast Command Center</a>
  <a href="/command/great-lakes">Great Lakes Command Center</a>
</nav>

<section class="metrics">
  <div><span>Total approved subcontractors</span><strong><?= $totals['approved_subcontractors'] ?></strong></div>
  <div><span>Total available crews</span><strong><?= $totals['available_crews'] ?></strong></div>
  <div><span>Total open opportunities</span><strong><?= $totals['open_opportunities'] ?></strong></div>
  <div><span>Total pipeline value</span><strong>$<?= number_format($totals['pipeline_value']) ?></strong></div>
  <div><span>Critical recommendations</span><strong><?= $totals['critical_recommendations'] ?></strong></div>
</section>

<section class="metrics">
  <div><span>New Signals</span><strong><?= $signalWidgets['new'] ?></strong></div>
  <div><span>Critical Signals</span><strong><?= $signalWidgets['critical'] ?></strong></div>
  <div><span>Signals Needing Review</span><strong><?= $signalWidgets['needing_review'] ?></strong></div>
  <div><span>Signals Assigned</span><strong><?= $signalWidgets['assigned_to_me'] ?></strong></div>
  <div><span>Converted This Month</span><strong><?= $signalWidgets['converted_month'] ?></strong></div>
</section>

<section class="region-grid">
  <?php foreach ($regions as $region): ?>
    <article class="panel">
      <div class="panel-title">
        <div><p class="eyebrow"><?= htmlspecialchars($region['owner']) ?></p><h2><?= htmlspecialchars($region['name']) ?></h2></div>
        <span class="score <?= strtolower($region['capacity_score']['category']) ?>"><?= $region['capacity_score']['category'] ?> <?= $region['capacity_score']['score'] ?></span>
      </div>
      <p><?= htmlspecialchars($region['states']) ?></p>
      <div class="mini-metrics">
        <div><span>Approved Network</span><strong><?= (int)$region['approved_subcontractors'] ?></strong></div>
        <div><span>Available Crews</span><strong><?= (int)$region['approved_crews'] ?></strong></div>
        <div><span>Open Opps</span><strong><?= (int)$region['open_opportunities'] ?></strong></div>
        <div><span>Services Covered</span><strong><?= (int)$region['capacity_score']['service_coverage'] ?>/5</strong></div>
      </div>
      <h3>Capacity Gap</h3>
      <div class="gap-list">
        <?php foreach ($region['gaps'] as $service => $gap): ?>
          <div><span><?= htmlspecialchars($service) ?></span><strong><?= $gap['current'] ?>/<?= $gap['target'] ?></strong></div>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Opportunities by Stage</h2>
    <div class="table-wrap"><table><thead><tr><th>Stage</th><th>Count</th></tr></thead><tbody><?php foreach ($stageCounts as $stage): ?><tr><td><?= htmlspecialchars($stage['stage'] ?: 'Unstaged') ?></td><td><?= (int)$stage['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Recent Activity</h2>
    <div class="activity-list"><?php foreach ($recentActivities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?></span></div><?php endforeach; ?><?php if (!$recentActivities): ?><p>No activity recorded yet.</p><?php endif; ?></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Recommended Actions</h2><a class="btn secondary" href="/recommendations">View All</a></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Priority</th><th>Type</th><th>Region</th><th>Recommended Action</th><th>Owner</th></tr></thead>
      <tbody>
      <?php foreach ($actions as $action): ?>
        <tr><td><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?></span></td><td><?= htmlspecialchars($action['recommendation_type']) ?></td><td><?= htmlspecialchars($action['region_name'] ?? 'All') ?></td><td><strong><?= htmlspecialchars($action['title']) ?></strong><br><small><?= htmlspecialchars($action['why_it_matters']) ?></small><br><small><?= htmlspecialchars($action['recommended_next_action']) ?></small></td><td><?= htmlspecialchars($action['assigned_owner']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
