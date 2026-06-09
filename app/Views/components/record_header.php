<?php
$recordActions = $recordActions ?? ['Add Note','Log Call','Draft Email','Create Follow-Up','Mark Reviewed'];
$recordScore = (int)($recordScore ?? 0);
$recordEntityType = $recordEntityType ?? strtolower(str_replace(' ', '_', (string)($recordType ?? 'record')));
$recordEntityId = (int)($recordEntityId ?? 0);
$recordRegionId = (int)($recordRegionId ?? 0);
$recordReturnTo = $recordReturnTo ?? ($_SERVER['REQUEST_URI'] ?? '/');
$recordActionOwner = $recordOwner ?? 'Unassigned';
$recordPrimaryOwner = $recordPrimaryOwner ?? $recordOwner ?? 'Unassigned';
$recordSecondaryOwner = $recordSecondaryOwner ?? '';
$recordSharedOwnerFlag = (int)($recordSharedOwnerFlag ?? 0);
$recordOwnershipNotes = $recordOwnershipNotes ?? '';
$recordSupportingAction = $recordSupportingAction ?? ($recordSecondaryOwner ? 'Support the primary owner on capacity, relationship, or readiness context.' : '');
?>
<section class="record-header">
  <div>
    <p class="eyebrow"><?= htmlspecialchars($recordEyebrow ?? 'Record Workspace') ?></p>
    <h1><?= htmlspecialchars($recordName ?? 'Record') ?></h1>
    <div class="record-meta">
      <span><?= htmlspecialchars($recordType ?? 'Record') ?></span>
      <span><?= htmlspecialchars($recordRegion ?? 'National') ?></span>
      <span>Primary Owner: <?= htmlspecialchars($recordPrimaryOwner ?: 'Unassigned') ?></span>
      <span>Secondary Owner: <?= htmlspecialchars($recordSecondaryOwner ?: 'Unassigned') ?></span>
      <span><?= $recordSharedOwnerFlag ? 'Shared Priority' : 'Not Shared' ?></span>
      <span>Status: <?= htmlspecialchars($recordStatus ?? 'Open') ?></span>
      <?php if ($recordScore > 0): ?><span>Score: <?= $recordScore ?></span><?php endif; ?>
    </div>
    <p><strong>Next best action:</strong> <?= htmlspecialchars($recordNextAction ?? 'Review record, confirm owner, and create the next action.') ?></p>
    <?php if ($recordSupportingAction): ?><p><strong>Supporting owner action:</strong> <?= htmlspecialchars($recordSupportingAction) ?></p><?php endif; ?>
  </div>
  <div class="record-actions">
    <?php foreach ($recordActions as $action): ?>
      <?php $primary = in_array($action, ['Add Note','Log Call','Draft Email','Create Follow-Up'], true); ?>
      <details class="record-action-panel">
        <summary class="btn <?= $primary ? '' : 'secondary' ?>"><?= htmlspecialchars($action) ?></summary>
        <form method="post" action="/record-actions" class="record-action-form">
          <?= \App\Core\Auth::csrfInput() ?>
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
    <details class="record-action-panel">
      <summary class="btn secondary">Assign Ownership</summary>
      <form method="post" action="/ownership/update" class="record-action-form">
        <?= \App\Core\Auth::csrfInput() ?>
        <input type="hidden" name="record_type" value="<?= htmlspecialchars($recordEntityType) ?>">
        <input type="hidden" name="record_id" value="<?= $recordEntityId ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($recordReturnTo) ?>">
        <label>Primary Owner
          <input name="primary_owner" value="<?= htmlspecialchars($recordPrimaryOwner ?: 'Unassigned') ?>" required>
        </label>
        <label>Secondary Owner
          <input name="secondary_owner" value="<?= htmlspecialchars($recordSecondaryOwner) ?>">
        </label>
        <label><input type="checkbox" name="shared_owner_flag" value="1" <?= $recordSharedOwnerFlag ? 'checked' : '' ?>> Shared priority</label>
        <label class="full">Ownership Notes <textarea name="ownership_notes"><?= htmlspecialchars($recordOwnershipNotes) ?></textarea></label>
        <label class="full">Change Reason <input name="change_reason" value="Ownership updated from record header."></label>
        <button class="btn" type="submit">Save Ownership</button>
      </form>
    </details>
  </div>
</section>
