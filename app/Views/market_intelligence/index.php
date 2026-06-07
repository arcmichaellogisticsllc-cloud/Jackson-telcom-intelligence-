<section class="page-header">
  <p class="eyebrow">Regional Market Intelligence Network</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionId === null ? 'active' : '' ?>" href="/market-intelligence">National</a>
  <a href="/market-intelligence/southeast">Southeast</a>
  <a href="/market-intelligence/great-lakes">Great Lakes</a>
  <a href="/market-intelligence/southwest">Southwest</a>
  <form method="post" action="/market-intelligence/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Market Network</button></form>
</nav>

<section class="metrics">
  <div><span>Sources</span><strong><?= (int)$metrics['sources'] ?></strong></div>
  <div><span>Markets</span><strong><?= (int)$metrics['profiles'] ?></strong></div>
  <div><span>Avg Source Quality</span><strong><?= (int)$metrics['avg_quality'] ?></strong></div>
  <div><span>Avg Market Readiness</span><strong><?= (int)$metrics['avg_readiness'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Market Readiness</h2><span class="status">12-24 Month View</span></div>
    <div class="table-wrap"><table><thead><tr><th>Market</th><th>Theater</th><th>Readiness</th><th>Funding</th><th>Upcoming Work</th></tr></thead><tbody><?php foreach ($profiles as $profile): ?><tr><td><strong><?= htmlspecialchars($profile['market']) ?></strong><br><small><?= htmlspecialchars($profile['strategic_priority']) ?></small></td><td><?= htmlspecialchars($profile['region_name'] ?? '') ?></td><td><?= (int)$profile['market_readiness_score'] ?><br><small><?= htmlspecialchars($profile['readiness_category']) ?></small></td><td><?= (int)$profile['funding_activity'] ?></td><td><?= htmlspecialchars($profile['upcoming_opportunities']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Intelligence Sources</h2><span class="status">Quality Ranked</span></div>
    <div class="table-wrap"><table><thead><tr><th>Source</th><th>Type</th><th>Yield</th><th>Noise</th><th>Quality</th></tr></thead><tbody><?php foreach ($sources as $source): ?><tr><td><strong><?= htmlspecialchars($source['source_name']) ?></strong><br><small><?= htmlspecialchars($source['state']) ?> · <?= htmlspecialchars($source['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($source['source_type']) ?></td><td>S <?= (int)$source['signal_yield'] ?> · O <?= (int)$source['opportunity_yield'] ?> · R <?= (int)$source['relationship_yield'] ?></td><td><?= (int)$source['noise_level'] ?></td><td><?= (int)$source['quality_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Market Intelligence Recommendations</h2><a class="btn secondary" href="/recommendations">Recommendations</a></div>
  <div class="action-stack"><?php foreach ($recommendations as $rec): ?><article><span class="priority <?= strtolower($rec['priority']) ?>"><?= htmlspecialchars($rec['priority']) ?> · <?= (int)$rec['priority_score'] ?></span><h3><?= htmlspecialchars($rec['title']) ?></h3><p><?= htmlspecialchars($rec['recommended_next_action']) ?></p><small><?= htmlspecialchars($rec['assigned_owner']) ?> · <?= htmlspecialchars($rec['region_name'] ?? 'National') ?></small></article><?php endforeach; ?></div>
</section>
