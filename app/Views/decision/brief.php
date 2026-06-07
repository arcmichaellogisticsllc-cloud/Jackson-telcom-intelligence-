<section class="page-header">
  <p class="eyebrow">Executive Daily Brief</p>
  <h1>What matters today.</h1>
  <p>Concise owner-facing brief. No raw data dumps, no automated outreach, and no automated publishing.</p>
</section>

<nav class="dash-tabs">
  <a href="/decision-support">Decision Support</a>
  <a class="active" href="/daily-brief">Executive Daily Brief</a>
  <a href="/decision-support/southeast">Mike</a>
  <a href="/decision-support/great-lakes">Ron</a>
  <a href="/decision-support/southwest">Shared Southwest</a>
</nav>

<section class="grid two">
  <?php foreach ($briefs as $regionName => $brief): ?>
    <article class="panel">
      <div class="panel-title">
        <div>
          <p class="eyebrow"><?= htmlspecialchars($regionName) ?></p>
          <h2><?= htmlspecialchars($regionName === 'Southeast' ? 'Mike' : ($regionName === 'Great Lakes' ? 'Ron' : ($regionName === 'Southwest' ? 'Mike/Ron Shared' : 'Admin'))) ?> brief</h2>
        </div>
        <?php if ($brief['scorecard']): ?><span class="score stable"><?= (int)$brief['scorecard']['overall_growth_score'] ?></span><?php endif; ?>
      </div>
      <?php if ($brief['scorecard']): ?>
        <p><strong>Focus:</strong> <?= htmlspecialchars($brief['scorecard']['recommended_focus']) ?></p>
        <p><strong>Blocker:</strong> <?= htmlspecialchars($brief['scorecard']['top_blocker']) ?></p>
      <?php endif; ?>
      <?php $command = $commandBriefs[$regionName] ?? null; ?>
      <?php if ($command): ?>
        <div class="mini-metrics">
          <div><span>Who Has Work</span><strong><?= (int)$command['metrics']['work'] ?></strong></div>
          <div><span>Who Has Capacity</span><strong><?= (int)$command['metrics']['capacity'] ?></strong></div>
          <div><span>Who Needs Work</span><strong><?= (int)$command['metrics']['need'] ?></strong></div>
          <div><span>Who Influences Work</span><strong><?= (int)$command['metrics']['influence'] ?></strong></div>
        </div>
        <div class="action-stack">
          <?php if ($command['work']): ?><div><span class="priority high">Who Has Work</span><h3><?= htmlspecialchars($command['work'][0]['organization_name'] ?? 'Work signal') ?></h3><p>Validate scope, decision makers, timing, and capacity requirements.</p></div><?php endif; ?>
          <?php if ($command['capacity']): ?><div><span class="priority high">Who Has Capacity</span><h3><?= htmlspecialchars($command['capacity'][0]['profile_name'] ?? 'Capacity provider') ?></h3><p><?= (int)$command['capacity'][0]['available_crews'] ?> available crews. Match against pursuits and capacity gaps.</p></div><?php endif; ?>
          <?php if ($command['need']): ?><div><span class="priority medium">Who Needs Work</span><h3><?= htmlspecialchars($command['need'][0]['organization_name'] ?? 'Need signal') ?></h3><p><?= htmlspecialchars($command['need'][0]['workload_status']) ?>. Call to determine availability and fit.</p></div><?php endif; ?>
          <?php if ($command['influence']): ?><div><span class="priority high">Who Influences Work</span><h3><?= htmlspecialchars(trim(($command['influence'][0]['first_name'] ?? '') . ' ' . ($command['influence'][0]['last_name'] ?? ''))) ?></h3><p><?= htmlspecialchars($command['influence'][0]['influence_role'] ?: 'Influence contact') ?>. Strengthen access and ask for intelligence.</p></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="action-stack">
        <?php foreach ($brief['topActions'] as $action): ?>
          <div>
            <span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span>
            <h3><?= htmlspecialchars($action['action_title']) ?></h3>
            <p><?= htmlspecialchars($action['recommended_next_step']) ?></p>
            <small><?= htmlspecialchars($action['owner']) ?> · Due <?= htmlspecialchars($action['due_date']) ?></small>
          </div>
        <?php endforeach; ?>
        <?php if (!$brief['topActions']): ?><p>No critical actions.</p><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
