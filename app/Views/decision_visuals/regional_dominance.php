<section class="page-header command-page-header"><p class="eyebrow">Where Should We Invest?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/regional-dominance">Regional Dominance</a><a href="/decision-visuals/work-vs-capacity">Work vs Capacity</a><a href="/decision-visuals/capacity-heatmap">Capacity Heat</a></nav>
<?php $why='Regional dominance combines relationships, capacity, work, influence, demand, competitive pressure, and operating rhythm into an investment decision.'; $recommended='Invest in the region with the strongest near-term upside or fix the weakest region blocker before expanding there.'; $next='Open the regional command view and assign the recommended investment action to the owner.'; $risk='Weak regions consume attention without producing work, while strong regions can be underfunded if not visible.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="grid two">
<?php foreach ($dominance as $row): ?>
  <article class="panel">
    <div class="panel-title"><h2><?= htmlspecialchars($row['region_name']) ?></h2><span class="score <?= strtolower($row['dominance_category'] ?: 'stable') ?>"><?= (int)$row['visual_score'] ?></span></div>
    <div class="mini-metrics">
      <div><span>Relationships</span><strong><?= (int)$row['relationship_strength_score'] ?></strong></div>
      <div><span>Capacity</span><strong><?= (int)$row['capacity_strength_score'] ?></strong></div>
      <div><span>Work</span><strong><?= (int)$row['opportunity_strength_score'] ?></strong></div>
      <div><span>Influence</span><strong><?= (int)$row['influence_strength_score'] ?></strong></div>
      <div><span>Demand</span><strong><?= (int)$row['demand_strength_score'] ?></strong></div>
      <div><span>Competition</span><strong><?= (int)$row['competitive_pressure_score'] ?></strong></div>
      <div><span>Rhythm</span><strong><?= (int)$row['operating_rhythm_score'] ?></strong></div>
      <div><span>Dominance</span><strong><?= (int)$row['regional_dominance_score'] ?></strong></div>
    </div>
    <div class="command-items"><div><strong>Why It Matters</strong><span><?= htmlspecialchars($row['why_it_matters']) ?></span></div><div><strong>Recommended Action</strong><span><?= htmlspecialchars($row['recommended_action']) ?></span></div><div><strong>Risk</strong><span><?= htmlspecialchars($row['top_risk'] ?: 'No top risk recorded.') ?></span></div></div>
    <p><a class="btn secondary" href="<?= htmlspecialchars($row['href']) ?>">Open Regional Command</a></p>
  </article>
<?php endforeach; ?>
</section>
