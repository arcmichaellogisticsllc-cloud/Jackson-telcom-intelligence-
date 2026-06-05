<section class="page-header">
  <p class="eyebrow">National Hunt Command</p>
  <h1>Hunts turn targets into execution.</h1>
  <p>Operational campaigns for capacity, opportunities, influence, workforce, prime relationships, equipment sellers, and vendors.</p>
</section>

<section class="metrics">
  <div><span>Active Hunts</span><strong><?= $metrics['active_hunts'] ?></strong></div>
  <div><span>Targets In Hunts</span><strong><?= $metrics['active_targets'] ?></strong></div>
  <div><span>Overdue Tasks</span><strong><?= $metrics['overdue_tasks'] ?></strong></div>
  <div><span>Converted</span><strong><?= $metrics['converted'] ?></strong></div>
  <div><span>Not Fit</span><strong><?= $metrics['not_fit'] ?></strong></div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Create Hunt</h2><span class="status">What are we hunting?</span></div>
  <form method="post" action="/hunts" class="form-grid">
    <label>Hunt Name <input name="hunt_name" required></label>
    <label>Hunt Type <select name="hunt_type"><?php foreach ($options['huntTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Theater <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
    <label>Owner <select name="owner"><?php foreach ($options['owners'] as $owner): ?><option><?= htmlspecialchars($owner) ?></option><?php endforeach; ?></select></label>
    <label>Target Goal <input type="number" name="target_count_goal" value="25"></label>
    <label>Start <input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></label>
    <label>End <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></label>
    <label>Status <select name="status"><?php foreach ($options['huntStatuses'] as $status): ?><option><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label>
    <label class="full">Objective <textarea name="objective"></textarea></label>
    <label class="full">Success Metric <textarea name="success_metric"></textarea></label>
    <label class="full">Notes <textarea name="notes"></textarea></label>
    <button class="btn">Create Hunt</button>
  </form>
</section>

<section class="panel">
  <div class="panel-title"><h2>Hunts</h2><a class="btn secondary" href="/hunt-actions">Today's Hunt Actions</a></div>
  <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Type</th><th>Theater</th><th>Owner</th><th>Status</th><th>Targets</th><th>Converted</th><th>Not Fit</th><th>Objective</th></tr></thead><tbody>
    <?php foreach ($hunts as $hunt): ?><tr><td><strong><?= htmlspecialchars($hunt['hunt_name']) ?></strong></td><td><?= htmlspecialchars($hunt['hunt_type']) ?></td><td><?= htmlspecialchars($hunt['region_name']) ?></td><td><?= htmlspecialchars($hunt['owner']) ?></td><td><?= htmlspecialchars($hunt['status']) ?></td><td><?= (int)$hunt['assigned_targets'] ?></td><td><?= (int)$hunt['converted_targets'] ?></td><td><?= (int)$hunt['not_fit_targets'] ?></td><td><?= htmlspecialchars($hunt['objective']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
