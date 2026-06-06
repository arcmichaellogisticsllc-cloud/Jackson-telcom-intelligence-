<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($app['name']) ?></title>
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<?php if ($user): ?>
  <aside class="sidebar">
    <div class="brand">Jackson<br><span>Intelligence Platform</span></div>
    <nav>
      <?php foreach (['/' => 'National Command Center','/regions' => 'Regional Command Centers','/briefing' => 'Daily Briefing','/demand-briefing' => 'Demand Briefing','/escalations' => 'Escalations','/watchlists' => 'Watchlists','/capacity-radar' => 'Capacity Radar','/subcontractor-acquisition' => 'Subcontractor Acquisition','/relationship-graph' => 'Relationship Graph','/harvesters' => 'Acquisition Harvesters','/targets' => 'Acquisition Targets','/targets/hunting' => 'Hunting Lists','/hunts' => 'Hunts','/playbooks' => 'Playbooks','/hunt-actions' => 'Today Hunt Actions','/traffic' => 'Traffic Engine','/demand' => 'Demand & Distribution','/signals' => 'Signal Center','/organizations' => 'Organizations','/contacts' => 'Contacts','/subcontractors' => 'Subcontractors','/opportunities' => 'Opportunities','/recommendations' => 'Recommendations','/activities' => 'Activities','/settings' => 'Settings'] as $href => $label): ?>
        <a href="<?= $href ?>" class="<?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $href ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
<?php endif; ?>
<main class="<?= $user ? 'main' : 'login-main' ?>">
  <?php if ($user): ?>
    <header class="topbar">
      <div>
        <strong><?= htmlspecialchars($user['name']) ?></strong>
        <span><?= htmlspecialchars($user['role']) ?></span>
      </div>
      <form method="post" action="/logout"><button class="link-button">Logout</button></form>
    </header>
  <?php endif; ?>
  <?php require $contentView; ?>
</main>
</body>
</html>
