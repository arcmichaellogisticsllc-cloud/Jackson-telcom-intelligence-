<section class="page-header"><p class="eyebrow">Activity Log</p><h1>Relationship and acquisition activity</h1><p>Every major entity can be tracked with notes, calls, emails, meetings, tasks, and status changes.</p></section>
<section class="panel"><h2>Add Activity</h2><form method="post" class="form-grid">
  <label>Entity type <select name="entity_type"><option>organization</option><option>contact</option><option>subcontractor</option><option>opportunity</option></select></label><label>Entity ID <input type="number" name="entity_id" required></label>
  <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label>
  <label>Type <select name="activity_type"><?php foreach (['Note','Call','Email','Meeting','Task','Status Change'] as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
  <label>Title <input name="title" required></label><label>Date <input type="date" name="activity_date" value="<?= date('Y-m-d') ?>"></label><label>Owner <input name="owner"></label>
  <label class="full">Notes <textarea name="notes"></textarea></label><button class="btn">Save Activity</button>
</form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Type</th><th>Entity</th><th>Region</th><th>Title</th><th>Owner</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars(substr($row['activity_date'],0,10)) ?></td><td><?= htmlspecialchars($row['activity_type']) ?></td><td><?= htmlspecialchars($row['entity_type'] . ' #' . $row['entity_id']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['notes']) ?></small></td><td><?= htmlspecialchars($row['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>

