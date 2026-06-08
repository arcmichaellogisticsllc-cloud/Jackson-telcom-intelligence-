<section class="page-header">
  <p class="eyebrow">Find or Ask</p>
  <h1>Search Jackson Platform</h1>
  <p>Find contacts, accounts, opportunities, subcontractors, capacity providers, project packages, and actions.</p>
</section>

<section class="panel">
  <form class="global-search large" method="get" action="/workspace/search"><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Find or Ask"><button class="btn">Search</button></form>
</section>

<section class="grid two">
  <?php
  $map = [
    'organizations' => ['Organizations', '/organizations/detail?id='],
    'contacts' => ['Contacts', '/contacts/detail?id='],
    'opportunities' => ['Opportunities', '/pursuits/detail?id='],
    'subcontractors' => ['Subcontractors', '/subcontractor-acquisition/detail?id='],
    'packages' => ['Project Packages', '/syncerp-integration/detail?id='],
  ];
  ?>
  <?php foreach ($map as $key => [$label, $href]): ?><div class="panel">
    <div class="panel-title"><h2><?= htmlspecialchars($label) ?></h2><span class="status"><?= count($results[$key] ?? []) ?></span></div>
    <div class="action-stack">
      <?php foreach ($results[$key] ?? [] as $row): ?><article><h3><a href="<?= $href . (int)$row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></h3><p><?= htmlspecialchars($row['meta'] ?? '') ?></p></article><?php endforeach; ?>
      <?php if (empty($results[$key])): ?><article class="empty-state"><h3>No matches</h3><p>Try another company, person, package, pursuit, or capacity name.</p></article><?php endif; ?>
    </div>
  </div><?php endforeach; ?>
</section>
