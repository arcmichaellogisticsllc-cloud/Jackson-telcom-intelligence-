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

<?php
$why = 'This is the first screen after login. It collapses the platform into the five decisions that matter today.';
$recommended = 'Work from Today\'s Priorities first, then inspect Work, Capacity, Need, and Influence only where action is required.';
$next = 'Pick one priority, contact the owner or target, and record the outcome before moving to the next action.';
$risk = 'If this screen gets ignored, high-value work, capacity, and relationship actions can sit while competitors move first.';
require __DIR__ . '/../components/action_first.php';
?>

<?php $priorityActions = $decisionWidgets['topActions'] ?? []; require __DIR__ . '/../components/todays_priorities.php'; ?>

<section class="panel command-priorities">
  <div class="panel-title"><h2>Decision Visuals</h2><a class="btn secondary" href="/decision-visuals">Open Visual Hub</a></div>
  <div class="priority-list">
    <?php foreach (array_slice($visualWidgets['alerts'] ?? [], 0, 3) as $alert): ?>
      <article>
        <span class="priority high">Visual Alert · <?= (int)$alert['score'] ?></span>
        <h3><?= htmlspecialchars($alert['title']) ?></h3>
        <p><?= htmlspecialchars($alert['why']) ?></p>
        <small><?= htmlspecialchars($alert['action']) ?></small>
        <p><a class="btn secondary" href="<?= htmlspecialchars($alert['href']) ?>">Open</a></p>
      </article>
    <?php endforeach; ?>
    <?php if (empty($visualWidgets['alerts'])): ?><article><h3>No visual alerts</h3><p>Decision visuals have no critical alerts for this operator view.</p></article><?php endif; ?>
  </div>
</section>

<?php $widgets = $widgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>

<section class="panel">
  <div class="panel-title"><h2>Onboarding Readiness</h2><a class="btn secondary" href="/onboarding">Open Onboarding</a></div>
  <p class="muted">Discovery becomes operationally ready capacity, workforce, strategic accounts, and markets here.</p>
  <div class="mini-metrics">
    <?php foreach (($onboardingWidgets['metrics'] ?? []) as $label => $value): ?>
      <div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div>
    <?php endforeach; ?>
  </div>
  <div class="command-items">
    <?php foreach (array_slice($onboardingWidgets['recommendations'] ?? [], 0, 4) as $item): ?>
      <div><strong><?= htmlspecialchars($item['title'] ?? 'Onboarding action') ?></strong><span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['recommended_action'] ?? 'Complete readiness review.') ?></span></div>
    <?php endforeach; ?>
    <?php if (empty($onboardingWidgets['recommendations'])): ?><div><strong>No onboarding actions</strong><span>Current onboarding queues have no open recommendations.</span></div><?php endif; ?>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Risks</h2><a class="btn secondary" href="/decision-support">Review Risks</a></div>
    <div class="table-wrap"><table><thead><tr><th>Risk</th><th>Theater</th><th>Severity</th><th>Next Step</th></tr></thead><tbody><?php foreach (array_slice($decisionWidgets['blockers'] ?? [], 0, 5) as $item): ?><tr><td><strong><?= htmlspecialchars($item['blocker_title'] ?? 'Growth blocker') ?></strong><br><small><?= htmlspecialchars($item['reason'] ?? '') ?></small></td><td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td><td><span class="priority <?= strtolower($item['severity'] ?? 'medium') ?>"><?= htmlspecialchars($item['severity'] ?? 'Medium') ?></span></td><td><?= htmlspecialchars($item['recommended_resolution'] ?? 'Assign owner and next action.') ?></td></tr><?php endforeach; ?><?php if (empty($decisionWidgets['blockers'])): ?><tr><td colspan="4">No critical risks are active.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Opportunities</h2><a class="btn secondary" href="/opportunities">Open Opportunities</a></div>
    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Theater</th><th>Value</th><th>Next Action</th></tr></thead><tbody><?php foreach (array_slice($topOpportunities, 0, 5) as $item): ?><tr><td><strong><?= htmlspecialchars($item['name']) ?></strong><br><small><?= htmlspecialchars($item['market'] ?? '') ?></small></td><td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td><td>$<?= number_format((float)$item['estimated_value']) ?></td><td><?= htmlspecialchars($item['next_action'] ?: 'Assign next action.') ?></td></tr><?php endforeach; ?><?php if (!$topOpportunities): ?><tr><td colspan="4">No open opportunities are active.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Operating Rhythm</h2><a class="btn secondary" href="/operating-rhythm">Open Rhythm</a></div>
    <div class="mini-metrics">
      <div><span>Rhythm Score</span><strong><?= (int)($maturityWidgets['metrics']['avg_score'] ?? 0) ?></strong></div>
      <div><span>Due Today</span><strong><?= (int)($maturityWidgets['metrics']['due_today'] ?? 0) ?></strong></div>
      <div><span>Overdue</span><strong><?= (int)($maturityWidgets['metrics']['overdue'] ?? 0) ?></strong></div>
      <div><span>Pressure Spikes</span><strong><?= (int)($maturityWidgets['metrics']['pressure_spikes'] ?? 0) ?></strong></div>
    </div>
    <div class="command-items">
      <?php foreach (array_slice($maturityWidgets['overdue'] ?? [], 0, 3) as $review): ?><div><strong><?= htmlspecialchars($review['rhythm_name']) ?></strong><span><?= htmlspecialchars($review['owner']) ?> · <?= htmlspecialchars($review['region_name'] ?? 'National') ?> · <?= htmlspecialchars($review['status']) ?></span></div><?php endforeach; ?>
      <?php if (empty($maturityWidgets['overdue'])): ?><div><strong>No overdue reviews</strong><span>Cadence is current for this operator view.</span></div><?php endif; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Workforce / Competitive Watch</h2><a class="btn secondary" href="/strategic-account-intelligence">Open Intel</a></div>
    <div class="command-items">
      <?php foreach (array_slice($maturityWidgets['workforceMovers'] ?? [], 0, 2) as $mover): ?><div><strong><?= htmlspecialchars($mover['name']) ?></strong><span><?= htmlspecialchars($mover['movement_type']) ?> · recruitability <?= (int)$mover['recruitability_score'] ?></span></div><?php endforeach; ?>
      <?php foreach (array_slice($maturityWidgets['pressureSpikes'] ?? [], 0, 2) as $spike): ?><div><strong><?= htmlspecialchars($spike['competitor_name']) ?></strong><span><?= htmlspecialchars($spike['market']) ?> · <?= htmlspecialchars($spike['threat_level']) ?> · <?= (int)$spike['competitive_pressure_score'] ?></span></div><?php endforeach; ?>
    </div>
  </div>
</section>

<?php $healthChecks = $platformData['health'] ?? []; require __DIR__ . '/../components/platform_health.php'; ?>
