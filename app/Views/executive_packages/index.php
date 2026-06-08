<section class="page-header command-page-header">
  <p class="eyebrow">Executive Intelligence Packaging</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?> Executives see decision packages, not raw module data.</p>
</section>

<nav class="dash-tabs">
  <a class="active" href="/executive-packages">All Packages</a>
  <a href="/executive-packages/southeast">Southeast</a>
  <a href="/executive-packages/great-lakes">Great Lakes</a>
  <a href="/executive-packages/southwest">Southwest</a>
  <a href="/executive-briefs">Briefs</a>
  <a href="/executive-os">Executive OS</a>
  <form method="post" action="/executive-packages/rebuild"><input type="hidden" name="return_to" value="/executive-packages"><button class="btn secondary">Rebuild Packages</button></form>
</nav>

<?php
$why = 'Packages convert signals, relationships, capacity, demand, pursuits, and preconstruction into executive decisions.';
$recommended = 'Review Top 5 Actions, then inspect risks, opportunities, and strategic packages that need a decision.';
$next = 'Open a package, use one action, then mark it reviewed or complete with outcome notes.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics command-metrics">
  <div><span>Total Packages</span><strong><?= (int)$metrics['packages'] ?></strong></div>
  <div><span>Work Ready</span><strong><?= (int)$metrics['work'] ?></strong></div>
  <div><span>Capacity</span><strong><?= (int)$metrics['capacity'] ?></strong></div>
  <div><span>Influence</span><strong><?= (int)$metrics['influence'] ?></strong></div>
  <div><span>Risks</span><strong><?= (int)$metrics['risks'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top 5 Actions</h2><a class="btn secondary" href="/executive-briefs">Briefs</a></div>
    <div class="action-stack">
      <?php foreach ($topActions as $package): ?>
        <article>
          <span class="priority <?= strtolower($package['package_type']) ?>"><?= htmlspecialchars($package['package_type']) ?> · <?= (int)$package['impact_score'] + (int)$package['urgency_score'] ?></span>
          <h3><a href="/executive-packages/detail?id=<?= (int)$package['id'] ?>"><?= htmlspecialchars($package['package_title']) ?></a></h3>
          <p><?= htmlspecialchars($package['recommended_action']) ?></p>
          <small><?= htmlspecialchars($package['owner']) ?> · <?= htmlspecialchars($package['region_name'] ?? 'National') ?></small>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top 5 Risks</h2><span class="status">Risk Of Inaction</span></div>
    <div class="table-wrap"><table><thead><tr><th>Risk</th><th>Action</th><th>Owner</th></tr></thead><tbody><?php foreach ($risks as $package): ?><tr><td><a href="/executive-packages/detail?id=<?= (int)$package['id'] ?>"><?= htmlspecialchars($package['package_title']) ?></a><br><small><?= htmlspecialchars($package['risk_of_inaction']) ?></small></td><td><?= htmlspecialchars($package['recommended_action']) ?></td><td><?= htmlspecialchars($package['owner']) ?></td></tr><?php endforeach; ?><?php if (!$risks): ?><tr><td colspan="3">No packaged risks.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</section>

<?php
$widgets = [
    ['eyebrow' => 'Work Ready', 'title' => 'Who has work?', 'score' => $metrics['work'], 'summary' => 'Executive work packages with customer, opportunity, strategic alignment, relationship fit, and capacity fit.', 'href' => '/executive-packages', 'cta' => 'Review Work', 'items' => array_map(fn($row) => ['title' => $row['package_title'], 'meta' => ($row['region_name'] ?? 'National') . ' · impact ' . (int)$row['impact_score']], $work)],
    ['eyebrow' => 'Capacity Available', 'title' => 'Who has capacity?', 'score' => $metrics['capacity'], 'summary' => 'Capacity packages with provider, crews, mobilization, trust score, and capacity contribution.', 'href' => '/capacity-radar', 'cta' => 'Review Capacity', 'items' => array_map(fn($row) => ['title' => $row['package_title'], 'meta' => ($row['region_name'] ?? 'National') . ' · urgency ' . (int)$row['urgency_score']], $capacity)],
    ['eyebrow' => 'Capacity Seeking Work', 'title' => 'Who needs work?', 'score' => $metrics['need'], 'summary' => 'Need packages for underutilized contractors and available capacity.', 'href' => '/hunting-lists', 'cta' => 'Review Need', 'items' => array_map(fn($row) => ['title' => $row['package_title'], 'meta' => ($row['region_name'] ?? 'National') . ' · confidence ' . (int)$row['confidence_score']], $need)],
    ['eyebrow' => 'Influence Network', 'title' => 'Who influences work?', 'score' => $metrics['influence'], 'summary' => 'Influence packages with authority, trust, objectives, and next action.', 'href' => '/relationship-graph', 'cta' => 'Review Influence', 'items' => array_map(fn($row) => ['title' => $row['package_title'], 'meta' => ($row['region_name'] ?? 'National') . ' · impact ' . (int)$row['impact_score']], $influence)],
];
$columns = 4;
require __DIR__ . '/../components/command_widgets.php';
?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top 5 Opportunities</h2><a class="btn secondary" href="/pursuits">Pursuits</a></div>
    <div class="table-wrap"><table><thead><tr><th>Opportunity Package</th><th>Decision</th><th>Risk</th></tr></thead><tbody><?php foreach ($opportunities as $package): ?><tr><td><a href="/executive-packages/detail?id=<?= (int)$package['id'] ?>"><?= htmlspecialchars($package['package_title']) ?></a><br><small><?= htmlspecialchars($package['executive_summary']) ?></small></td><td><?= htmlspecialchars($package['decision_required']) ?></td><td><?= htmlspecialchars($package['risk_of_inaction']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Strategic Recommendations</h2><a class="btn secondary" href="/strategic-review">Strategic Review</a></div>
    <div class="table-wrap"><table><thead><tr><th>Package</th><th>Action</th><th>Owner</th></tr></thead><tbody><?php foreach ($strategic as $package): ?><tr><td><a href="/executive-packages/detail?id=<?= (int)$package['id'] ?>"><?= htmlspecialchars($package['package_title']) ?></a><br><small><?= htmlspecialchars($package['executive_summary']) ?></small></td><td><?= htmlspecialchars($package['recommended_action']) ?></td><td><?= htmlspecialchars($package['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
