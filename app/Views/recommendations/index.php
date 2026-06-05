<section class="page-header"><p class="eyebrow">Decision Support Layer v1</p><h1>Recommended Actions</h1><p>Rules prioritize capacity acquisition, relationship follow-up, compliance, and opportunity discipline.</p></section>
<form method="post" action="/recommendations/regenerate" class="toolbar"><button class="btn secondary">Regenerate Recommendations</button></form>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Priority</th><th>Type</th><th>Category</th><th>Region</th><th>Recommended Action</th><th>Why It Matters</th><th>Owner</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
  <td><span class="priority <?= strtolower($row['priority']) ?>"><?= htmlspecialchars($row['priority']) ?></span><br><small><?= (int)$row['priority_score'] ?></small></td>
  <td><?= htmlspecialchars($row['recommendation_type']) ?></td><td><?= htmlspecialchars($row['category']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? 'All') ?></td>
  <td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['trigger_detail']) ?></small><br><small><?= htmlspecialchars($row['recommended_next_action']) ?></small></td>
  <td><?= htmlspecialchars($row['why_it_matters']) ?></td>
  <td><?= htmlspecialchars($row['assigned_owner']) ?></td>
  <td><form method="post" action="/recommendations"><input type="hidden" name="id" value="<?= $row['id'] ?>"><select name="status" onchange="this.form.submit()"><?php foreach (['Open','In Progress','Completed','Dismissed'] as $s): ?><option <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></form></td>
</tr><?php endforeach; ?>
</tbody></table></div></section>
