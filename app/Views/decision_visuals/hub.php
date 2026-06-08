<section class="page-header command-page-header">
  <p class="eyebrow">Executive Visual Decision Layer</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a class="active" href="/decision-visuals">Visual Hub</a>
  <a href="/decision-visuals/regional-dominance">Regional Dominance</a>
  <a href="/decision-visuals/work-vs-capacity">Work vs Capacity</a>
  <a href="/decision-visuals/account-health">Account Health</a>
  <a href="/decision-visuals/ecosystem-map">Ecosystem Map</a>
  <a href="/decision-visuals/capacity-heatmap">Capacity Heat</a>
  <a href="/decision-visuals/workforce-heatmap">Workforce Heat</a>
  <a href="/decision-visuals/competitive-pressure">Competitive Pressure</a>
  <a href="/decision-visuals/forecasts">Forecasts</a>
  <a href="/decision-visuals/opportunity-flow">Opportunity Flow</a>
  <a href="/decision-visuals/scorecards">Scorecards</a>
</nav>

<?php
$why = 'Executives need visual tools that compress intelligence into investment, recruiting, pursuit, relationship, and avoidance decisions.';
$recommended = 'Start with the top visual alerts, then open the specific drill-down that matches today\'s executive decision.';
$next = 'Pick one visual, follow its recommended action, and drill into the supporting package, record, or workspace.';
$risk = 'If visuals do not produce action, they become reporting noise and delay money, capacity, relationship, and opportunity decisions.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="panel command-priorities">
  <div class="panel-title"><h2>Top Visual Alerts</h2><a class="btn secondary" href="/decision-support">Open Decision Support</a></div>
  <div class="priority-list">
    <?php foreach ($alerts as $alert): ?>
      <article>
        <span class="priority high">Decision Alert · <?= (int)$alert['score'] ?></span>
        <h3><?= htmlspecialchars($alert['title']) ?></h3>
        <p><?= htmlspecialchars($alert['why']) ?></p>
        <small><?= htmlspecialchars($alert['action']) ?></small>
        <p><a class="btn secondary" href="<?= htmlspecialchars($alert['href']) ?>">Open Visual</a></p>
      </article>
    <?php endforeach; ?>
    <?php if (!$alerts): ?><article><h3>No visual alerts</h3><p>Current visual layer has no critical decision alert.</p></article><?php endif; ?>
  </div>
</section>

<section class="command-widget-grid cols-3">
  <?php foreach ($cards as $card): ?>
    <a class="command-widget" href="<?= htmlspecialchars($card['href']) ?>">
      <span class="status">Decision Visual</span>
      <h2><?= htmlspecialchars($card['title']) ?></h2>
      <p><strong><?= htmlspecialchars($card['decision']) ?></strong></p>
      <p><?= htmlspecialchars($card['why']) ?></p>
      <div class="command-items"><div><strong>Recommended Action</strong><span><?= htmlspecialchars($card['action']) ?></span></div></div>
    </a>
  <?php endforeach; ?>
</section>
