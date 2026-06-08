<section class="page-header command-page-header"><p class="eyebrow">Where Does Money Leak?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/opportunity-flow">Opportunity Flow</a><a href="/pursuits">Pursuits</a><a href="/preconstruction">Preconstruction</a><a href="/syncerp-integration">SyncERP Packages</a></nav>
<?php $why='Opportunity flow shows how much value survives from available work through relationship access, capacity feasibility, pursuit, bid readiness, award, and handoff.'; $recommended='Fix the stage with the highest value leakage before adding more raw opportunity volume.'; $next='Open the leaking stage workspace and create a decision-support action for its recommended fix.'; $risk='Money leaks silently when pursuit, capacity, relationship, and handoff stages are not visible together.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Flow / Leakage Diagram</h2><span class="status">Value Surviving Each Stage</span></div>
  <div class="command-widget-grid cols-3">
    <?php foreach ($opportunityFlow as $row): ?>
      <a class="command-widget" href="<?= htmlspecialchars($row['href']) ?>">
        <h2><?= htmlspecialchars($row['stage']) ?></h2>
        <div class="mini-metrics">
          <div><span>Count</span><strong><?= (int)$row['count'] ?></strong></div>
          <div><span>Value</span><strong>$<?= number_format((float)$row['estimated_value'] / 1000000, 1) ?>M</strong></div>
          <div><span>Conversion</span><strong><?= (int)$row['conversion_percentage'] ?>%</strong></div>
          <div><span>Leakage</span><strong><?= htmlspecialchars($row['leakage_reason']) ?></strong></div>
        </div>
        <p><?= htmlspecialchars($row['why_it_matters']) ?></p>
        <div class="command-items"><div><strong>Recommended Fix</strong><span><?= htmlspecialchars($row['recommended_action']) ?></span></div></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
