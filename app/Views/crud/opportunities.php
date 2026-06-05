<section class="page-header"><p class="eyebrow">Market Intelligence</p><h1>Opportunities</h1><p>Track opportunity intelligence while keeping capacity requirements visible before pursuit execution.</p></section>
<section class="panel"><h2>Add / Update Opportunity</h2><form method="post" class="form-grid">
  <label>Record ID to update <input type="number" name="id" placeholder="Leave blank for new"></label><label>Name <input name="name" required></label><label>Organization <select name="organization_id"><?php foreach ($organizations as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option><?php endforeach; ?></select></label>
  <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label><label>Market <input name="market"></label>
  <label>Estimated value <input type="number" step="0.01" name="estimated_value"></label><label>Estimated margin % <input type="number" step="0.01" name="estimated_margin"></label><label>Probability % <input type="number" name="probability"></label>
  <label>Stage <select name="stage"><?php foreach ($options['stages'] as $o): ?><option><?= $o ?></option><?php endforeach; ?></select></label><label>Capacity required <input type="number" name="capacity_required" value="0"></label><label>Decision makers <input name="decision_makers"></label><label>Owner <input name="owner"></label>
  <label class="full">Next action <input name="next_action"></label><label class="full">Notes <textarea name="notes"></textarea></label><button class="btn">Save Opportunity</button>
</form></section>
<section class="panel">
  <div class="panel-title"><h2>Pursuit Scores</h2><span class="status">Calculated</span></div>
  <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Pursuit Score</th><th>Recommendation</th><th>Capacity</th><th>Risk Notes</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><a href="/record?type=opportunity&id=<?= $row['id'] ?>"><strong><?= htmlspecialchars($row['name']) ?></strong></a></td><td><?= $row['pursuit']['score'] ?></td><td><?= htmlspecialchars($row['pursuit']['label']) ?></td><td><?= (int)$row['available_crews'] ?> / <?= (int)$row['capacity_required'] ?></td><td><?= htmlspecialchars($row['notes'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php $resource = 'opportunities'; $recordType = 'opportunity'; $columns = ['id'=>'ID','name'=>'Opportunity','organization_name'=>'Organization','region_name'=>'Region','market'=>'Market','estimated_value'=>'Value','probability'=>'Probability','stage'=>'Stage','capacity_required'=>'Capacity']; require __DIR__ . '/table.php'; ?>
