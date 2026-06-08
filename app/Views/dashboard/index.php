<?php
$brand = $app['brand'] ?? [];
$command = $commandData ?? ['metrics' => [], 'work' => [], 'capacity' => [], 'need' => [], 'influence' => []];
$widgets = [
    [
        'eyebrow' => 'Work Ready',
        'title' => 'Who has work?',
        'score' => $command['metrics']['work'] ?? 0,
        'summary' => 'Utilities, primes, municipalities, and programs with active or future fiber backbone work.',
        'href' => '/acquisition-command',
        'cta' => 'Open Work Intelligence',
        'items' => array_map(fn($row) => ['title' => $row['organization_name'] ?? 'Work signal', 'meta' => ($row['region_name'] ?? 'National') . ' · readiness ' . (int)($row['work_readiness_score'] ?? 0)], $command['work'] ?? []),
    ],
    [
        'eyebrow' => 'Capacity Available',
        'title' => 'Who can perform work?',
        'score' => $command['metrics']['capacity'] ?? 0,
        'summary' => 'Approved, preferred, strategic, and internal deployable capacity that can support pursuit decisions.',
        'href' => '/capacity-radar',
        'cta' => 'Open Capacity Radar',
        'items' => array_map(fn($row) => ['title' => $row['profile_name'] ?? 'Capacity provider', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . (int)($row['available_crews'] ?? 0) . ' crews'], $command['capacity'] ?? []),
    ],
    [
        'eyebrow' => 'Capacity Seeking Work',
        'title' => 'Who needs work?',
        'score' => $command['metrics']['need'] ?? 0,
        'summary' => 'Underutilized contractors and crews that may become fast capacity wins for Jackson Telcom.',
        'href' => '/hunting-lists',
        'cta' => 'Open Hunting Lists',
        'items' => array_map(fn($row) => ['title' => $row['organization_name'] ?? 'Need signal', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['workload_status'] ?? 'Unknown')], $command['need'] ?? []),
    ],
    [
        'eyebrow' => 'Influence Network',
        'title' => 'Who influences work?',
        'score' => $command['metrics']['influence'] ?? 0,
        'summary' => 'Project managers, construction leaders, utility contacts, and prime contacts who can create access.',
        'href' => '/relationship-graph',
        'cta' => 'Open Influence Network',
        'items' => array_map(fn($row) => ['title' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Influence contact', 'meta' => ($row['region_name'] ?? 'National') . ' · influence ' . (int)($row['final_influence_score'] ?? 0)], $command['influence'] ?? []),
    ],
];
$supportWidgets = [
    [
        'eyebrow' => 'Pursuits',
        'title' => 'Top Fiber Backbone Pursuits',
        'score' => $pursuitWidgets['metrics']['top_pursuits'] ?? 0,
        'summary' => 'Pursue work that advances fiber backbone construction, expansion, maintenance, and restoration.',
        'href' => '/pursuits',
        'cta' => 'Open Pursuit Board',
        'items' => array_map(fn($row) => ['title' => $row['name'] ?? 'Pursuit', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['recommended_decision'] ?? '')], $pursuitWidgets['topPursuits'] ?? []),
    ],
    [
        'eyebrow' => 'Growth Blockers',
        'title' => 'What is blocking growth?',
        'score' => $decisionWidgets['metrics']['critical_blockers'] ?? 0,
        'summary' => 'Structural blockers that prevent Jackson from converting intelligence into capacity, relationships, or work.',
        'href' => '/decision-support',
        'cta' => 'Open Decision Support',
        'items' => array_map(fn($row) => ['title' => $row['blocker_title'] ?? 'Growth blocker', 'meta' => ($row['severity'] ?? 'Medium') . ' · ' . ($row['region_name'] ?? 'National')], $decisionWidgets['blockers'] ?? []),
    ],
    [
        'eyebrow' => 'Ready For SyncERP',
        'title' => 'What is ready for handoff?',
        'score' => $syncWidgets['metrics']['ready'] ?? 0,
        'summary' => 'Execution packages that preserve pursuit, relationship, capacity, preconstruction, and risk context.',
        'href' => '/syncerp-integration',
        'cta' => 'Open Handoff Queue',
        'items' => array_map(fn($row) => ['title' => $row['package_name'] ?? 'Project package', 'meta' => ($row['region_name'] ?? 'National') . ' · readiness ' . (int)($row['readiness_score'] ?? 0)], $syncWidgets['ready'] ?? []),
    ],
    [
        'eyebrow' => 'Market Intelligence',
        'title' => 'Where will work come from?',
        'score' => $marketWidgets['metrics']['avg_readiness'] ?? 0,
        'summary' => 'Engineering, utility, municipal, funding, and prime intelligence that points 12-24 months ahead.',
        'href' => '/market-intelligence',
        'cta' => 'Open Market Intel',
        'items' => array_map(fn($row) => ['title' => $row['market'] ?? 'Market profile', 'meta' => ($row['region_name'] ?? 'National') . ' · readiness ' . (int)($row['market_readiness_score'] ?? 0)], $marketWidgets['profiles'] ?? []),
    ],
    [
        'eyebrow' => 'Strategic Accounts',
        'title' => 'Where should we invest?',
        'score' => $strategicIntel['metrics']['accounts'] ?? 0,
        'summary' => 'Account coverage across telecom providers, utilities, primes, cooperatives, and municipal broadband systems.',
        'href' => '/strategic-account-intelligence',
        'cta' => 'Open Account Intel',
        'items' => array_map(fn($row) => ['title' => $row['account_name'] ?? 'Strategic account', 'meta' => ($row['region_name'] ?? 'National') . ' · strategic ' . (int)($row['strategic_score'] ?? 0)], $strategicIntel['accounts'] ?? []),
    ],
    [
        'eyebrow' => 'Workforce Movers',
        'title' => 'Who runs the work?',
        'score' => $strategicIntel['metrics']['workforce'] ?? 0,
        'summary' => 'Project leaders, OSP managers, foremen, splicers, bore operators, and field leaders who can create access or capacity.',
        'href' => '/workforce-intelligence',
        'cta' => 'Open Workforce Intel',
        'items' => array_map(fn($row) => ['title' => $row['name'] ?? 'Workforce profile', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['role_type'] ?? '') . ' · recruit ' . (int)($row['recruitability_score'] ?? 0)], $strategicIntel['workforce'] ?? []),
    ],
    [
        'eyebrow' => 'Competitive Pressure',
        'title' => 'Who else is chasing work?',
        'score' => $strategicIntel['metrics']['avg_pressure'] ?? 0,
        'summary' => 'Competitor hiring, awards, office expansion, and subcontractor recruiting activity by market.',
        'href' => '/competitive-intelligence',
        'cta' => 'Open Competitor Intel',
        'items' => array_map(fn($row) => ['title' => $row['competitor_name'] ?? 'Competitor', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['threat_level'] ?? '') . ' · pressure ' . (int)($row['competitive_pressure_score'] ?? 0)], $strategicIntel['competitors'] ?? []),
    ],
];
?>
<section class="command-hero">
  <div class="command-mark"><?= htmlspecialchars($brand['logo_text'] ?? 'JT') ?></div>
  <div>
    <p class="eyebrow"><?= htmlspecialchars($brand['platform_name'] ?? 'Jackson Intelligence Platform') ?></p>
    <h1><?= htmlspecialchars($brand['command_center_title'] ?? 'Jackson Telcom Command Center') ?></h1>
    <p>The operating brain for who has work, who has capacity, who needs work, who influences work, and what Jackson Telcom should do next.</p>
  </div>
