<?php
$recordActions = $recordActions ?? ['Add Note','Log Call','Draft Email','Create Follow-Up','Mark Reviewed'];
$recordScore = (int)($recordScore ?? 0);
$recordEntityType = $recordEntityType ?? strtolower(str_replace(' ', '_', (string)($recordType ?? 'record')));
$recordEntityId = (int)($recordEntityId ?? 0);
$recordRegionId = (int)($recordRegionId ?? 0);
$recordReturnTo = $recordReturnTo ?? ($_SERVER['REQUEST_URI'] ?? '/');
$recordActionOwner = $recordOwner ?? 'Unassigned';
?>
<section class="record-header">
  <div>
    <p class="eyebrow"><?= htmlspecialchars($recordEyebrow ?? 'Record Workspace') ?></p>
    <h1><?= htmlspecialchars($recordName ?? 'Record') ?></h1>
    <div class="record-meta">
      <span><?= htmlspecialchars($recordType ?? 'Record') ?></span>
      <span><?= htmlspecialchars($recordRegion ?? 'National') ?></span>
      <span>Owner: <?= htmlspecialchars($recordOwner ?? 'Unassigned') ?></span>
      <span>Status: <?= htmlspecialchars($recordStatus ?? 'Open') ?></span>
      <?php if ($recordScore > 0): ?><span>Score: <?= $recordScore ?></span><?php endif; ?>
    </div>
    <p><strong>Next best action:</strong> <?= htmlspecialchars($recordNextAction ?? 'Review record, confirm owner, and create the next action.') ?></p>
  </div>
  <div class="record-actions">
    <?php foreach ($recordActions as $action): ?>
      <?php $primary = in_array($action, ['Add Note','Log Call','Draft Email','Create Follow-Up'], true); ?>
      <details class="record-action-panel">
        <summary class="btn <?= $primary ? '' : 'secondary' ?>"><?= htmlspecialchars($action) ?></summary>
        <form method="post" action="/record-actions" class="record-action-form">
          <input type="hidden" name="record_type" value="<?= htmlspecialchars($recordEntityType) ?>">
          <input type="hidden" name="record_id" value="<?= $recordEntityId ?>">
          <input type="hidden" name="region_id" value="<?= $recordRegionId ?>">
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($recordReturnTo) ?>">
          <input type="hidden" name="action_type" value="<?= htmlspecialchars($action) ?>">
          <label><?= $action === 'Assign Owner' ? 'New owner' : 'Owner' ?>
            <input name="owner" value="<?= htmlspecialchars($recordActionOwner) ?>" required>
          </label>
          <?php if ($action === 'Draft Email'): ?>
            <label>Subject <input name="draft_subject" placeholder="Draft subject for human review"></label>
            <label class="full">Draft body <textarea name="draft_body" placeholder="Draft only. No email will be sent."></textarea></label>
          <?php endif; ?>
          <label class="full">Summary <textarea name="summary" required placeholder="<?= htmlspecialchars($action) ?> summary"></textarea></label>
          <label class="full">Outcome <textarea name="outcome" placeholder="What happened or what changed?"></textarea></label>
          <label class="full">Next step <input name="next_step" placeholder="What should happen next?"></label>
          <button class="btn" type="submit">Save <?= htmlspecialchars($action) ?></button>
        </form>
      </details>
    <?php endforeach; ?>
  </div>
</section>
