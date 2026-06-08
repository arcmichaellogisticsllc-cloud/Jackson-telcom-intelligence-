<?php
$allActions = [];
foreach ($briefs as $brief) {
    foreach ($brief['topActions'] as $action) {
        $allActions[] = $action;
    }
}
usort($allActions, fn($a, $b) => ((int)($b['decision_score'] ?? 0)) <=> ((int)($a['decision_score'] ?? 0)));
$summary = ['work' => [], 'capacity' => [], 'need' => [], 'influence' => []];
foreach ($commandBriefs as $regionName => $command) {
    foreach (['work','capacity','need','influence'] as $type) {
        if (!empty($command[$type][0])) {
            $row = $command[$type][0];
            $summary[$type][] = ['region' => $regionName, 'row' => $row];
        }
    }
}
?>
<section class="page-header command-page-header">
  <p class="eyebrow">Executive Brief</p>
  <h1>What should Jackson Telcom do today?</h1>
  <p>One screen for who has work, who has capacity, who needs work, who influences work, and the actions that should move before anything else.</p>
</section>

<nav class="dash-tabs">
  <a href="/">Command Center</a>
  <a href="/decision-support">Decision Support</a>
  <a class="active" href="/daily-brief">Executive Brief</a>
  <a href="/command/southeast">Mike Mode</a>
  <a href="/command/great-lakes">Ron Mode</a>
  <a href="/command/southwest">Shared Southwest</a>
</nav>

<?php require __DIR__ . '/../components/executive_doctrine.php'; ?>

<?php
$why = 'The executive brief prevents operators from drowning in modules by showing only the doctrine categories and top actions.';
$recommended = 'Use this page at the start of every operating day before opening module-specific screens.';
$next = 'Pick the first priority, complete or delegate it, then capture the outcome in the linked record.';
require __DIR__ . '/../components/action_first.php';
?>

<?php $priorityActions = $allActions; require __DIR__ . '/../components/todays_priorities.php'; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Doctrine Review</h2><span class="status">Action Filter</span></div>
    <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Score</th><th>Strongest</th><th>Weakest</th><th>Improve</th></tr></thead><tbody><?php foreach (($doctrineData['health'] ?? []) as $row): ?><tr><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><span class="score <?= strtolower($row['doctrine_category']) ?>"><?= (int)$row['doctrine_compliance_score'] ?></span></td><td><?= htmlspecialchars($row['strongest_alignment']) ?></td><td><?= htmlspecialchars($row['weakest_alignment']) ?></td><td><?= htmlspecialchars($row['top_improvement_area']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Weak Alignment</h2><span class="status">Review Or Archive</span></div>
    <div class="activity-list"><?php foreach (($doctrineData['weak'] ?? []) as $row): ?><div><strong><?= htmlspecialchars($row['entity_type']) ?> #<?= (int)$row['entity_id'] ?> · <?= (int)$row['overall_doctrine_alignment_score'] ?></strong><span><?= htmlspecialchars($row['region_name'] ?? 'National') ?> · <?= htmlspecialchars($row['owner']) ?></span><small><?= htmlspecialchars($row['recommended_action']) ?></small></div><?php endforeach; ?><?php if (empty($doctrineData['weak'])): ?><p>No weak doctrine items are active.</p><?php endif; ?></div>
  </div>
</section>

<section class="command-widget-grid cols-4">
  <article class="command-widget">
    <p class="eyebrow">Who Has Work</p>
    <h2>Work Ready</h2>
    <div class="command-items">
      <?php foreach (array_slice($summary['work'], 0, 5) as $item): ?>
        <div><strong><?= htmlspecialchars($item['row']['organization_name'] ?? 'Work signal') ?></strong><span><?= htmlspecialchars($item['region']) ?> · readiness <?= (int)($item['row']['work_readiness_score'] ?? 0) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$summary['work']): ?><p>No work-ready intelligence is active.</p><?php endif; ?>
    </div>
  </article>
  <article class="command-widget">
    <p class="eyebrow">Who Has Capacity</p>
    <h2>Capacity Available</h2>
    <div class="command-items">
      <?php foreach (array_slice($summary['capacity'], 0, 5) as $item): ?>
        <div><strong><?= htmlspecialchars($item['row']['profile_name'] ?? 'Capacity provider') ?></strong><span><?= htmlspecialchars($item['region']) ?> · <?= (int)($item['row']['available_crews'] ?? 0) ?> crews</span></div>
      <?php endforeach; ?>
      <?php if (!$summary['capacity']): ?><p>No deployable capacity intelligence is active.</p><?php endif; ?>
    </div>
  </article>
  <article class="command-widget">
    <p class="eyebrow">Who Needs Work</p>
    <h2>Capacity Seeking Work</h2>
    <div class="command-items">
      <?php foreach (array_slice($summary['need'], 0, 5) as $item): ?>
        <div><strong><?= htmlspecialchars($item['row']['organization_name'] ?? 'Need signal') ?></strong><span><?= htmlspecialchars($item['region']) ?> · <?= htmlspecialchars($item['row']['workload_status'] ?? 'Unknown') ?></span></div>
      <?php endforeach; ?>
      <?php if (!$summary['need']): ?><p>No work-seeking capacity intelligence is active.</p><?php endif; ?>
    </div>
  </article>
  <article class="command-widget">
    <p class="eyebrow">Who Influences Work</p>
    <h2>Influence Network</h2>
    <div class="command-items">
      <?php foreach (array_slice($summary['influence'], 0, 5) as $item): ?>
        <div><strong><?= htmlspecialchars(trim(($item['row']['first_name'] ?? '') . ' ' . ($item['row']['last_name'] ?? '')) ?: 'Influence contact') ?></strong><span><?= htmlspecialchars($item['region']) ?> · score <?= (int)($item['row']['final_influence_score'] ?? 0) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$summary['influence']): ?><p>No influence intelligence is active.</p><?php endif; ?>
    </div>
  </article>
</section>

<section class="grid two">
  <?php foreach ($briefs as $regionName => $brief): ?>
    <article class="panel">
      <div class="panel-title">
        <div>
          <p class="eyebrow"><?= htmlspecialchars($regionName) ?></p>
          <h2><?= htmlspecialchars($regionName === 'Southeast' ? 'Mike' : ($regionName === 'Great Lakes' ? 'Ron' : ($regionName === 'Southwest' ? 'Mike/Ron Shared' : 'Admin'))) ?> operating focus</h2>
        </div>
        <?php if ($brief['scorecard']): ?><span class="score stable"><?= (int)$brief['scorecard']['overall_growth_score'] ?></span><?php endif; ?>
      </div>
      <?php if ($brief['scorecard']): ?>
        <p><strong>Recommended focus:</strong> <?= htmlspecialchars($brief['scorecard']['recommended_focus']) ?></p>
        <p><strong>Top blocker:</strong> <?= htmlspecialchars($brief['scorecard']['top_blocker']) ?></p>
      <?php endif; ?>
      <div class="action-stack">
        <?php foreach (array_slice($brief['topActions'], 0, 3) as $action): ?>
          <article>
            <span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span>
            <h3><?= htmlspecialchars($action['action_title']) ?></h3>
            <p><?= htmlspecialchars($action['recommended_next_step']) ?></p>
            <small><?= htmlspecialchars($action['owner']) ?> · Due <?= htmlspecialchars($action['due_date']) ?></small>
          </article>
        <?php endforeach; ?>
        <?php if (!$brief['topActions']): ?><p>No critical actions.</p><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
