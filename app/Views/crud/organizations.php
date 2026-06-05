<section class="page-header"><p class="eyebrow">Acquisition Database</p><h1>Organizations</h1><p>Track utilities, primes, subcontractors, vendors, municipalities, equipment providers, and market relationships.</p></section>
<section class="panel"><h2>Add / Update Organization</h2><form method="post" class="form-grid">
  <label>Record ID to update <input type="number" name="id" placeholder="Leave blank for new"></label>
  <label>Name <input name="name" required></label>
  <label>Type <select name="type"><?php foreach ($options['types'] as $o): ?><option><?= $o ?></option><?php endforeach; ?></select></label>
  <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label>
  <label>State <select name="state"><?php foreach ($options['states'] as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></label>
  <label>City <input name="city"></label><label>Website <input name="website"></label><label>Phone <input name="phone"></label><label>Status <input name="status" value="Active"></label>
  <label class="full">Notes <textarea name="notes"></textarea></label><button class="btn">Save Organization</button>
</form></section>
<?php $resource = 'organizations'; $recordType = 'organization'; $columns = ['id'=>'ID','name'=>'Organization','type'=>'Type','region_name'=>'Region','state'=>'State','city'=>'City','status'=>'Status']; require __DIR__ . '/table.php'; ?>
