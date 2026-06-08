<section class="list-toolbar">
  <div>
    <p class="eyebrow"><?= htmlspecialchars($listEyebrow ?? 'Workspace List') ?></p>
    <h2><?= htmlspecialchars($listTitle ?? 'Records') ?></h2>
  </div>
  <div class="list-controls">
    <input type="search" placeholder="Search this workspace">
    <select><option>All owners</option><option>Mike</option><option>Ron</option><option>Mike/Ron Shared</option><option>Admin</option></select>
    <select><option>All theaters</option><option>Southeast</option><option>Great Lakes</option><option>Southwest</option><option>National</option></select>
    <select><option>Saved view</option><option>Top actions</option><option>Needs follow-up</option><option>High score</option></select>
  </div>
</section>
