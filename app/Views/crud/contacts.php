<section class="page-header"><p class="eyebrow">Relationship Intelligence</p><h1>Contacts</h1><p>Track decision makers, influence, relationship strength, and next actions.</p></section>
<section class="panel"><h2>Add / Update Contact</h2><form method="post" class="form-grid">
  <label>Record ID to update <input type="number" name="id" placeholder="Leave blank for new"></label>
  <label>First name <input name="first_name" required></label><label>Last name <input name="last_name" required></label><label>Title <input name="title"></label><label>Email <input name="email"></label><label>Phone <input name="phone"></label>
  <label>Organization <select name="organization_id"><?php foreach ($organizations as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option><?php endforeach; ?></select></label>
  <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label>
  <label>Relationship owner <input name="relationship_owner"></label>
  <label>Influence <select name="influence_level"><?php foreach ($options['influence'] as $o): ?><option><?= $o ?></option><?php endforeach; ?></select></label>
  <label>Strength <select name="relationship_strength"><?php foreach ($options['strength'] as $o): ?><option><?= $o ?></option><?php endforeach; ?></select></label>
  <label>Last contact <input type="date" name="last_contact_date"></label><label>Next action <input name="next_action"></label>
  <label class="full">Notes <textarea name="notes"></textarea></label><button class="btn">Save Contact</button>
</form></section>
<?php $resource = 'contacts'; $recordType = 'contact'; $columns = ['id'=>'ID','first_name'=>'First','last_name'=>'Last','organization_name'=>'Organization','region_name'=>'Region','influence_level'=>'Influence','relationship_strength'=>'Strength','next_action'=>'Next Action']; require __DIR__ . '/table.php'; ?>
