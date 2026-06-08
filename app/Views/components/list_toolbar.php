<?php
$query = $_GET['q'] ?? '';
$ownerFilter = $_GET['owner'] ?? '';
$regionFilter = $_GET['region'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$listRegions = $listRegions ?? ['Southeast','Great Lakes','Southwest','National'];
$listOwners = $listOwners ?? ['Mike','Ron','Mike/Ron Shared','Future Southwest Owner','Admin','Unassigned'];
$listStatuses = $listStatuses ?? ['Open','Active','New','In Progress','Qualified','Approved','Preferred','Strategic Partner','Ready For SyncERP','Completed','Dismissed'];
?>
<form class="list-toolbar" method="get">
  <div>
    <p class="eyebrow"><?= htmlspecialchars($listEyebrow ?? 'Workspace List') ?></p>
    <h2><?= htmlspecialchars($listTitle ?? 'Records') ?></h2>
  </div>
  <div class="list-controls">
    <input type="search" name="q" placeholder="Search this workspace" value="<?= htmlspecialchars((string)$query) ?>">
    <select name="owner" onchange="this.form.submit()">
      <option value="">All owners</option>
      <?php foreach ($listOwners as $owner): ?><option value="<?= htmlspecialchars($owner) ?>" <?= $ownerFilter === $owner ? 'selected' : '' ?>><?= htmlspecialchars($owner) ?></option><?php endforeach; ?>
    </select>
    <select name="region" onchange="this.form.submit()">
      <option value="">All theaters</option>
      <?php foreach ($listRegions as $region): ?><option value="<?= htmlspecialchars($region) ?>" <?= $regionFilter === $region ? 'selected' : '' ?>><?= htmlspecialchars($region) ?></option><?php endforeach; ?>
    </select>
    <select name="status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach ($listStatuses as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?>
    </select>
    <button class="btn secondary" type="submit">Filter</button>
  </div>
</form>
