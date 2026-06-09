<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<section class="page-header">
  <p class="eyebrow">Shared Operating System</p>
  <h1>Ownership / Accountability</h1>
  <p>One Jackson workflow with clear primary ownership, secondary support, and shared executive priorities.</p>
  <form method="post" action="/ownership/backfill">
    <?= \App\Core\Auth::csrfInput() ?>
    <button class="btn" type="submit">Backfill Ownership Defaults</button>
  </form>
</section>

<?php if ($flash): ?><section class="panel"><p><?= htmlspecialchars($flash) ?></p></section><?php endif; ?>

<?php
$why = 'Ownership prevents split-brain operations by showing one company state while making responsibility explicit.';
$recommended = 'Review unassigned and shared records first, then transfer or support ownership where priorities are stalled.';
$next = 'Assign primary and secondary owners for any unassigned records, and mark Southwest/National priorities as shared where joint action is required.';
$risk = 'Without clear ownership, valuable work, capacity, relationships, and handoffs can stall because everyone can see the record but nobody owns the next move.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="grid five">
  <div class="metric-card"><span>Total Records</span><strong><?= (int)$metrics['total_records'] ?></strong></div>
  <div class="metric-card"><span>Unassigned</span><strong><?= (int)$metrics['unassigned'] ?></strong></div>
  <div class="metric-card"><span>No Secondary</span><strong><?= (int)$metrics['missing_secondary'] ?></strong></div>
  <div class="metric-card"><span>Shared</span><strong><?= (int)$metrics['shared'] ?></strong></div>
  <div class="metric-card"><span>Conflicts</span><strong><?= (int)$metrics['conflicts'] ?></strong></div>
</section>

<section class="grid three">
  <?php foreach (['my' => 'My Priorities', 'shared' => 'Shared Priorities', 'company' => 'Company Priorities'] as $key => $label): ?>
    <div class="panel">
      <div class="panel-title"><h2><?= htmlspecialchars($label) ?></h2></div>
      <div class="command-items">
        <?php foreach (($priorities[$key] ?? []) as $item): ?>
          <div>
            <strong><?= htmlspecialchars($item['action_title'] ?? $item['package_title'] ?? 'Priority') ?></strong>
            <span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['primary_owner'] ?? $item['owner'] ?? 'Unassigned') ?> · <?= htmlspecialchars($item['recommended_next_step'] ?? $item['recommended_action'] ?? 'Confirm next action.') ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (empty($priorities[$key])): ?><div><strong>No active items</strong><span>This priority bucket is clear for the current perspective.</span></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Owner Workload</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Owner</th><th>Records</th></tr></thead><tbody>
      <?php foreach ($workload as $owner => $count): ?><tr><td><?= htmlspecialchars($owner) ?></td><td><?= (int)$count ?></td></tr><?php endforeach; ?>
      <?php if (!$workload): ?><tr><td colspan="2">No ownership workload exists yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Ownership Conflicts</h2></div>
    <div class="command-items">
      <?php foreach ($conflicts as $row): ?>
        <div><strong><?= htmlspecialchars($row['record_title']) ?></strong><span><?= htmlspecialchars($row['record_type']) ?> · same primary and secondary owner: <?= htmlspecialchars($row['primary_owner']) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$conflicts): ?><div><strong>No conflicts</strong><span>Primary and secondary ownership assignments are distinct.</span></div><?php endif; ?>
    </div>
  </div>
</section>

<?php
$sections = [
    'unassigned' => ['Unassigned Records', 'Assign Primary Owner'],
    'missingSecondary' => ['Records Missing Secondary Owner', 'Assign Support Owner'],
    'shared' => ['Shared Records', 'Review Shared Ownership'],
];
?>
<?php foreach ($sections as $key => [$title, $actionLabel]): ?>
  <section class="panel">
    <div class="panel-title"><h2><?= htmlspecialchars($title) ?></h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Record</th><th>Theater</th><th>Primary</th><th>Secondary</th><th>Shared</th><th>Next Owner Action</th><th><?= htmlspecialchars($actionLabel) ?></th></tr></thead>
        <tbody>
        <?php foreach (($$key ?? []) as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['record_title']) ?></strong><br><small><?= htmlspecialchars($row['record_type']) ?> #<?= (int)$row['id'] ?> · <?= htmlspecialchars($row['record_status'] ?? 'Open') ?></small></td>
            <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td>
            <td><?= htmlspecialchars($row['primary_owner'] ?? 'Unassigned') ?></td>
            <td><?= htmlspecialchars($row['secondary_owner'] ?? '') ?></td>
            <td><?= (int)($row['shared_owner_flag'] ?? 0) ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars($row['next_owner_action'] ?? 'Confirm next action.') ?></td>
            <td>
              <details class="record-action-panel compact">
                <summary class="btn secondary">Update</summary>
                <form method="post" action="/ownership/update" class="record-action-form">
                  <?= \App\Core\Auth::csrfInput() ?>
                  <input type="hidden" name="record_type" value="<?= htmlspecialchars($row['record_type']) ?>">
                  <input type="hidden" name="record_id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="return_to" value="/ownership">
                  <label>Primary Owner <input name="primary_owner" value="<?= htmlspecialchars($row['primary_owner'] ?? '') ?>"></label>
                  <label>Secondary Owner <input name="secondary_owner" value="<?= htmlspecialchars($row['secondary_owner'] ?? '') ?>"></label>
                  <label><input type="checkbox" name="shared_owner_flag" value="1" <?= (int)($row['shared_owner_flag'] ?? 0) ? 'checked' : '' ?>> Shared priority</label>
                  <label class="full">Ownership Notes <textarea name="ownership_notes"><?= htmlspecialchars($row['ownership_notes'] ?? '') ?></textarea></label>
                  <label class="full">Change Reason <input name="change_reason" value="Ownership reviewed from accountability dashboard."></label>
                  <button class="btn" type="submit">Save Ownership</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($$key)): ?><tr><td colspan="7">No records in this ownership queue.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>
