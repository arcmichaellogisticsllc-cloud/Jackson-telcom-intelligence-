<section class="page-header">
  <p class="eyebrow">Decision Support Layer V2</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?> Daily Actions are the prioritized executive layer above system recommendations.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= !$regionId ? 'active' : '' ?>" href="/decision-support">National Decision Support</a>
  <a href="/decision-support/southeast">Southeast</a>
  <a href="/decision-support/great-lakes">Great Lakes</a>
  <a href="/decision-support/southwest">Southwest</a>
  <a href="/daily-brief">Executive Daily Brief</a>
</nav>

<section class="metrics">
  <div><span>Open Daily Actions</span><strong><?= (int)$metrics['top_actions'] ?></strong></div>
  <div><span>Growth Blockers</span><strong><?= (int)$metrics['critical_blockers'] ?></strong></div>
  <div><span>Pursue Decisions</span><strong><?= (int)$metrics['pursue'] ?></strong></div>
  <div><span>Avoid Warnings</span><strong><?= (int)$metrics['avoid'] ?></strong></div>
  <div><span>Crews to Recruit</span><strong><?= (int)$metrics['recruitment_needs'] ?></strong></div>
</section>

<?php if ($scorecard): ?>
<section class="panel">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Regional Readiness</p>
      <h2><?= htmlspecialchars($scorecard['region_name'] ?? 'National') ?> growth score: <?= (int)$scorecard['overall_growth_score'] ?></h2>
    </div>
    <span class="score <?= (int)$scorecard['overall_growth_score'] >= 75 ? 'strong' : ((int)$scorecard['overall_growth_score'] >= 55 ? 'stable' : 'critical') ?>"><?= (int)$scorecard['overall_growth_score'] >= 75 ? 'Ready' : ((int)$scorecard['overall_growth_score'] >= 55 ? 'Watch' : 'Blocked') ?></span>
  </div>
  <p><?= htmlspecialchars($scorecard['summary']) ?></p>
  <div class="mini-metrics">
    <div><span>Capacity</span><strong><?= (int)$scorecard['capacity_score'] ?></strong></div>
    <div><span>Relationship</span><strong><?= (int)$scorecard['relationship_score'] ?></strong></div>
    <div><span>Demand</span><strong><?= (int)$scorecard['demand_score'] ?></strong></div>
    <div><span>Signal Quality</span><strong><?= (int)$scorecard['signal_quality_score'] ?></strong></div>
    <div><span>Risk</span><strong><?= (int)$scorecard['risk_score'] ?></strong></div>
  </div>
  <p><strong>Top blocker:</strong> <?= htmlspecialchars($scorecard['top_blocker']) ?></p>
  <p><strong>Recommended focus:</strong> <?= htmlspecialchars($scorecard['recommended_focus']) ?></p>
