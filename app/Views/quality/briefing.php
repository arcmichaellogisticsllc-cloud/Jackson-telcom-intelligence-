<section class="page-header">
  <p class="eyebrow">Daily Intelligence Briefing</p>
  <h1><?= $region ? htmlspecialchars($region['name']) : 'National' ?>: what matters today.</h1>
  <p>Actionable intelligence only: escalations, hunts, watchlist movement, and owner recommendations.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionSlug === 'national' ? 'active' : '' ?>" href="/briefing">National</a>
  <a class="<?= $regionSlug === 'southeast' ? 'active' : '' ?>" href="/briefing?region=southeast">Mike: Southeast</a>
  <a class="<?= $regionSlug === 'great-lakes' ? 'active' : '' ?>" href="/briefing?region=great-lakes">Ron: Great Lakes</a>
  <a class="<?= $regionSlug === 'southwest' ? 'active' : '' ?>" href="/briefing?region=southwest">Southwest</a>
</nav>

<section class="metrics">
  <div><span>Escalations</span><strong><?= count($escalations) ?></strong></div>
  <div><span>Active Hunts</span><strong><?= count($hunts) ?></strong></div>
  <div><span>Watchlist Changes</span><strong><?= count($watchlist) ?></strong></div>
  <div><span>Top Recommendations</span><strong><?= count($actions) ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Escalations</h2><a class="btn secondary" href="/escalations">Escalation Center</a></div>
    <div class="action-stack"><?php foreach ($escalations as $item): ?><article><span class="priority critical">Escalate</span><h3><?= htmlspecialchars($item['title']) ?></h3><p><?= htmlspecialchars($item['recommended_next_action'] ?: 'Assign next owner action.') ?></p><small><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · value <?= (int)$item['signal_value_score'] ?></small></article><?php endforeach; ?><?php if (!$escalations): ?><p>No escalations for this briefing.</p><?php endif; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Hunts</h2><a class="btn secondary" href="/hunts">Hunt Command</a></div>
    <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Theater</th><th>Status</th><th>Targets</th></tr></thead><tbody><?php foreach ($hunts as $hunt): ?><tr><td><?= htmlspecialchars($hunt['hunt_name']) ?></td><td><?= htmlspecialchars($hunt['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($hunt['status']) ?></td><td><?= (int)$hunt['target_count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Watchlist Changes</h2><a class="btn secondary" href="/watchlists">Watchlists</a></div>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Entity</th><th>Signal</th></tr></thead><tbody><?php foreach ($watchlist as $item): ?><tr><td><?= htmlspecialchars($item['status']) ?></td><td><?= htmlspecialchars($item['organization_name'] ?: $item['contact_name']) ?></td><td><a href="/record?type=signal&id=<?= (int)$item['signal_id'] ?>"><?= htmlspecialchars($item['signal_title']) ?></a></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Recommendations</h2><a class="btn secondary" href="/recommendations">All Actions</a></div>
    <div class="action-stack"><?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?></span><h3><?= htmlspecialchars($action['title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_action']) ?></p><small><?= htmlspecialchars($action['region_name'] ?? 'National') ?> · <?= htmlspecialchars($action['assigned_owner']) ?></small></article><?php endforeach; ?><?php if (!$actions): ?><p>No recommendations for this briefing.</p><?php endif; ?></div>
  </div>
</section>

<?php if (!$region): ?>
<section class="panel">
  <h2>Regional Comparison</h2>
  <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Escalations</th><th>Hunt Signals</th><th>Watch Signals</th></tr></thead><tbody><?php foreach ($regionComparison as $row): ?><tr><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><?= (int)$row['escalations'] ?></td><td><?= (int)$row['hunt_signals'] ?></td><td><?= (int)$row['watch_signals'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php endif; ?>
