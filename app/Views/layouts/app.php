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
      <?php
      $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
      $navGroups = [
        'COMMAND' => ['/' => 'Command Center','/daily-brief' => 'Daily Brief','/executive-briefs' => 'Executive Brief','/executive-packages' => 'Decision Packages'],
        'WORK' => ['/acquisition-command' => 'Work Intelligence','/opportunities' => 'Opportunities','/pursuits' => 'Pursuits','/preconstruction' => 'Preconstruction'],
        'CAPACITY' => ['/capacity-radar' => 'Capacity Radar','/subcontractor-acquisition' => 'Subcontractor Network','/workforce-intelligence' => 'Workforce Intelligence','/targets' => 'Strategic Partners'],
        'RELATIONSHIPS' => ['/contacts' => 'Contacts','/organizations' => 'Organizations','/strategic-account-intelligence' => 'Strategic Accounts','/communications' => 'Communications','/relationship-graph' => 'Relationship Graph'],
        'MARKET' => ['/signals' => 'Signals','/escalations' => 'Escalations','/watchlists' => 'Watchlists','/market-intelligence' => 'Market Intelligence','/competitive-intelligence' => 'Competitive Intelligence'],
        'GROWTH' => ['/demand' => 'Demand','/traffic' => 'Content','/outreach' => 'Distribution','/demand-briefing' => 'Channels'],
        'OPERATIONS' => ['/syncerp-integration' => 'SyncERP Integration','/syncerp-integration#packages' => 'Project Packages','/syncerp-integration#readiness' => 'ERP Readiness'],
        'SYSTEM' => ['/settings' => 'Settings','/platform-review' => 'Platform Health','/activities' => 'Activities','/recommendations' => 'Recommendations','/warehouse' => 'Intelligence Warehouse'],
      ];
      ?>
      <?php foreach ($navGroups as $group => $links): ?>
        <?php $isOpen = in_array($currentPath, array_keys($links), true) || ($group === 'COMMAND' && $currentPath === '/'); ?>
        <details class="workspace-nav" <?= $isOpen ? 'open' : '' ?>>
          <summary><?= htmlspecialchars($group) ?></summary>
          <div>
            <?php foreach ($links as $href => $label): ?>
              <a href="<?= $href ?>" class="<?= $currentPath === $href ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
          </div>
        </details>
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
      <form class="global-search" method="get" action="/workspace/search"><input name="q" placeholder="Find or Ask"></form>
      <form method="post" action="/logout"><button class="link-button">Logout</button></form>
    </header>
  <?php endif; ?>
  <?php require $contentView; ?>
</main>
</body>
</html>
