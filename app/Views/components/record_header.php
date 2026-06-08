<?php
$recordActions = $recordActions ?? ['Add Note','Log Call','Draft Email','Create Follow-Up','Mark Reviewed'];
$recordScore = (int)($recordScore ?? 0);
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
    <?php foreach ($recordActions as $action): ?><button class="btn <?= in_array($action, ['Add Note','Log Call','Draft Email','Create Follow-Up'], true) ? '' : 'secondary' ?>" type="button"><?= htmlspecialchars($action) ?></button><?php endforeach; ?>
  </div>
</section>
