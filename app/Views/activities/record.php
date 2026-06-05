<section class="page-header">
  <p class="eyebrow"><?= htmlspecialchars($label) ?> Detail</p>
  <h1><?= htmlspecialchars($record['name'] ?? (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?: $label . ' #' . $record['id']) ?></h1>
  <p>Activity timeline for this acquisition record.</p>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Record Snapshot</h2>
    <div class="table-wrap"><table><tbody><?php foreach ($record as $key => $value): ?><tr><th><?= htmlspecialchars(str_replace('_', ' ', $key)) ?></th><td><?= htmlspecialchars((string)$value) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <?php if ($type === 'signal'): ?>
      <h2>Convert Signal</h2>
      <p class="hint">Conversion creates a starter acquisition record from the signal, then marks the signal converted.</p>
      <div class="convert-grid">
        <?php
          $targets = match ($record['signal_type']) {
            'Capacity' => ['organization' => 'Organization', 'subcontractor' => 'Subcontractor'],
            'Relationship' => ['contact' => 'Contact', 'organization' => 'Organization'],
            'Opportunity' => ['opportunity' => 'Opportunity', 'organization' => 'Organization'],
            'Market', 'SEO', 'Content', 'Outreach' => ['opportunity' => 'Opportunity', 'intelligence' => 'Intelligence Record'],
            default => ['organization' => 'Organization'],
          };
        ?>
        <?php foreach ($targets as $target => $labelText): ?>
          <form method="post" action="/signals/convert">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <input type="hidden" name="target" value="<?= htmlspecialchars($target) ?>">
            <button class="btn secondary">Convert to <?= htmlspecialchars($labelText) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
      <hr>
    <?php endif; ?>
    <h2>Add Note</h2>
    <form method="post" action="/activities" class="form-grid">
      <input type="hidden" name="entity_type" value="<?= htmlspecialchars($type) ?>">
      <input type="hidden" name="entity_id" value="<?= (int)$record['id'] ?>">
      <input type="hidden" name="return_to" value="/record?type=<?= htmlspecialchars($type) ?>&id=<?= (int)$record['id'] ?>">
      <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>" <?= ($record['region_id'] ?? null) == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label>
      <label>Type <select name="activity_type"><option>Note</option><option>Call</option><option>Email</option><option>Meeting</option><option>Task</option><option>Status Change</option></select></label>
      <label>Title <input name="title" required></label>
      <label>Date <input type="date" name="activity_date" value="<?= date('Y-m-d') ?>"></label>
      <label>Owner <input name="owner" value="<?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?>"></label>
      <label class="full">Note <textarea name="notes"></textarea></label>
      <button class="btn">Add Activity</button>
    </form>
  </div>
</section>

<section class="panel">
  <h2>Activity Timeline</h2>
  <div class="activity-list timeline">
    <?php foreach ($activities as $activity): ?>
      <div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?> · <?= htmlspecialchars($activity['region_name'] ?? '') ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$activities): ?><p>No activities for this record yet.</p><?php endif; ?>
  </div>
</section>
