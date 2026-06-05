<section class="page-header">
  <p class="eyebrow">Regional Command Centers</p>
  <h1>Focused theaters. National footprint.</h1>
  <p>Southeast and Great Lakes are Tier 1 operating theaters. Southwest is a Houston-centered Tier 2 expansion theater. National captures multi-region signals, organizations, content, and opportunities.</p>
</section>

<section class="region-grid">
  <?php foreach ($regions as $region): ?>
    <article class="panel">
      <div class="panel-title">
        <div>
          <p class="eyebrow"><?= htmlspecialchars($region['priority_tier'] ?? '') ?></p>
          <h2><?= htmlspecialchars($region['name']) ?></h2>
        </div>
        <span class="score <?= strtolower($region['capacity_score']['category'] ?? 'stable') ?>"><?= htmlspecialchars((string)($region['operating_status'] ?? 'Active')) ?></span>
      </div>
      <p><strong>Theater Owner:</strong> <?= htmlspecialchars($region['owner_name'] ?: $region['owner']) ?></p>
      <p><strong>Hub:</strong> <?= htmlspecialchars(trim(($region['hub_city'] ?? '') . ', ' . ($region['hub_state'] ?? ''), ', ')) ?></p>
      <p><strong>Coverage:</strong> <?= htmlspecialchars($region['states_covered'] ?: $region['states']) ?></p>
      <div class="mini-metrics">
        <div><span>Coverage</span><strong><?= (int)($region['coverage_score'] ?? 0) ?></strong></div>
        <div><span>Capacity</span><strong><?= (int)($region['capacity_score_value'] ?? $region['capacity_score']['score'] ?? 0) ?></strong></div>
        <div><span>Relationships</span><strong><?= (int)($region['relationship_score'] ?? 0) ?></strong></div>
        <div><span>Traffic</span><strong><?= (int)($region['traffic_score'] ?? 0) ?></strong></div>
      </div>
      <?php if ($region['name'] !== 'National'): ?>
        <p><a class="btn secondary" href="/command/<?= strtolower(str_replace(' ', '-', $region['name'])) ?>">Open <?= htmlspecialchars($region['name']) ?> Command</a></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
