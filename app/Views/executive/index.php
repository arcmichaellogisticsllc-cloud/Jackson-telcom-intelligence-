<?php
$metrics = $metrics ?? [];
$dominanceRows = $dominance ?? [];
$strategicWidgets = [
    [
        'eyebrow' => 'Communications',
        'title' => 'What is happening?',
        'score' => $metrics['communications'] ?? 0,
        'summary' => 'Calls, meetings, notes, follow-ups, and human-reviewed message drafts tied to relationships, capacity, hunts, targets, and opportunities.',
        'href' => '/communications',
        'cta' => 'Open Communications',
        'items' => array_map(fn($row) => ['title' => $row['summary'] ?? 'Communication', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['communication_type'] ?? '')], $communications ?? []),
    ],
    [
        'eyebrow' => 'Network Intelligence',
        'title' => 'How does work flow?',
        'score' => $metrics['network_edges'] ?? 0,
        'summary' => 'Utility, engineering, prime, subcontractor, and influencer relationships that explain who moves work to whom.',
        'href' => '/network-intelligence',
        'cta' => 'Open Network',
        'items' => array_map(fn($row) => ['title' => ($row['from_org'] ?? 'Unknown') . ' -> ' . ($row['to_org'] ?? 'Unknown'), 'meta' => ($row['region_name'] ?? 'National') . ' · influence ' . (int)($row['network_influence_score'] ?? 0)], $network ?? []),
    ],
    [
        'eyebrow' => 'Forecasts',
        'title' => 'What is likely to happen?',
        'score' => $metrics['forecasts'] ?? 0,
        'summary' => 'Capacity, opportunity, relationship, demand, and regional forecasts over 30, 90, 180, and 365 days.',
        'href' => '/forecasts',
        'cta' => 'Open Forecasts',
        'items' => array_map(fn($row) => ['title' => $row['forecast_title'] ?? 'Forecast', 'meta' => ($row['forecast_window'] ?? '') . ' · ' . ($row['trend'] ?? '') . ' · confidence ' . (int)($row['confidence_score'] ?? 0)], $forecasts ?? []),
    ],
    [
        'eyebrow' => 'Strategic Accounts',
        'title' => 'Where should we invest?',
        'score' => $metrics['strategic_accounts'] ?? 0,
        'summary' => 'Comcast, Charter, Frontier, AT&T, Windstream, cooperatives, municipal systems, primes, and strategic account coverage.',
        'href' => '/strategic-accounts',
        'cta' => 'Open Accounts',
        'items' => array_map(fn($row) => ['title' => $row['account_name'] ?? 'Strategic account', 'meta' => ($row['region_name'] ?? 'National') . ' · strategic ' . (int)($row['strategic_score'] ?? 0)], $accounts ?? []),
    ],
];
?>
<section class="page-header command-page-header">
  <p class="eyebrow">Executive Operating System</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a class="<?= $viewMode === 'home' ? 'active' : '' ?>" href="/executive-os">Executive OS</a>
  <a href="/">Command Center</a>
  <a href="/communications">Communications</a>
  <a class="<?= $viewMode === 'network' ? 'active' : '' ?>" href="/network-intelligence">Network</a>
  <a class="<?= $viewMode === 'forecasts' ? 'active' : '' ?>" href="/forecasts">Forecasts</a>
  <a class="<?= $viewMode === 'ownership' ? 'active' : '' ?>" href="/ownership-matrix">Ownership</a>
  <a class="<?= $viewMode === 'accounts' ? 'active' : '' ?>" href="/strategic-accounts">Strategic Accounts</a>
  <a class="<?= $viewMode === 'review' ? 'active' : '' ?>" href="/strategic-review">Strategic Review</a>
</nav>