</section>

<nav class="dash-tabs">
  <a class="active" href="/">Command Center</a>
  <a href="/daily-brief">Executive Brief</a>
  <a href="/command/southeast">Mike Mode</a>
  <a href="/command/great-lakes">Ron Mode</a>
  <a href="/command/southwest">Shared Southwest</a>
  <a href="/operator-modes">Operator Modes</a>
</nav>

<?php require __DIR__ . '/../components/executive_doctrine.php'; ?>

<?php
$why = 'This is the first screen after login. It collapses the platform into the five decisions that matter today.';
$recommended = 'Work from Today\'s Priorities first, then inspect Work, Capacity, Need, and Influence only where action is required.';
$next = 'Pick one priority, contact the owner or target, and record the outcome before moving to the next action.';
require __DIR__ . '/../components/action_first.php';
?>

<?php $priorityActions = $decisionWidgets['topActions'] ?? []; require __DIR__ . '/../components/todays_priorities.php'; ?>

<?php $widgets = $widgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>

<section class="metrics command-metrics">
  <div><span>Approved Network</span><strong><?= (int)$totals['approved_subcontractors'] ?></strong></div>
  <div><span>Available Crews</span><strong><?= (int)$totals['available_crews'] ?></strong></div>
  <div><span>Open Opportunities</span><strong><?= (int)$totals['open_opportunities'] ?></strong></div>
  <div><span>Pipeline Value</span><strong>$<?= number_format((float)$totals['pipeline_value']) ?></strong></div>
  <div><span>Critical Actions</span><strong><?= (int)$totals['critical_recommendations'] ?></strong></div>
