<section class="page-header"><p class="eyebrow">Settings</p><h1>Regions / theaters and users</h1><p>Phase 1 configuration for theater ownership, hub coverage, access, and capacity targets.</p></section>
<section class="grid two">
  <div class="panel"><h2>Regions</h2><table><thead><tr><th>Name</th><th>Owner</th><th>States</th><th>Status</th></tr></thead><tbody><?php foreach ($regions as $r): ?><tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['owner']) ?></td><td><?= htmlspecialchars($r['states']) ?></td><td><?= $r['active'] ? 'Active' : 'Inactive' ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="panel"><h2>Users</h2><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Region</th></tr></thead><tbody><?php foreach ($users as $u): ?><tr><td><?= htmlspecialchars($u['name']) ?></td><td><?= htmlspecialchars($u['email']) ?></td><td><?= htmlspecialchars($u['role']) ?></td><td><?= htmlspecialchars($u['region_name'] ?? 'All') ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<section class="panel">
  <h2>Regional Capacity Targets</h2>
  <form method="post" action="/settings/targets">
    <div class="table-wrap"><table><thead><tr><th>Region</th><th>Service</th><th>Target Crews</th></tr></thead><tbody><?php foreach ($targets as $target): ?><tr><td><?= htmlspecialchars($target['region_name']) ?></td><td><?= htmlspecialchars($target['service_type']) ?></td><td><input type="number" min="0" name="targets[<?= $target['id'] ?>]" value="<?= (int)$target['target_crews'] ?>"></td></tr><?php endforeach; ?></tbody></table></div>
    <button class="btn">Update Capacity Targets</button>
  </form>
</section>