<?php
$why = 'This layer gives leadership visibility across communication, network influence, forecasts, ownership, accounts, dominance, and strategic recommendations.';
$recommended = 'Use strategic recommendations and forecasts to decide where to invest relationship time, capacity recruiting, and pursuit attention.';
$next = 'Pick the highest priority recommendation, assign ownership, and turn it into a communication, hunt, or decision-support action.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics command-metrics">
  <div><span>Dominance Score</span><strong><?= (int)($metrics['dominance_score'] ?? 0) ?></strong></div>
  <div><span>Network Edges</span><strong><?= (int)($metrics['network_edges'] ?? 0) ?></strong></div>
  <div><span>Forecasts</span><strong><?= (int)($metrics['forecasts'] ?? 0) ?></strong></div>
  <div><span>Strategic Accounts</span><strong><?= (int)($metrics['strategic_accounts'] ?? 0) ?></strong></div>
  <div><span>Strategic Actions</span><strong><?= (int)($metrics['strategic_recommendations'] ?? 0) ?></strong></div>
</section>

<?php $widgets = $strategicWidgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Strategic Recommendations</h2><a class="btn secondary" href="/strategic-review">Strategic Review</a></div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Recommendation</th><th>Owner</th><th>Expected Impact</th></tr></thead><tbody><?php foreach ($recommendations as $item): ?><tr><td><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span></td><td><strong><?= htmlspecialchars($item['recommendation_title']) ?></strong><br><small><?= htmlspecialchars($item['recommended_action']) ?></small></td><td><?= htmlspecialchars($item['owner']) ?><br><small><?= htmlspecialchars($item['region_name'] ?? 'National') ?></small></td><td><?= htmlspecialchars($item['expected_impact']) ?></td></tr><?php endforeach; ?><?php if (!$recommendations): ?><tr><td colspan="4">No open strategic recommendations.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Regional Dominance</h2><a class="btn secondary" href="/strategic-review">Review Regions</a></div>
    <div class="table-wrap"><table><thead><tr><th>Region</th><th>Score</th><th>Category</th><th>Invest Next</th><th>Risk</th></tr></thead><tbody><?php foreach ($dominanceRows as $row): ?><tr><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><?= (int)$row['regional_dominance_score'] ?></td><td><span class="status"><?= htmlspecialchars($row['dominance_category']) ?></span></td><td><?= htmlspecialchars($row['top_investment']) ?></td><td><?= htmlspecialchars($row['top_risk']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<?php if ($viewMode === 'network'): ?>
  <section class="grid two">
    <div class="panel">
      <h2>Ecosystem Maps</h2>
      <div class="dash-tabs"><a href="/ecosystem/southeast">Southeast Ecosystem</a><a href="/ecosystem/great-lakes">Great Lakes Ecosystem</a><a href="/ecosystem/southwest">Southwest Ecosystem</a></div>
      <div class="table-wrap"><table><thead><tr><th>Relationship</th><th>Type</th><th>Strength</th><th>Trust</th><th>Influence</th></tr></thead><tbody><?php foreach ($network as $row): ?><tr><td><?= htmlspecialchars($row['from_org'] ?? 'Unknown') ?> -> <?= htmlspecialchars($row['to_org'] ?? 'Unknown') ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['relationship_type']) ?></td><td><?= (int)$row['strength_score'] ?></td><td><?= (int)$row['trust_score'] ?></td><td><?= (int)$row['network_influence_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
    <div class="panel">
      <h2>Ownership Matrix</h2>
      <div class="table-wrap"><table><thead><tr><th>Record</th><th>Primary</th><th>Secondary</th><th>Reason</th></tr></thead><tbody><?php foreach ($ownership as $row): ?><tr><td><?= htmlspecialchars($row['record_type']) ?> #<?= (int)$row['record_id'] ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['primary_owner']) ?></td><td><?= htmlspecialchars($row['secondary_owner'] ?? '') ?></td><td><?= htmlspecialchars($row['ownership_reason']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </section>
<?php endif; ?>

<?php if (in_array($viewMode, ['forecasts','review'], true)): ?>
  <section class="panel">
    <div class="panel-title"><h2>Forecast Dashboards</h2><a class="btn secondary" href="/forecasts">Forecast Engine</a></div>
    <div class="table-wrap"><table><thead><tr><th>Window</th><th>Forecast</th><th>Value</th><th>Confidence</th><th>Action</th></tr></thead><tbody><?php foreach ($forecasts as $row): ?><tr><td><?= htmlspecialchars($row['forecast_window']) ?></td><td><strong><?= htmlspecialchars($row['forecast_title']) ?></strong><br><small><?= htmlspecialchars($row['forecast_type']) ?> · <?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= number_format((float)$row['forecast_value']) ?></td><td><?= (int)$row['confidence_score'] ?> · <?= htmlspecialchars($row['trend']) ?></td><td><?= htmlspecialchars($row['recommended_action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </section>
<?php endif; ?>

<?php if (in_array($viewMode, ['accounts','review'], true)): ?>
  <section class="panel">
    <div class="panel-title"><h2>Strategic Account Coverage</h2><a class="btn secondary" href="/strategic-accounts">Strategic Accounts</a></div>
    <div class="table-wrap"><table><thead><tr><th>Account</th><th>Type</th><th>Strategic</th><th>Relationship</th><th>Opportunity</th><th>Next Best Action</th></tr></thead><tbody><?php foreach ($accounts as $row): ?><tr><td><?= htmlspecialchars($row['account_name']) ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['account_type']) ?></td><td><?= (int)$row['strategic_score'] ?></td><td><?= (int)$row['relationship_coverage_score'] ?></td><td><?= (int)$row['opportunity_volume_score'] ?></td><td><?= htmlspecialchars($row['next_best_action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </section>
<?php endif; ?>

<?php if (in_array($viewMode, ['ownership','review'], true)): ?>
  <section class="panel">
    <div class="panel-title"><h2>Territory Ownership Matrix</h2><a class="btn secondary" href="/ownership-matrix">Ownership</a></div>
    <div class="table-wrap"><table><thead><tr><th>Record</th><th>Theater</th><th>Primary Owner</th><th>Secondary Owner</th><th>Status</th></tr></thead><tbody><?php foreach ($ownership as $row): ?><tr><td><?= htmlspecialchars($row['record_type']) ?> #<?= (int)$row['record_id'] ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= htmlspecialchars($row['primary_owner']) ?></td><td><?= htmlspecialchars($row['secondary_owner'] ?? '') ?></td><td><?= htmlspecialchars($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </section>
<?php endif; ?>

<?php if ($viewMode === 'review'): ?>
  <section class="panel">
    <div class="panel-title">
      <div>
        <p class="eyebrow">Quarterly Strategic Review</p>
        <h2>Executive questions</h2>
      </div>
      <span class="status">Strategic Mode</span>
    </div>
    <div class="module-grid">
      <article><h3>What worked?</h3><p>Review Intelligence Warehouse outcomes, high-performing relationships, converted hunts, and strong demand channels.</p></article>
      <article><h3>What failed?</h3><p>Review lost pursuits, stale communications, weak channels, and recommendations that stayed unresolved.</p></article>
      <article><h3>What should we invest in?</h3><p>Use regional dominance, strategic accounts, and forecasts to decide where leadership attention goes next.</p></article>
      <article><h3>What should we recruit?</h3><p>Use capacity forecasts and capacity gaps to choose subcontractor, workforce, and equipment hunting priorities.</p></article>
      <article><h3>What should we expand?</h3><p>Expand markets where work, capacity, influence, and demand are all rising together.</p></article>
      <article><h3>What should we avoid?</h3><p>Avoid low-alignment pursuits, weak-margin work, and regions where capacity or influence cannot support execution.</p></article>
    </div>
  </section>
<?php endif; ?>