</section>

<section class="panel">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Regional Command</p>
      <h2>Theater readiness</h2>
    </div>
    <a class="btn secondary" href="/regions">Regional Command Centers</a>
  </div>
  <div class="region-strip">
    <?php foreach ($regions as $region): ?>
      <?php if ($region['name'] === 'National') { continue; } ?>
      <a href="/command/<?= strtolower(str_replace(' ', '-', $region['name'])) ?>">
        <strong><?= htmlspecialchars($region['name']) ?></strong>
        <span><?= htmlspecialchars($region['owner']) ?> · <?= (int)($region['capacity_score_value'] ?? 0) ?> capacity · <?= (int)($region['relationship_score'] ?? 0) ?> relationship</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php $widgets = $supportWidgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Demand Opportunities</h2><a class="btn secondary" href="/demand">Demand Engine</a></div>
    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Audience</th><th>Theater</th><th>Impact</th></tr></thead><tbody><?php foreach ($topDemandContent as $item): ?><tr><td><?= htmlspecialchars($item['title']) ?><br><small><?= htmlspecialchars($item['content_type']) ?></small></td><td><?= htmlspecialchars($item['audience']) ?></td><td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td><td>C <?= (int)$item['expected_capacity_impact'] ?> · R <?= (int)$item['expected_relationship_impact'] ?> · O <?= (int)$item['expected_opportunity_impact'] ?></td></tr><?php endforeach; ?><?php if (!$topDemandContent): ?><tr><td colspan="4">No demand opportunities waiting for review.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Strategic Recommendations</h2><a class="btn secondary" href="/executive-os">Executive OS</a></div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Recommendation</th><th>Owner</th><th>Impact</th></tr></thead><tbody><?php foreach ($executiveWidgets['recommendations'] ?? [] as $item): ?><tr><td><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span></td><td><strong><?= htmlspecialchars($item['recommendation_title']) ?></strong><br><small><?= htmlspecialchars($item['recommended_action']) ?></small></td><td><?= htmlspecialchars($item['owner']) ?><br><small><?= htmlspecialchars($item['region_name'] ?? 'National') ?></small></td><td><?= htmlspecialchars($item['expected_impact']) ?></td></tr><?php endforeach; ?><?php if (empty($executiveWidgets['recommendations'])): ?><tr><td colspan="4">No strategic recommendations waiting for review.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recent Activity</h2><a class="btn secondary" href="/activities">Activities</a></div>
    <div class="activity-list"><?php foreach ($recentActivities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?></span></div><?php endforeach; ?><?php if (!$recentActivities): ?><p>No activity recorded yet.</p><?php endif; ?></div>
  </div>
</section>

<?php $healthChecks = $platformData['health'] ?? []; require __DIR__ . '/../components/platform_health.php'; ?>
