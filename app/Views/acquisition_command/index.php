<section class="page-header">
  <p class="eyebrow">Acquisition Doctrine</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionId === null ? 'active' : '' ?>" href="/acquisition-command">National</a>
  <a href="/acquisition-command/southeast">Southeast</a>
  <a href="/acquisition-command/great-lakes">Great Lakes</a>
  <a href="/acquisition-command/southwest">Southwest</a>
  <form method="post" action="/acquisition-command/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Doctrine</button></form>
</nav>

<section class="metrics">
  <div><span>Who Has Work</span><strong><?= (int)$metrics['work'] ?></strong></div>
  <div><span>Who Has Capacity</span><strong><?= (int)$metrics['capacity'] ?></strong></div>
  <div><span>Who Needs Work</span><strong><?= (int)$metrics['need'] ?></strong></div>
  <div><span>Who Influences Work</span><strong><?= (int)$metrics['influence'] ?></strong></div>
  <div><span>Acquisition Priority</span><strong><?= (int)$metrics['priority_score'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Who Has Work</h2><span class="status">Work Ready</span></div>
    <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Status</th><th>Value</th><th>Score</th><th>Capacity</th></tr></thead><tbody><?php foreach ($work as $item): ?><tr><td><strong><?= htmlspecialchars($item['organization_name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($item['organization_type'] ?? '') ?> · <?= htmlspecialchars($item['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($item['work_status']) ?><br><small><?= htmlspecialchars($item['work_type'] ?? '') ?></small></td><td>$<?= number_format((float)$item['estimated_value']) ?></td><td><?= (int)$item['work_readiness_score'] ?></td><td><?= (int)$item['capacity_required'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Who Has Capacity</h2><span class="status">Deployable Capacity</span></div>
    <div class="table-wrap"><table><thead><tr><th>Provider</th><th>Disciplines</th><th>Crews</th><th>Readiness</th><th>Score</th></tr></thead><tbody><?php foreach ($capacity as $item): ?><tr><td><strong><?= htmlspecialchars($item['profile_name'] ?? 'Capacity Provider') ?></strong><br><small><?= htmlspecialchars($item['profile_type'] ?? '') ?> · <?= htmlspecialchars($item['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($item['disciplines'] ?: 'Unknown') ?></td><td><?= (int)$item['available_crews'] ?></td><td><?= htmlspecialchars($item['mobilization_readiness'] ?: 'Unknown') ?></td><td><?= (int)$item['deployable_capacity_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Who Needs Work</h2><span class="status">Need Work</span></div>
    <div class="table-wrap"><table><thead><tr><th>Organization</th><th>Workload</th><th>Idle Crews</th><th>Urgency</th><th>Score</th></tr></thead><tbody><?php foreach ($need as $item): ?><tr><td><strong><?= htmlspecialchars($item['organization_name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($item['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($item['workload_status']) ?></td><td><?= (int)$item['estimated_idle_crews'] ?></td><td><?= htmlspecialchars($item['urgency']) ?></td><td><?= (int)$item['need_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Who Influences Work</h2><span class="status">Influence</span></div>
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Organization</th><th>Role</th><th>Access</th><th>Score</th></tr></thead><tbody><?php foreach ($influence as $item): ?><tr><td><strong><?= htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?></strong><br><small><?= htmlspecialchars($item['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($item['organization_name'] ?? '') ?></td><td><?= htmlspecialchars($item['influence_role'] ?: 'Unknown') ?></td><td><?= (int)$item['access_score'] ?></td><td><?= (int)$item['final_influence_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Specialized Watchlists</h2><span class="status">Recent Changes</span></div>
    <div class="table-wrap"><table><thead><tr><th>List</th><th>Escalation</th><th>Change</th><th>Action</th></tr></thead><tbody><?php foreach ($watchlists as $item): ?><tr><td><?= htmlspecialchars($item['watchlist_type']) ?><br><small><?= htmlspecialchars($item['region_name'] ?? '') ?></small></td><td><span class="priority <?= strtolower($item['escalation_level']) ?>"><?= htmlspecialchars($item['escalation_level']) ?></span></td><td><?= htmlspecialchars($item['recent_change']) ?></td><td><?= htmlspecialchars($item['recommended_action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Recommended Actions</h2><a class="btn secondary" href="/recommendations">All Recommendations</a></div>
    <div class="action-stack"><?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['priority_score'] ?></span><h3><?= htmlspecialchars($action['title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_action']) ?></p><small><?= htmlspecialchars($action['assigned_owner']) ?> · <?= htmlspecialchars($action['region_name'] ?? 'National') ?></small></article><?php endforeach; ?><?php if (!$actions): ?><p>No doctrine actions yet.</p><?php endif; ?></div>
  </div>
</section>
