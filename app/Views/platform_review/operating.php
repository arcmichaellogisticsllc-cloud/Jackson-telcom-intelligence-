<section class="page-header">
  <p class="eyebrow">Executive Operating View</p>
  <h1>The five things that matter.</h1>
  <p>One operating screen for work, capacity, need, influence, and what to do next.</p>
</section>

<nav class="dash-tabs">
  <a class="active" href="/operating-view">Executive Operating View</a>
  <a href="/platform-review">Platform Health</a>
  <a href="/operator-modes">Operator Modes</a>
  <a href="/acquisition-command">Acquisition Command</a>
  <a href="/market-intelligence">Market Intelligence</a>
</nav>

<section class="grid two">
  <?php foreach ([['Who Has Work','work','organization_name','work_readiness_score'],['Who Has Capacity','capacity','profile_name','deployable_capacity_score'],['Who Needs Work','need','organization_name','need_score'],['Who Influences Work','influence','first_name','final_influence_score']] as [$title, $key, $nameKey, $scoreKey]): ?>
    <div class="panel">
      <div class="panel-title"><h2><?= htmlspecialchars($title) ?></h2><span class="status">Top 5</span></div>
      <div class="action-stack">
        <?php foreach ($operatingView[$key] as $item): ?>
          <?php $name = $key === 'influence' ? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')) : ($item[$nameKey] ?? 'Unknown'); ?>
          <article><span class="priority high"><?= (int)$item[$scoreKey] ?></span><h3><?= htmlspecialchars($name ?: 'Unknown') ?></h3><p><?= htmlspecialchars($item['region_name'] ?? 'National') ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>What Should We Do Next</h2><a class="btn secondary" href="/daily-brief">Daily Brief</a></div>
    <div class="action-stack"><?php foreach ($operatingView['nextActions'] as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span><h3><?= htmlspecialchars($action['action_title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_step']) ?></p></article><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Market Signals Ahead</h2><a class="btn secondary" href="/market-intelligence">Market Network</a></div>
    <div class="table-wrap"><table><thead><tr><th>Market</th><th>Theater</th><th>Readiness</th><th>Priority</th></tr></thead><tbody><?php foreach ($market['profiles'] as $profile): ?><tr><td><?= htmlspecialchars($profile['market']) ?></td><td><?= htmlspecialchars($profile['region_name'] ?? '') ?></td><td><?= (int)$profile['market_readiness_score'] ?></td><td><?= htmlspecialchars($profile['strategic_priority']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
