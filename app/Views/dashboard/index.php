<?php
$brand = $app['brand'] ?? [];
$command = $commandData ?? ['metrics' => [], 'work' => [], 'capacity' => [], 'need' => [], 'influence' => []];
$topActions = array_slice($decisionWidgets['topActions'] ?? [], 0, 5);
$blockers = array_slice($decisionWidgets['blockers'] ?? [], 0, 3);
$opportunities = array_slice($topOpportunities ?? [], 0, 3);
$visualAlerts = array_slice($visualWidgets['alerts'] ?? [], 0, 3);
$onboardingMetrics = $onboardingWidgets['metrics'] ?? [];
$onboardingActions = array_slice($onboardingWidgets['recommendations'] ?? [], 0, 3);
$recentConversations = array_slice($recentConversations ?? [], 0, 3);
$healthChecks = array_slice($platformData['health'] ?? [], 0, 4);

$shortItems = fn(array $rows, callable $map): array => array_slice(array_map($map, $rows), 0, 3);
$stateWidgets = [
    [
        'eyebrow' => 'Who Has Work',
        'title' => 'Work Ready',
        'score' => $command['metrics']['work'] ?? 0,
        'summary' => 'Utilities, primes, municipalities, and programs with active or future fiber backbone work.',
        'href' => '/acquisition-command',
        'cta' => 'Open Work',
        'items' => $shortItems($command['work'] ?? [], fn($row) => ['title' => $row['organization_name'] ?? 'Work signal', 'meta' => ($row['region_name'] ?? 'National') . ' · readiness ' . (int)($row['work_readiness_score'] ?? 0)]),
    ],
    [
        'eyebrow' => 'Who Has Capacity',
        'title' => 'Capacity Available',
        'score' => $command['metrics']['capacity'] ?? 0,
        'summary' => 'Deployable crews and providers that can support real pursuit decisions.',
        'href' => '/capacity-radar',
        'cta' => 'Open Capacity',
        'items' => $shortItems($command['capacity'] ?? [], fn($row) => ['title' => $row['profile_name'] ?? 'Capacity provider', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . (int)($row['available_crews'] ?? 0) . ' crews']),
    ],
    [
        'eyebrow' => 'Who Needs Work',
        'title' => 'Capacity Seeking Work',
        'score' => $command['metrics']['need'] ?? 0,
        'summary' => 'Underutilized contractors and crews that may become fast capacity wins.',
        'href' => '/hunting-lists',
        'cta' => 'Open Need',
        'items' => $shortItems($command['need'] ?? [], fn($row) => ['title' => $row['organization_name'] ?? 'Need signal', 'meta' => ($row['region_name'] ?? 'National') . ' · ' . ($row['workload_status'] ?? 'Unknown')]),
    ],
    [
        'eyebrow' => 'Who Influences Work',
        'title' => 'Influence Network',
        'score' => $command['metrics']['influence'] ?? 0,
        'summary' => 'Project managers, construction leaders, utility contacts, and prime contacts who create access.',
        'href' => '/relationship-graph',
        'cta' => 'Open Influence',
        'items' => $shortItems($command['influence'] ?? [], fn($row) => ['title' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Influence contact', 'meta' => ($row['region_name'] ?? 'National') . ' · influence ' . (int)($row['final_influence_score'] ?? 0)]),
    ],
];

$actionHref = function (array $action): string {
    $type = $action['linked_record_type'] ?? $action['source_type'] ?? '';
    $id = (int)($action['linked_record_id'] ?? $action['source_id'] ?? 0);
    return match ($type) {
        'Executive Package' => $id ? '/executive-packages/detail?id=' . $id : '/executive-packages',
        'opportunity', 'Opportunity' => $id ? '/pursuits/detail?id=' . $id : '/pursuits',
        'preconstruction_profile', 'Preconstruction Profile' => $id ? '/preconstruction/detail?id=' . $id : '/preconstruction',
        'project_package', 'Project Package' => $id ? '/syncerp-integration/detail?id=' . $id : '/syncerp-integration',
        'contact', 'Contact' => $id ? '/contacts/detail?id=' . $id : '/contacts',
        'organization', 'Organization' => $id ? '/organizations/detail?id=' . $id : '/organizations',
        'subcontractor', 'Subcontractor' => $id ? '/subcontractor-acquisition/detail?id=' . $id : '/subcontractor-acquisition',
        'subcontractor_onboarding' => $id ? '/onboarding/subcontractors#ground-crew-' . $id : '/onboarding/subcontractors',
        'acquisition_target', 'Acquisition Target' => $id ? '/targets/detail?id=' . $id : '/targets',
        default => '/decision-support',
    };
};

$categoryLabels = ['Capacity' => 'Capacity', 'Relationship' => 'Relationship', 'Opportunity' => 'Pursuit', 'Demand' => 'Growth', 'Content' => 'Growth', 'Hunt' => 'Capacity', 'Subcontractor' => 'Capacity', 'Risk' => 'Risk'];
$criticalHealth = array_values(array_filter($healthChecks, fn($check) => in_array(strtolower((string)($check['status'] ?? 'pass')), ['fail', 'warn'], true)));
?>

<section class="command-hero">
  <div class="command-mark">
    <?php if (!empty($brand['logo_path'])): ?>
      <img src="<?= htmlspecialchars($brand['logo_path']) ?>" alt="<?= htmlspecialchars($brand['company_name'] ?? 'Jackson Telcom LLC') ?>">
    <?php else: ?>
      <?= htmlspecialchars($brand['logo_text'] ?? 'JT') ?>
    <?php endif; ?>
  </div>
  <div>
    <p class="eyebrow"><?= htmlspecialchars($brand['platform_name'] ?? 'Jackson Intelligence Platform') ?></p>
    <h1><?= htmlspecialchars($brand['command_center_title'] ?? 'Jackson Telcom Command Center') ?></h1>
    <p>One operating screen for work, capacity, relationships, risks, and today’s next moves.</p>
  </div>
</section>

<nav class="dash-tabs">
  <a class="active" href="/">Command Center</a>
  <a href="/daily-brief">Daily Brief</a>
  <a href="/executive-briefs">Executive Brief</a>
  <a href="/executive-packages">Decision Queue</a>
  <a href="/decision-visuals">Executive Maps</a>
  <a href="/ownership">Ownership</a>
</nav>

<section class="action-first-grid">
  <article><span>What This Is</span><p>The first screen after login. It shows what needs action now.</p></article>
  <article><span>Why It Matters</span><p>Work, capacity, relationship, and onboarding issues lose value when they sit.</p></article>
  <article><span>Next Step</span><p>Work the Top 5 first. Then clear blockers and intake items waiting on Jackson.</p></article>
  <article><span>Risk Of Inaction</span><p>High-value work and capacity can stall while competitors move first.</p></article>
</section>

<section class="panel command-priorities">
  <div class="panel-title">
    <div><p class="eyebrow">Today</p><h2>Top 5 Actions</h2></div>
    <a class="btn secondary" href="/decision-support">Open Action Queue</a>
  </div>
  <div class="priority-list">
    <?php foreach ($topActions as $action): ?>
      <?php $category = $categoryLabels[$action['action_category'] ?? ''] ?? ($action['action_category'] ?? 'Work'); ?>
      <article>
        <span class="priority <?= strtolower($action['priority'] ?? 'medium') ?>"><?= htmlspecialchars($category) ?> · <?= htmlspecialchars($action['priority'] ?? 'Medium') ?></span>
        <h3><a href="<?= htmlspecialchars($actionHref($action)) ?>"><?= htmlspecialchars($action['action_title'] ?? $action['title'] ?? 'Action required') ?></a></h3>
        <p><?= htmlspecialchars($action['reason'] ?? $action['why_it_matters'] ?? 'This needs operator attention.') ?></p>
        <p><strong>Next:</strong> <?= htmlspecialchars($action['recommended_next_step'] ?? $action['recommended_next_action'] ?? 'Assign owner and complete the next step.') ?></p>
        <small><?= htmlspecialchars($action['owner'] ?? $action['assigned_owner'] ?? 'Admin') ?> · <?= htmlspecialchars($action['region_name'] ?? $action['region'] ?? 'National') ?></small>
      </article>
    <?php endforeach; ?>
    <?php if (!$topActions): ?><article class="empty-state"><h3>No urgent actions</h3><p>Run the acquisition cycle or review escalations if this seems wrong.</p></article><?php endif; ?>
  </div>
</section>

<section class="grid three">
  <div class="panel">
    <div class="panel-title"><h2>Critical Blockers</h2><a class="btn secondary" href="/decision-support">Review</a></div>
    <div class="command-items">
      <?php foreach ($blockers as $item): ?>
        <div>
          <strong><?= htmlspecialchars($item['blocker_title'] ?? 'Growth blocker') ?></strong>
          <span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['severity'] ?? 'Medium') ?> · <?= htmlspecialchars($item['recommended_resolution'] ?? 'Assign owner and next action.') ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$blockers): ?><div class="empty-state"><strong>No critical blockers</strong><span>Current operating blockers are clear.</span></div><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Onboarding Waiting</h2><a class="btn secondary" href="/onboarding">Open</a></div>
    <div class="mini-metrics compact">
      <div><span>Capacity Being Created</span><strong><?= (int)($onboardingMetrics['New Capacity Being Created'] ?? 0) ?></strong></div>
      <div><span>Missing Documents</span><strong><?= (int)($onboardingMetrics['Missing Documents'] ?? 0) ?></strong></div>
    </div>
    <div class="command-items">
      <?php foreach ($onboardingActions as $item): ?>
        <div><strong><?= htmlspecialchars($item['title'] ?? 'Onboarding action') ?></strong><span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['recommended_next_action'] ?? $item['recommended_action'] ?? 'Complete readiness review.') ?></span></div>
      <?php endforeach; ?>
      <?php if (!$onboardingActions): ?><div class="empty-state"><strong>No onboarding actions</strong><span>Subcontractor and readiness queues are clear.</span></div><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Recent Conversations</h2><a class="btn secondary" href="/communications">Log</a></div>
    <div class="command-items">
      <?php foreach ($recentConversations as $item): ?>
        <div>
          <strong><?= htmlspecialchars($item['summary'] ?? $item['title'] ?? 'Conversation') ?></strong>
          <span><?= htmlspecialchars(substr($item['communication_date'] ?? $item['activity_date'] ?? '', 0, 10)) ?> · <?= htmlspecialchars($item['owner'] ?? 'Unassigned') ?> · <?= htmlspecialchars($item['next_step'] ?? $item['next_action'] ?? 'Create follow-up if needed.') ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentConversations): ?><div class="empty-state"><strong>No conversations yet</strong><span>Log calls, meetings, notes, drafts, and follow-ups as they happen.</span></div><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Company State</h2><span class="status">Four Hunting Questions</span></div>
  <?php $widgets = $stateWidgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Decision Queue</h2><a class="btn secondary" href="/executive-packages">Open Queue</a></div>
    <div class="command-items">
      <?php foreach ($opportunities as $item): ?>
        <div>
          <strong><?= htmlspecialchars($item['name']) ?></strong>
          <span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · $<?= number_format((float)($item['estimated_value'] ?? 0)) ?> · <?= htmlspecialchars($item['next_action'] ?: 'Assign next action.') ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$opportunities): ?><div class="empty-state"><strong>No open opportunities</strong><span>Current opportunity queue is clear.</span></div><?php endif; ?>
    </div>
  </div>

  <?php if ($visualAlerts): ?>
  <div class="panel">
    <div class="panel-title"><h2>Executive Maps</h2><a class="btn secondary" href="/decision-visuals">Open Maps</a></div>
    <div class="command-items">
      <?php foreach ($visualAlerts as $alert): ?>
        <div>
          <strong><?= htmlspecialchars($alert['title']) ?></strong>
          <span><?= htmlspecialchars($alert['why']) ?> · <?= htmlspecialchars($alert['action']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<section class="panel system-trust">
  <div class="panel-title"><h2>System Trust</h2><a class="btn secondary" href="/platform-review">Open System Review</a></div>
  <div class="health-grid">
    <?php foreach ($healthChecks as $check): ?>
      <div>
        <span class="status <?= strtolower($check['status'] ?? 'pass') ?>"><?= htmlspecialchars($check['status'] ?? 'Pass') ?></span>
        <strong><?= htmlspecialchars($check['check_type'] ?? 'System Check') ?></strong>
        <small><?= htmlspecialchars($check['module_name'] ?? '') ?> · <?= (int)($check['issue_count'] ?? 0) ?> issues</small>
      </div>
    <?php endforeach; ?>
    <?php if (!$healthChecks): ?><p>No system checks have been generated.</p><?php endif; ?>
  </div>
  <?php if ($criticalHealth): ?><p class="muted">Review failed or warning checks before relying on stale or incomplete records.</p><?php endif; ?>
</section>
