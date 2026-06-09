<section class="page-header"><p class="eyebrow">Decision Support Layer v1</p><h1>Recommended Actions</h1><p>Rules prioritize capacity acquisition, relationship follow-up, compliance, and opportunity discipline.</p></section>
<form method="post" action="/recommendations/regenerate" class="toolbar"><button class="btn secondary">Regenerate Recommendations</button></form>
<?php
$listEyebrow = 'System Findings';
$listTitle = 'Recommendations';
$listStatuses = ['Open','In Progress','Completed','Dismissed'];
$listPriorities = ['Critical','High','Medium','Low'];
require __DIR__ . '/../components/list_toolbar.php';
?>
<section class="panel"><div class="panel-title"><h2>Operator Work Queue</h2><span class="status"><?= count($rows) ?> shown</span></div><div class="table-wrap"><table class="operator-table"><thead><tr><th>Priority</th><th>Finding</th><th>Region</th><th>Owner</th><th>Status</th><th>Next Action</th><th>Why It Matters</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
  <td><span class="priority <?= strtolower($row['priority']) ?>"><?= htmlspecialchars($row['priority']) ?></span><br><small><?= (int)$row['priority_score'] ?></small></td>
  <td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['category']) ?> · <?= htmlspecialchars($row['recommendation_type']) ?></small><br><small><?= htmlspecialchars($row['trigger_detail']) ?></small></td>
  <td><?= htmlspecialchars($row['region_name'] ?? 'All') ?></td>
  <td><?= htmlspecialchars($row['assigned_owner']) ?></td>
  <td><form method="post" action="/recommendations"><input type="hidden" name="id" value="<?= $row['id'] ?>"><select name="status" onchange="this.form.submit()"><?php foreach (['Open','In Progress','Completed','Dismissed'] as $s): ?><option <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></form></td>
  <td><?= htmlspecialchars($row['recommended_next_action']) ?></td>
  <td><?= htmlspecialchars($row['why_it_matters']) ?></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7"><?php $emptyTitle = 'No recommendations match this view'; $emptyBody = 'Clear filters or regenerate recommendations after real records are available.'; $emptyActionHref = '/recommendations'; $emptyActionLabel = 'Clear Filters'; require __DIR__ . '/../components/empty_state.php'; ?></td></tr><?php endif; ?>
</tbody></table></div></section>
