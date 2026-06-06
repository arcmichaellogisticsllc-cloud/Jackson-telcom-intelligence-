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
