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
      <?php if (!empty($brand['logo_path'])): ?>
        <span class="brand-mark image"><img src="<?= htmlspecialchars($brand['logo_path']) ?>" alt="<?= htmlspecialchars($brand['company_name'] ?? 'Jackson Telcom LLC') ?>"></span>
      <?php else: ?>
        <span class="brand-mark"><?= htmlspecialchars($brand['logo_text'] ?? 'JT') ?></span>
      <?php endif; ?>
      <span class="brand-copy">
        <strong><?= htmlspecialchars($brand['company_name'] ?? 'Jackson Telcom LLC') ?></strong>
        <em><?= htmlspecialchars($brand['command_center_title'] ?? 'Jackson Telcom Command Center') ?></em>
      </span>
    </a>
    <nav>
      <?php
      $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
      $navGroups = [
        'COMMAND' => ['/' => 'Command Center','/executive-os' => 'Executive OS','/daily-brief' => 'Daily Brief','/executive-briefs' => 'Executive Brief','/executive-packages' => 'Decision Packages','/decision-visuals' => 'Decision Visuals','/strategic-review' => 'Strategic Review'],
        'WORK' => ['/workspace/work' => 'Work Workspace','/acquisition-command' => 'Work Intelligence','/strategic-account-intelligence' => 'Strategic Accounts','/opportunities' => 'Opportunities','/pursuits' => 'Pursuits','/preconstruction' => 'Preconstruction'],
        'CAPACITY' => ['/workspace/capacity' => 'Capacity Workspace','/capacity-radar' => 'Capacity Radar','/subcontractor-acquisition' => 'Subcontractor Network','/subcontractors' => 'Preferred Network','/targets' => 'Strategic Partners','/workforce-intelligence' => 'Workforce Intelligence'],
        'RELATIONSHIPS' => ['/workspace/relationships' => 'Relationship Workspace','/communications' => 'Communications','/contacts' => 'Contacts','/organizations' => 'Organizations','/relationship-graph' => 'Relationship Graph','/network-intelligence' => 'Network Intelligence'],
        'MARKET' => ['/workspace/market' => 'Market Workspace','/signals' => 'Signals','/escalations' => 'Escalations','/watchlists' => 'Watchlists','/market-intelligence' => 'Market Intelligence','/competitive-intelligence' => 'Competitive Intelligence','/harvesters' => 'Acquisition Harvesters'],
        'GROWTH' => ['/workspace/growth' => 'Growth Workspace','/demand' => 'Demand','/traffic' => 'Content','/outreach' => 'Distribution','/demand-briefing' => 'Channels'],
        'ONBOARDING' => ['/workspace/onboarding' => 'Onboarding Workspace','/onboarding' => 'Overview','/onboarding/subcontractors' => 'Subcontractors','/onboarding/workforce' => 'Workforce','/onboarding/strategic-accounts' => 'Strategic Accounts','/onboarding/markets' => 'Markets','/onboarding/documents' => 'Documents','/onboarding/reviews' => 'Reviews','/onboarding/metrics' => 'Metrics'],
        'OPERATIONS' => ['/workspace/operations' => 'Operations Workspace','/syncerp-integration' => 'SyncERP Integration','/syncerp-handoff-brief' => 'Handoff Brief'],
        'SYSTEM' => ['/workspace/system' => 'System Workspace','/production-readiness' => 'Production Readiness','/data-quality' => 'Data Quality Review','/connector-runs' => 'Connector Runs','/audit-logs' => 'Audit Logs','/operating-rhythm' => 'Operating Rhythm','/platform-review' => 'Platform Health','/settings' => 'Settings','/recommendations' => 'Recommendations','/activities' => 'Activities','/warehouse' => 'Intelligence Warehouse'],
      ];
      $pathFor = function (string $href): string {
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        return $path;
      };
      $opensFor = function (string $href) use ($currentPath, $pathFor): bool {
        $path = $pathFor($href);
        return $currentPath === $path || ($path !== '/' && str_starts_with($currentPath, rtrim($path, '/') . '/'));
      };
      $activeFor = function (string $href, bool $groupHasExact) use ($currentPath, $pathFor): bool {
        $path = $pathFor($href);
        if ($currentPath === $path) {
          return true;
        }
        return !$groupHasExact && $path !== '/' && str_starts_with($currentPath, rtrim($path, '/') . '/');
      };
      ?>
      <?php foreach ($navGroups as $group => $links): ?>
        <?php
        $paths = array_map($pathFor, array_keys($links));
        $groupHasExact = in_array($currentPath, $paths, true);
        $isOpen = $group === 'COMMAND' && $currentPath === '/';
        foreach (array_keys($links) as $href) { $isOpen = $isOpen || $opensFor($href); }
        ?>
        <details class="workspace-nav" <?= $isOpen ? 'open' : '' ?>>
          <summary><?= htmlspecialchars($group) ?></summary>
          <div>
            <?php foreach ($links as $href => $label): ?>
              <a href="<?= $href ?>" class="<?= $activeFor($href, $groupHasExact) ? 'active' : '' ?>"><?= $label ?></a>
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
  <?php $flashMessages = $_SESSION['flash_messages'] ?? []; unset($_SESSION['flash_messages']); ?>
  <?php if ($flashMessages): ?>
    <section class="flash-stack" aria-live="polite">
      <?php foreach ($flashMessages as $flash): ?>
        <div class="flash <?= htmlspecialchars($flash['type'] ?? 'success') ?>"><?= htmlspecialchars($flash['message'] ?? '') ?></div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
  <?php require $contentView; ?>
</main>
</body>
</html>
