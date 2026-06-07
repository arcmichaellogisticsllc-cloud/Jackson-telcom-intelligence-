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
      <?php foreach (['/operating-view' => 'Executive Operating View','/acquisition-command' => 'Acquisition Command Center','/market-intelligence' => 'Market Intelligence','/syncerp-integration' => 'SyncERP Integration','/platform-review' => 'Platform Review','/operator-modes' => 'Operator Modes','/' => 'National Command Center','/regions' => 'Regional Command Centers','/daily-brief' => 'Executive Daily Brief','/decision-support' => 'Decision Support','/warehouse' => 'Intelligence Warehouse','/pursuits' => 'Pursuit Board','/preconstruction' => 'Preconstruction','/outreach' => 'Outreach Intelligence','/harvesters' => 'Acquisition Harvesters','/signals' => 'Signal Center','/escalations' => 'Escalations','/watchlists' => 'Watchlists','/targets' => 'Acquisition Targets','/hunting-lists' => 'Hunting Lists','/hunts' => 'Hunts','/playbooks' => 'Playbooks','/capacity-radar' => 'Capacity Radar','/subcontractor-acquisition' => 'Subcontractor Acquisition','/relationship-graph' => 'Relationship Graph','/demand' => 'Demand Engine','/traffic' => 'Traffic Engine','/organizations' => 'Organizations','/contacts' => 'Contacts','/opportunities' => 'Opportunities','/recommendations' => 'Recommendations','/activities' => 'Activities','/settings' => 'Settings'] as $href => $label): ?>
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
