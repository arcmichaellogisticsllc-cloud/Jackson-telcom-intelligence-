<section class="page-header">
  <p class="eyebrow">Pursuit Detail</p>
  <h1><?= htmlspecialchars($opportunity['name']) ?></h1>
  <p><?= htmlspecialchars($opportunity['region_name'] ?? 'National') ?> · <?= htmlspecialchars($opportunity['classification'] ?? 'Unclassified') ?> · <?= htmlspecialchars($opportunity['recommended_decision'] ?? 'Monitor') ?></p>
</section>

<nav class="dash-tabs">
  <a href="/pursuits">Pursuit Board</a>
  <a href="/opportunities">Opportunities</a>
  <a href="/capacity-radar">Capacity Radar</a>
  <a href="/relationship-graph">Relationship Graph</a>
</nav>

<section class="metrics">
  <div><span>Strategic Alignment</span><strong><?= (int)($opportunity['strategic_alignment_score'] ?? 0) ?></strong></div>
  <div><span>Pursuit Score</span><strong><?= (int)($opportunity['pursuit_score'] ?? 0) ?></strong></div>
  <div><span>Relationship Fit</span><strong><?= (int)($opportunity['relationship_fit_score'] ?? 0) ?></strong></div>
  <div><span>Capacity Fit</span><strong><?= (int)($opportunity['capacity_fit_score'] ?? 0) ?></strong></div>
  <div><span>Risk</span><strong><?= (int)($opportunity['risk_score'] ?? 0) ?></strong></div>
</section>

<section class="grid two">
  <article class="panel">
    <div class="panel-title"><h2>Why This Decision</h2><span class="priority high"><?= htmlspecialchars($opportunity['recommended_decision'] ?? 'Monitor') ?></span></div>
    <p><?= htmlspecialchars($opportunity['decision_reason'] ?? $opportunity['reason'] ?? '') ?></p>
    <h3>Next Best Action</h3>
    <p><?= htmlspecialchars($opportunity['next_best_action'] ?? '') ?></p>
    <h3>Watchlist</h3>
    <p><?= htmlspecialchars($opportunity['watch_status'] ?? 'Watch') ?> · Review <?= htmlspecialchars($opportunity['next_review_date'] ?? '') ?></p>
  </article>
  <article class="panel">
    <div class="panel-title"><h2>Opportunity Profile</h2><span class="status"><?= htmlspecialchars($opportunity['stage'] ?? 'Intelligence') ?></span></div>
    <div class="mini-metrics">
      <div><span>Type</span><strong><?= htmlspecialchars($opportunity['opportunity_type'] ?? '') ?></strong></div>
      <div><span>Customer</span><strong><?= htmlspecialchars($opportunity['customer_type'] ?? '') ?></strong></div>
      <div><span>Funding</span><strong><?= htmlspecialchars($opportunity['funding_source'] ?? '') ?></strong></div>
      <div><span>Value</span><strong>$<?= number_format((float)($opportunity['estimated_value'] ?? 0)) ?></strong></div>
    </div>
    <p><?= htmlspecialchars($opportunity['notes'] ?? '') ?></p>
  </article>
</section>

<section class="grid two">
  <article class="panel">
    <div class="panel-title"><h2>Relationship Fit</h2><span class="score stable"><?= (int)($opportunity['relationship_fit_score'] ?? 0) ?></span></div>
    <p><?= htmlspecialchars($opportunity['relationship_gap'] ?: 'Relationship access appears sufficient for current pursuit stage.') ?></p>
    <p><strong>Decision makers:</strong> <?= htmlspecialchars($opportunity['decision_makers'] ?? '') ?></p>
  </article>
  <article class="panel">
    <div class="panel-title"><h2>Capacity Fit</h2><span class="score stable"><?= (int)($opportunity['capacity_fit_score'] ?? 0) ?></span></div>
    <p><?= htmlspecialchars($opportunity['capacity_gap'] ?: 'Capacity fit appears sufficient for current pursuit stage.') ?></p>
    <p><strong>Capacity required:</strong> <?= (int)($opportunity['capacity_required'] ?? 0) ?> crews</p>
  </article>
</section>