</section>
<?php endif; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>What Matters Today</h2><a class="btn secondary" href="/recommendations">System Findings</a></div>
    <div class="action-stack">
      <?php foreach ($topActions as $action): ?>
        <article>
          <span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span>
          <h3><?= htmlspecialchars($action['action_title']) ?></h3>
          <p><?= htmlspecialchars($action['reason']) ?></p>
          <p><strong>Next:</strong> <?= htmlspecialchars($action['recommended_next_step']) ?></p>
          <small><?= htmlspecialchars($action['owner']) ?> · <?= htmlspecialchars($action['region_name'] ?? 'National') ?> · Due <?= htmlspecialchars($action['due_date']) ?></small>
          <form method="post" action="/daily-actions/complete" class="inline-form">
            <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <input name="outcome_notes" placeholder="Outcome notes">
            <button>Complete</button>
          </form>
          <form method="post" action="/daily-actions/dismiss" class="inline-form">
            <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <button class="secondary">Dismiss</button>
          </form>
        </article>
      <?php endforeach; ?>
      <?php if (!$topActions): ?><p>No open daily actions.</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Create Follow-Up Action</h2>
    <form method="post" action="/daily-actions/follow-up" class="form-grid">
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <label>Source Action<select name="source_action_id"><?php foreach ($actions as $action): ?><option value="<?= (int)$action['id'] ?>"><?= htmlspecialchars($action['action_title']) ?></option><?php endforeach; ?></select></label>
      <label>Action Title<input name="action_title" required></label>
      <label>Owner<select name="owner"><option>Mike</option><option>Ron</option><option>Mike/Ron Shared</option><option>Future Southwest Owner</option><option>Admin</option></select></label>
      <label>Due Date<input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+2 days')) ?>"></label>
      <label class="full">Recommended Next Step<textarea name="recommended_next_step" rows="3"></textarea></label>
      <button>Create Follow-Up</button>
    </form>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Growth Blockers</h2>
    <div class="table-wrap"><table><thead><tr><th>Severity</th><th>Blocker</th><th>Resolution</th></tr></thead><tbody><?php foreach ($blockers as $item): ?><tr><td><span class="priority <?= strtolower($item['severity']) ?>"><?= htmlspecialchars($item['severity']) ?></span></td><td><strong><?= htmlspecialchars($item['blocker_title']) ?></strong><br><small><?= htmlspecialchars($item['reason']) ?></small></td><td><?= htmlspecialchars($item['recommended_resolution']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Recruit Capacity</h2>
    <div class="table-wrap"><table><thead><tr><th>Urgency</th><th>Theater</th><th>Need</th><th>Sources</th></tr></thead><tbody><?php foreach ($capacityGaps as $item): ?><tr><td><span class="priority <?= strtolower($item['urgency']) ?>"><?= htmlspecialchars($item['urgency']) ?></span></td><td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td><td><?= (int)$item['needed_count'] ?> <?= htmlspecialchars($item['discipline']) ?></td><td><?= htmlspecialchars($item['suggested_sources']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Strengthen Relationships</h2>
    <div class="table-wrap"><table><thead><tr><th>Decision</th><th>Contact</th><th>Impact</th><th>Action</th></tr></thead><tbody><?php foreach ($relationshipActions as $item): ?><tr><td><?= htmlspecialchars($item['decision']) ?></td><td><?= htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?><br><small><?= htmlspecialchars($item['organization_name'] ?? '') ?></small></td><td><?= (int)$item['impact_score'] ?></td><td><?= htmlspecialchars($item['recommended_action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Hunts Need Action</h2>
    <div class="table-wrap"><table><thead><tr><th>Due</th><th>Target</th><th>Hunt</th><th>Task</th></tr></thead><tbody><?php foreach ($hunts as $item): ?><tr><td><?= htmlspecialchars($item['due_date']) ?></td><td><?= htmlspecialchars($item['target_name']) ?></td><td><?= htmlspecialchars($item['hunt_name']) ?></td><td><?= htmlspecialchars($item['task_title']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Publish / Review</h2>
    <div class="table-wrap"><table><thead><tr><th>Decision</th><th>Content</th><th>Impact</th><th>Channel</th></tr></thead><tbody><?php foreach ($contentActions as $item): ?><tr><td><?= htmlspecialchars($item['decision']) ?></td><td><?= htmlspecialchars($item['title']) ?><br><small><?= htmlspecialchars($item['audience']) ?></small></td><td><?= (int)$item['impact_score'] ?></td><td><?= htmlspecialchars($item['recommended_channel']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Pursue / Avoid</h2>
    <div class="table-wrap"><table><thead><tr><th>Decision</th><th>Opportunity</th><th>Pursue</th><th>Avoid</th></tr></thead><tbody><?php foreach ($opportunityDecisions as $item): ?><tr><td><?= htmlspecialchars($item['recommended_decision']) ?></td><td><?= htmlspecialchars($item['opportunity_name']) ?><br><small>$<?= number_format((float)$item['estimated_value']) ?></small></td><td><?= (int)$item['pursue_score'] ?></td><td><?= (int)$item['avoid_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
