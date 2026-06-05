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
    <h2>Active Hunt Assignments</h2>
    <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Playbook</th><th>Status</th><th>Current Step</th><th>Qualification</th><th>Outcome</th></tr></thead><tbody>
      <?php foreach ($assignments as $assignment): ?><tr><td><?= htmlspecialchars($assignment['hunt_name']) ?></td><td><?= htmlspecialchars($assignment['playbook_name'] ?? '') ?></td><td><?= htmlspecialchars($assignment['hunt_status']) ?></td><td><?= htmlspecialchars($assignment['current_step'] ?? '') ?></td><td><?= (int)$assignment['qualification_score'] ?> · <?= htmlspecialchars($assignment['qualification_result'] ?? '') ?></td><td><?= htmlspecialchars($assignment['outcome'] ?? '') ?><br><small><?= htmlspecialchars($assignment['outcome_notes'] ?? '') ?></small></td></tr><?php endforeach; ?>
      <?php if (!$assignments): ?><tr><td colspan="6">Not assigned to a hunt yet.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($assignments): ?>
      <hr>
      <h2>Record Outcome</h2>
      <form method="post" action="/hunt-targets/outcome" class="form-grid compact">
        <input type="hidden" name="return_to" value="/targets/detail?id=<?= (int)$target['id'] ?>">
        <label>Assignment <select name="hunt_target_id"><?php foreach ($assignments as $assignment): ?><option value="<?= (int)$assignment['id'] ?>"><?= htmlspecialchars($assignment['hunt_name']) ?> - <?= htmlspecialchars($assignment['target_name'] ?? $target['target_name']) ?></option><?php endforeach; ?></select></label>
        <label>Outcome <select name="outcome">
          <?php foreach (['Converted to Subcontractor','Converted to Organization','Converted to Contact','Converted to Opportunity','Converted to Outreach Target','Not Fit','No Response','Future Follow-Up','Bad Data','Duplicate'] as $outcome): ?><option><?= htmlspecialchars($outcome) ?></option><?php endforeach; ?>
        </select></label>
        <label class="full">Outcome Notes <textarea name="outcome_notes"></textarea></label>
        <button class="btn secondary">Save Outcome</button>
      </form>
    <?php endif; ?>
    <hr>
    <h2>Add to Hunt</h2>
    <form method="post" action="/hunt-targets" class="form-grid compact">
      <input type="hidden" name="acquisition_target_id" value="<?= (int)$target['id'] ?>">
      <label>Hunt <select name="hunt_id"><?php foreach ($hunts as $hunt): ?><option value="<?= $hunt['id'] ?>"><?= htmlspecialchars($hunt['hunt_name']) ?></option><?php endforeach; ?></select></label>
      <label>Playbook <select name="playbook_id"><?php foreach ($playbooks as $playbook): ?><option value="<?= $playbook['id'] ?>"><?= htmlspecialchars($playbook['playbook_name']) ?></option><?php endforeach; ?></select></label>
      <label>Owner <input name="assigned_owner" value="<?= htmlspecialchars($target['owner']) ?>"></label>
      <button class="btn">Assign Target</button>
    </form>
  </div>
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
</section>

<section class="panel">
    <h2>Activity Timeline</h2>
    <div class="activity-list timeline">
      <?php foreach ($activities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div><?php endforeach; ?>
      <?php if (!$activities): ?><p>No activity yet.</p><?php endif; ?>
    </div>
</section>
