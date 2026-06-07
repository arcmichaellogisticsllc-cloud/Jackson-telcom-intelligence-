<section class="page-header">
  <p class="eyebrow">SyncERP Integration Layer</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?> No execution, billing, labor, equipment, or production workflows are created here.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionId === null ? 'active' : '' ?>" href="/syncerp-integration">National</a>
  <a href="/syncerp-integration/southeast">Southeast</a>
  <a href="/syncerp-integration/great-lakes">Great Lakes</a>
  <a href="/syncerp-integration/southwest">Southwest</a>
  <a href="/syncerp-handoff-brief">Executive Handoff Brief</a>
  <form method="post" action="/syncerp-integration/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Packages</button></form>
</nav>

<section class="metrics">
  <div><span>Ready Packages</span><strong><?= (int)$metrics['ready'] ?></strong></div>
  <div><span>Blocked Packages</span><strong><?= (int)$metrics['blocked'] ?></strong></div>
  <div><span>Missing Capacity</span><strong><?= (int)$metrics['missing_capacity'] ?></strong></div>
  <div><span>Missing Risk Review</span><strong><?= (int)$metrics['missing_risk'] ?></strong></div>
  <div><span>Awaiting Import</span><strong><?= (int)$metrics['awaiting_import'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Packages Ready For SyncERP</h2><span class="status">Handoff Only</span></div>
    <div class="table-wrap"><table><thead><tr><th>Package</th><th>Customer</th><th>Theater</th><th>Readiness</th><th>Status</th></tr></thead><tbody><?php foreach ($ready as $pkg): ?><tr><td><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><strong><?= htmlspecialchars($pkg['package_name']) ?></strong></a></td><td><?= htmlspecialchars($pkg['customer_name']) ?></td><td><?= htmlspecialchars($pkg['region_name'] ?? '') ?></td><td><?= (int)$pkg['readiness_score'] ?><br><small><?= htmlspecialchars($pkg['readiness_category']) ?></small></td><td><?= htmlspecialchars($pkg['integration_status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Blocked Packages</h2><span class="status">Resolve Before Handoff</span></div>
    <div class="table-wrap"><table><thead><tr><th>Package</th><th>Readiness</th><th>Blockers</th></tr></thead><tbody><?php foreach ($blocked as $pkg): ?><tr><td><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></a><br><small><?= htmlspecialchars($pkg['region_name'] ?? '') ?></small></td><td><?= (int)$pkg['readiness_score'] ?> · <?= htmlspecialchars($pkg['readiness_category']) ?></td><td><?= htmlspecialchars($pkg['blockers']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Packages Missing Capacity</h2>
    <div class="action-stack"><?php foreach ($missingCapacity as $pkg): ?><article><span class="priority high"><?= (int)$pkg['readiness_score'] ?></span><h3><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></a></h3><p><?= htmlspecialchars($pkg['blockers']) ?></p></article><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <h2>Packages Missing Risk Review</h2>
    <div class="action-stack"><?php foreach ($missingRisk as $pkg): ?><article><span class="priority medium"><?= (int)$pkg['readiness_score'] ?></span><h3><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></a></h3><p><?= htmlspecialchars($pkg['blockers']) ?></p></article><?php endforeach; ?></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Integration Recommendations</h2><a class="btn secondary" href="/recommendations">Recommendations</a></div>
  <div class="action-stack"><?php foreach ($recommendations as $rec): ?><article><span class="priority <?= strtolower($rec['priority']) ?>"><?= htmlspecialchars($rec['priority']) ?> · <?= (int)$rec['priority_score'] ?></span><h3><?= htmlspecialchars($rec['title']) ?></h3><p><?= htmlspecialchars($rec['recommended_next_action']) ?></p></article><?php endforeach; ?></div>
</section>
