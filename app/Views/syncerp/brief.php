<section class="page-header">
  <p class="eyebrow">Executive Handoff Brief</p>
  <h1>What is ready to hand off?</h1>
  <p>SyncERP remains separate. This brief only shows package readiness and blockers.</p>
</section>

<nav class="dash-tabs">
  <a href="/syncerp-integration">SyncERP Integration</a>
  <a class="active" href="/syncerp-handoff-brief">Executive Handoff Brief</a>
</nav>

<section class="metrics">
  <div><span>Projects Ready</span><strong><?= (int)$data['metrics']['ready'] ?></strong></div>
  <div><span>Projects Blocked</span><strong><?= (int)$data['metrics']['blocked'] ?></strong></div>
  <div><span>Missing Capacity</span><strong><?= (int)$data['metrics']['missing_capacity'] ?></strong></div>
  <div><span>Missing Risk Review</span><strong><?= (int)$data['metrics']['missing_risk'] ?></strong></div>
  <div><span>Awaiting Import</span><strong><?= (int)$data['metrics']['awaiting_import'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel"><h2>Ready Packages</h2><div class="action-stack"><?php foreach ($data['ready'] as $pkg): ?><article><span class="priority high"><?= (int)$pkg['readiness_score'] ?></span><h3><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></a></h3><p><?= htmlspecialchars($pkg['customer_name']) ?> · <?= htmlspecialchars($pkg['region_name'] ?? '') ?></p></article><?php endforeach; ?></div></div>
  <div class="panel"><h2>Blocked Packages</h2><div class="action-stack"><?php foreach ($data['blocked'] as $pkg): ?><article><span class="priority medium"><?= (int)$pkg['readiness_score'] ?></span><h3><a href="/syncerp-integration/detail?id=<?= (int)$pkg['id'] ?>"><?= htmlspecialchars($pkg['package_name']) ?></a></h3><p><?= htmlspecialchars($pkg['blockers']) ?></p></article><?php endforeach; ?></div></div>
</section>
