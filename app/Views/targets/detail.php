<section class="page-header">
  <p class="eyebrow">Acquisition Target</p>
  <h1><?= htmlspecialchars($target['target_name']) ?></h1>
  <p><?= htmlspecialchars($target['reason_to_pursue']) ?></p>
</section>

<section class="metrics">
  <div><span>Acquisition Score</span><strong><?= (int)$target['acquisition_score'] ?></strong></div>
  <div><span>Capacity Value</span><strong><?= (int)$target['capacity_value_score'] ?></strong></div>
  <div><span>Strategic Value</span><strong><?= (int)$target['strategic_value_score'] ?></strong></div>
  <div><span>Relationship Value</span><strong><?= (int)$target['relationship_value_score'] ?></strong></div>
  <div><span>Opportunity Value</span><strong><?= (int)$target['opportunity_value_score'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Why Is This Target Here?</h2>
    <p><?= htmlspecialchars($target['reason_to_pursue']) ?></p>
    <h2>What Do We Know?</h2>
    <div class="table-wrap"><table><tbody>
      <?php foreach (['target_type','region_name','state','city','organization_name','contact_name','email','phone','website','source_type','source_url','signal_title','status','priority','owner','last_touched_at','next_action_due_at'] as $key): ?>
        <tr><th><?= htmlspecialchars(str_replace('_', ' ', $key)) ?></th><td><?= htmlspecialchars((string)($target[$key] ?? '')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <h2>What Should We Do Next?</h2>
    <p><strong><?= htmlspecialchars($target['recommended_next_action']) ?></strong></p>
    <form method="post" action="/targets/status" class="form-card">
      <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
      <label>Status <select name="status"><?php foreach ($statuses as $status): ?><option <?= $status === $target['status'] ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label>
      <button class="btn">Update Status</button>
    </form>
    <hr>
    <h2>Outreach Prep</h2>
    <p><strong>Recommended Channel:</strong> <?= htmlspecialchars($prep['channel']) ?></p>
    <p><strong>Suggested Opening:</strong> <?= htmlspecialchars($prep['opening']) ?></p>
    <p><strong>Why This Matters:</strong> <?= htmlspecialchars($prep['why']) ?></p>
    <p><strong>Call Notes:</strong> <?= htmlspecialchars($prep['call_notes']) ?></p>
    <ul><?php foreach ($prep['questions'] as $question): ?><li><?= htmlspecialchars($question) ?></li><?php endforeach; ?></ul>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>What Can This Target Become?</h2>
    <div class="convert-grid">
      <?php foreach (['organization' => 'Organization','contact' => 'Contact','subcontractor' => 'Subcontractor Profile','opportunity' => 'Opportunity','outreach' => 'Outreach Target'] as $key => $label): ?>
        <form method="post" action="/targets/convert">
          <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
          <input type="hidden" name="convert_to" value="<?= htmlspecialchars($key) ?>">
          <button class="btn secondary">Convert to <?= htmlspecialchars($label) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h2>Activity Timeline</h2>
    <div class="activity-list timeline">
      <?php foreach ($activities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div><?php endforeach; ?>
      <?php if (!$activities): ?><p>No activity yet.</p><?php endif; ?>
    </div>
  </div>
</section>
