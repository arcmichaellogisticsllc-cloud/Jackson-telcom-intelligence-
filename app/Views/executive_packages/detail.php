<section class="page-header command-page-header">
  <p class="eyebrow"><?= htmlspecialchars($package['package_type']) ?> Package</p>
  <h1><?= htmlspecialchars($package['package_title']) ?></h1>
  <p><?= htmlspecialchars($package['executive_summary']) ?></p>
</section>

<nav class="dash-tabs">
  <a href="/executive-packages">Packages</a>
  <a href="/executive-briefs">Briefs</a>
  <a href="/executive-os">Executive OS</a>
  <a href="/daily-brief">Daily Brief</a>
</nav>

<section class="action-first">
  <div><span>What Is This?</span><p><?= htmlspecialchars($package['decision_required']) ?></p></div>
  <div><span>Why It Matters</span><p><?= htmlspecialchars($package['executive_summary']) ?></p></div>
  <div><span>What Should I Do?</span><p><?= htmlspecialchars($package['recommended_action']) ?></p></div>
  <div><span>What Happens If I Do Nothing?</span><p><?= htmlspecialchars($package['risk_of_inaction']) ?></p></div>
</section>

<section class="metrics command-metrics">
  <div><span>Confidence</span><strong><?= (int)$package['confidence_score'] ?></strong></div>
  <div><span>Impact</span><strong><?= (int)$package['impact_score'] ?></strong></div>
  <div><span>Urgency</span><strong><?= (int)$package['urgency_score'] ?></strong></div>
  <div><span>Status</span><strong><?= htmlspecialchars($package['package_status']) ?></strong></div>
  <div><span>Owner</span><strong><?= htmlspecialchars($package['owner']) ?></strong></div>
</section>

<?php if (!empty($package['doctrine'])): ?>
  <section class="panel">
    <div class="panel-title">
      <div>
        <p class="eyebrow">Doctrine Alignment</p>
        <h2>Does this decision fit Jackson doctrine?</h2>
      </div>
      <span class="score <?= strtolower($package['doctrine']['overall_doctrine_alignment_score'] >= 74 ? 'strong' : ($package['doctrine']['overall_doctrine_alignment_score'] >= 58 ? 'stable' : 'weak')) ?>"><?= (int)$package['doctrine']['overall_doctrine_alignment_score'] ?></span>
    </div>
    <div class="doctrine-health five">
      <div><span>Rule 1 Status</span><strong><?= htmlspecialchars($package['doctrine']['work_status']) ?></strong><small>Work <?= (int)$package['doctrine']['work_alignment_score'] ?></small></div>
      <div><span>Rule 2 Status</span><strong><?= htmlspecialchars($package['doctrine']['capacity_status']) ?></strong><small>Capacity <?= (int)$package['doctrine']['capacity_alignment_score'] ?></small></div>
      <div><span>Rule 3 Status</span><strong><?= htmlspecialchars($package['doctrine']['relationship_status']) ?></strong><small>Relationship <?= (int)$package['doctrine']['relationship_alignment_score'] ?></small></div>
      <div><span>Rule 4 Status</span><strong><?= htmlspecialchars($package['doctrine']['flow_status']) ?></strong><small>Flow <?= (int)$package['doctrine']['flow_alignment_score'] ?></small></div>
      <div><span>Rule 5 Status</span><strong><?= htmlspecialchars($package['doctrine']['action_status']) ?></strong><small>Action <?= (int)$package['doctrine']['action_alignment_score'] ?></small></div>
    </div>
    <p><strong>Doctrine reason:</strong> <?= htmlspecialchars($package['doctrine']['reason_for_score']) ?></p>
  </section>
<?php endif; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>One-Click Actions</h2><span class="status">No Automated Sending</span></div>
    <div class="action-stack">
      <?php foreach ($package['actions'] as $action): ?>
        <article>
          <h3><?= htmlspecialchars($action['action_label']) ?></h3>
          <p><?= htmlspecialchars($action['action_target']) ?></p>
          <form method="post" action="/executive-packages/action" class="inline-form">
            <input type="hidden" name="package_id" value="<?= (int)$package['id'] ?>">
            <input type="hidden" name="action_id" value="<?= (int)$action['id'] ?>">
            <input name="owner" value="<?= htmlspecialchars($package['owner']) ?>">
            <input name="notes" placeholder="Outcome or intent">
            <button class="btn secondary">Use</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h2>Package Timeline</h2>
    <div class="activity-list">
      <?php foreach ($package['timeline'] as $event): ?>
        <div><strong><?= htmlspecialchars($event['event_title']) ?></strong><span><?= htmlspecialchars(substr($event['event_date'], 0, 10)) ?> · <?= htmlspecialchars($event['event_type']) ?> · <?= htmlspecialchars($event['owner']) ?></span><small><?= htmlspecialchars($event['event_summary']) ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($package['decision']): ?>
  <section class="panel">
    <h2>Supporting Evidence</h2>
    <div class="table-wrap"><table><tbody>
      <tr><th>Decision Type</th><td><?= htmlspecialchars($package['decision']['decision_type']) ?></td></tr>
      <tr><th>Evidence</th><td><?= htmlspecialchars($package['decision']['supporting_evidence'] ?? '') ?></td></tr>
      <tr><th>Signals</th><td><?= htmlspecialchars($package['decision']['supporting_signals'] ?? '') ?></td></tr>
      <tr><th>Relationships</th><td><?= htmlspecialchars($package['decision']['supporting_relationships'] ?? '') ?></td></tr>
      <tr><th>Capacity</th><td><?= htmlspecialchars($package['decision']['supporting_capacity'] ?? '') ?></td></tr>
      <tr><th>Risks</th><td><?= htmlspecialchars($package['decision']['risks'] ?? '') ?></td></tr>
    </tbody></table></div>
  </section>
<?php endif; ?>

<section class="panel">
  <h2>Close The Loop</h2>
  <form class="form-grid" method="post" action="/executive-packages/status">
    <input type="hidden" name="id" value="<?= (int)$package['id'] ?>">
    <label>Status<select name="package_status"><option>Reviewed</option><option>Active</option><option>Completed</option><option>Archived</option></select></label>
    <label>Owner<input name="owner" value="<?= htmlspecialchars($package['owner']) ?>"></label>
    <label class="full">Outcome Notes<textarea name="notes"></textarea></label>
    <button class="btn">Update Package</button>
  </form>
</section>
