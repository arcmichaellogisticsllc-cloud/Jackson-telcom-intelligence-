<section class="page-header">
  <p class="eyebrow">National Command Center</p>
  <h1>Focused theaters. National footprint.</h1>
  <p>Traffic, signals, capacity, relationships, opportunities, and decision support for telecom construction growth. SyncERP remains the final integration layer only.</p>
</section>

<nav class="dash-tabs">
  <a class="active" href="/">National Overview</a>
  <a href="/regions">Regional Command Centers</a>
  <a href="/command/southeast">Southeast Command Center</a>
  <a href="/command/great-lakes">Great Lakes Command Center</a>
  <a href="/command/southwest">Southwest Command Center</a>
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
        <div><span>Coverage Score</span><strong><?= (int)($region['coverage_score'] ?? 0) ?></strong></div>
        <div><span>Capacity Score</span><strong><?= (int)($region['capacity_score_value'] ?? 0) ?></strong></div>
        <div><span>Relationship Score</span><strong><?= (int)($region['relationship_score'] ?? 0) ?></strong></div>
        <div><span>Traffic Score</span><strong><?= (int)($region['traffic_score'] ?? 0) ?></strong></div>
      </div>
      <div class="mini-metrics">
        <div><span>Approved Network</span><strong><?= (int)$region['approved_subcontractors'] ?></strong></div>
        <div><span>Available Crews</span><strong><?= (int)$region['approved_crews'] ?></strong></div>
        <div><span>Open Opps</span><strong><?= (int)$region['open_opportunities'] ?></strong></div>
        <div><span>Opportunity Score</span><strong><?= (int)($region['opportunity_score'] ?? 0) ?></strong></div>
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
    <div class="panel-title"><h2>Top Signals</h2><a class="btn secondary" href="/signals">Signal Center</a></div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Signal Source</th><th>Theater</th><th>Recommended Action</th></tr></thead><tbody><?php foreach ($topSignals as $signal): ?><tr><td><span class="priority <?= strtolower($signal['priority']) ?>"><?= htmlspecialchars($signal['priority']) ?></span></td><td><strong><?= htmlspecialchars($signal['title']) ?></strong><br><small><?= htmlspecialchars($signal['signal_type']) ?> · <?= htmlspecialchars($signal['source_type']) ?></small></td><td><?= htmlspecialchars($signal['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($signal['recommended_next_action'] ?: 'Review and assign next acquisition action.') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Capacity Needs</h2><a class="btn secondary" href="/recommendations">Recommended Actions</a></div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Theater</th><th>Capacity Recruitment</th></tr></thead><tbody><?php foreach ($topCapacityNeeds as $need): ?><tr><td><span class="priority <?= strtolower($need['priority']) ?>"><?= htmlspecialchars($need['priority']) ?></span></td><td><?= htmlspecialchars($need['region_name'] ?? 'National') ?></td><td><strong><?= htmlspecialchars($need['title']) ?></strong><br><small><?= htmlspecialchars($need['recommended_next_action']) ?></small></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Top Opportunities</h2><a class="btn secondary" href="/opportunities">Opportunity Intelligence</a></div>
  <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Opportunity</th><th>Stage</th><th>Value</th><th>Next Action</th></tr></thead><tbody><?php foreach ($topOpportunities as $opp): ?><tr><td><?= htmlspecialchars($opp['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($opp['name']) ?></td><td><?= htmlspecialchars($opp['stage']) ?></td><td>$<?= number_format((float)$opp['estimated_value']) ?></td><td><?= htmlspecialchars($opp['next_action']) ?></td></tr><?php endforeach; ?><?php if (!$topOpportunities): ?><tr><td colspan="5">No converted opportunities yet. Signal Center and Traffic Engine are feeding the acquisition layer.</td></tr><?php endif; ?></tbody></table></div>
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
