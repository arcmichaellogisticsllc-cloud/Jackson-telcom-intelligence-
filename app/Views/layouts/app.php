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
    <?php $brand = $app['brand'] ?? []; ?>
    <a class="brand" href="/">
      <span class="brand-mark"><?= htmlspecialchars($brand['logo_text'] ?? 'JT') ?></span>
      <strong><?= htmlspecialchars($brand['company_name'] ?? 'Jackson Telcom LLC') ?></strong>
      <em><?= htmlspecialchars($brand['command_center_title'] ?? 'Jackson Telcom Command Center') ?></em>
    </a>
    <nav>
      <?php $navGroups = [
        'COMMAND' => ['/' => 'Command Center','/executive-os' => 'Executive OS','/executive-packages' => 'Decision Packages','/executive-briefs' => 'Executive Briefs','/daily-brief' => 'Executive Brief','/briefing' => 'Daily Brief','/decision-support' => 'Decision Support','/strategic-review' => 'Strategic Review'],
        'WORK' => ['/acquisition-command' => 'Work Intelligence','/strategic-account-intelligence' => 'Strategic Accounts','/pursuits' => 'Pursuits','/preconstruction' => 'Preconstruction','/opportunities' => 'Opportunities'],
        'CAPACITY' => ['/capacity-radar' => 'Capacity Radar','/subcontractor-acquisition' => 'Subcontractor Network','/subcontractors' => 'Preferred Network','/targets' => 'Strategic Partners'],
        'RELATIONSHIPS' => ['/communications' => 'Communications','/relationship-graph' => 'Relationship Graph','/network-intelligence' => 'Network Intelligence','/relationships' => 'Influence Network','/contacts' => 'Contacts'],
        'MARKET' => ['/signals' => 'Signals','/escalations' => 'Escalations','/watchlists' => 'Watchlists','/market-intelligence' => 'Market Intelligence','/workforce-intelligence' => 'Workforce Intelligence','/competitive-intelligence' => 'Competitive Intelligence','/harvesters' => 'Acquisition Harvesters'],
        'GROWTH' => ['/demand' => 'Demand Engine','/traffic' => 'Content','/outreach' => 'Distribution'],
        'OPERATIONS' => ['/syncerp-integration' => 'SyncERP Integration'],
        'SETTINGS' => ['/settings' => 'Settings','/platform-review' => 'Administration','/operator-modes' => 'Operator Modes','/ownership-matrix' => 'Ownership Matrix','/strategic-accounts' => 'Strategic Accounts','/forecasts' => 'Forecasts','/organizations' => 'Organizations','/recommendations' => 'Recommendations','/activities' => 'Activities','/warehouse' => 'Intelligence Warehouse'],
      ]; ?>
      <?php foreach ($navGroups as $group => $links): ?>
        <div class="nav-group"><?= htmlspecialchars($group) ?></div>
        <?php foreach ($links as $href => $label): ?>
          <a href="<?= $href ?>" class="<?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $href ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
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
