<?php
$recordEyebrow = 'Target Workspace';
$recordName = $target['target_name'];
$recordType = $target['target_type'] ?? 'Acquisition Target';
$recordRegion = $target['region_name'] ?? 'National';
$recordOwner = $target['owner'] ?? 'Unassigned';
$recordStatus = $target['status'] ?? 'New';
$recordScore = (int)($target['acquisition_score'] ?? 0);
$recordNextAction = $target['recommended_next_action'] ?? 'Review target and decide whether to hunt, watch, convert, or archive.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Owner','Mark Reviewed'];
$recordEntityType = 'acquisition_target';
$recordEntityId = (int)$target['id'];
$recordRegionId = (int)($target['region_id'] ?? 0);
$timelineItems = [
  ['type' => 'Target Created', 'title' => $target['target_name'], 'why' => $target['reason_to_pursue'] ?? 'This target may become work, capacity, relationship access, or market intelligence.', 'next' => $recordNextAction, 'owner' => $recordOwner, 'date' => $target['created_at'] ?? ''],
  ['type' => 'Source Signal', 'title' => $target['signal_title'] ?? $target['source_type'] ?? 'Source intelligence', 'why' => 'The target should stay connected to its original intelligence source for confidence and context.', 'next' => 'Validate source quality before conversion.', 'owner' => $recordOwner, 'date' => $target['last_touched_at'] ?? ''],
];
foreach ($assignments as $assignment) {
  $timelineItems[] = ['type' => 'Hunt Assignment', 'title' => $assignment['hunt_name'], 'why' => 'Hunt execution determines whether this target becomes an operational asset.', 'next' => $assignment['current_step'] ?: 'Assign playbook and next step.', 'owner' => $assignment['assigned_owner'] ?? $recordOwner, 'date' => $assignment['updated_at'] ?? ''];
}
foreach ($recentConversations ?? [] as $conversation) {
  $timelineItems[] = ['type' => $conversation['communication_type'], 'title' => $conversation['summary'], 'why' => $conversation['outcome'] ?: 'Conversation may affect qualification, conversion, or next action.', 'next' => $conversation['next_step'] ?: 'Create follow-up if needed.', 'owner' => $conversation['owner'], 'date' => $conversation['communication_date']];
}
foreach ($activities as $activity) {
  $timelineItems[] = ['type' => $activity['activity_type'], 'title' => $activity['title'], 'why' => $activity['notes'] ?: 'Activity changed target context or status.', 'next' => 'Review current target status and next action.', 'owner' => $activity['owner'], 'date' => $activity['activity_date']];
}
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Conversations','Hunts','Tasks / Actions','Notes','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$what = 'This is an acquisition target generated from signals, hunts, or manual intelligence.';
$why = $target['reason_to_pursue'] ?? 'This target may become capacity, work, relationship access, or market intelligence.';
$recommended = $recordNextAction;
$next = 'Use a record action, assign to a hunt, or convert the target when qualification is clear.';
$risk = 'If this target is not worked, useful intelligence can age out, competitors can move first, and capacity or work opportunities can be missed.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Acquisition Score</span><strong><?= (int)$target['acquisition_score'] ?></strong></div>
  <div><span>Capacity Value</span><strong><?= (int)$target['capacity_value_score'] ?></strong></div>
  <div><span>Strategic Value</span><strong><?= (int)$target['strategic_value_score'] ?></strong></div>
  <div><span>Relationship Value</span><strong><?= (int)$target['relationship_value_score'] ?></strong></div>
  <div><span>Opportunity Value</span><strong><?= (int)$target['opportunity_value_score'] ?></strong></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

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
      <?php if (!$assignments): ?><tr><td colspan="6"><?php $emptyTitle = 'Not assigned to a hunt yet'; $emptyBody = 'Assign this target to a hunt and playbook when it is ready for operator pursuit.'; $emptyActionHref = '/hunts'; $emptyActionLabel = 'Open Hunts'; require __DIR__ . '/../components/empty_state.php'; ?></td></tr><?php endif; ?>
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
      <?php foreach (['organization' => 'Organization','contact' => 'Contact','subcontractor_candidate' => 'Subcontractor Candidate','subcontractor' => 'Subcontractor Profile','opportunity' => 'Opportunity','outreach' => 'Outreach Target'] as $key => $label): ?>
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
      <?php if (!$activities): ?><?php $emptyTitle = 'No activity yet'; $emptyBody = 'Use Add Note, Log Call, Draft Email, or Create Follow-Up to create the first timeline entry.'; $emptyActionHref = ''; $emptyActionLabel = ''; require __DIR__ . '/../components/empty_state.php'; ?><?php endif; ?>
    </div>
</section>
