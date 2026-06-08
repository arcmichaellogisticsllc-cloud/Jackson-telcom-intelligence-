<section class="page-header command-page-header"><p class="eyebrow">What Is Likely To Happen?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/forecasts">Forecasts</a><a href="/forecasts">Executive Forecasts</a></nav>
<?php $why='Forecasts let leaders act before capacity gaps, competitive pressure, or market movement becomes urgent.'; $recommended='Prioritize 90-180 day risks with high confidence and clear recommended action.'; $next='Assign the highest-confidence forecast to the next weekly or monthly operating rhythm.'; $risk='Waiting for forecasts to become facts narrows options and raises cost.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="grid two">
  <?php foreach ($forecasts as $row): ?>
    <article class="panel">
      <div class="panel-title"><h2><?= htmlspecialchars($row['forecast_title']) ?></h2><span class="status"><?= htmlspecialchars($row['forecast_window']) ?></span></div>
      <div class="mini-metrics">
        <div><span>Current</span><strong><?= (int)$row['current_state'] ?></strong></div>
        <div><span>Projected</span><strong><?= (int)$row['projected_state'] ?></strong></div>
        <div><span>Gap / Risk</span><strong><?= (int)$row['expected_gap'] ?></strong></div>
        <div><span>Confidence</span><strong><?= (int)$row['confidence_score'] ?></strong></div>
      </div>
      <div class="command-items"><div><strong>Why It Matters</strong><span><?= htmlspecialchars($row['why_it_matters']) ?></span></div><div><strong>Recommended Action</strong><span><?= htmlspecialchars($row['recommended_action']) ?></span></div></div>
      <p><a class="btn secondary" href="<?= htmlspecialchars($row['href']) ?>">Open Forecast Evidence</a></p>
    </article>
  <?php endforeach; ?>
</section>
