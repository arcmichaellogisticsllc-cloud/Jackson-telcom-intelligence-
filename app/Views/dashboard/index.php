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
$workflowQueues = $workflowQueues ?? [];
$missionLanes = $workflowQueues['mission'] ?? [];
$workflowSummary = $workflowQueues['summary'] ?? [];
$reviewQueue = array_slice($workflowQueues['review'] ?? [], 0, 5);
$qualityQueue = array_slice($workflowQueues['quality'] ?? [], 0, 5);
$captureQueue = array_slice($workflowQueues['capture'] ?? [], 0, 5);
$onboardingQueue = array_slice($workflowQueues['onboarding'] ?? [], 0, 5);
$documentQueue = array_slice($workflowQueues['documents'] ?? [], 0, 5);
$decisionQueue = array_slice($workflowQueues['decisions'] ?? [], 0, 5);
$handoffQueue = array_slice($workflowQueues['handoff'] ?? [], 0, 5);

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
$workflowCards = [
    [
        'title' => 'Onboard Ground Crew',
        'current' => ((int)($onboardingMetrics['New Capacity Being Created'] ?? 0)) . ' capacity records in onboarding',
        'next' => ((int)($onboardingMetrics['Missing Documents'] ?? 0)) > 0 ? 'Generate intake links or review missing documents.' : 'Add the next real crew or review submitted intake.',
        'href' => '/onboarding/subcontractors#add-ground-crew',
        'cta' => 'Open Onboarding',
    ],
    [
        'title' => 'Clear Capacity Blocker',
        'current' => count($blockers) . ' critical blockers on this screen',
        'next' => $blockers ? 'Assign capacity hunts and move matching providers into onboarding.' : 'No critical blocker is active. Review capacity radar for emerging gaps.',
        'href' => '/capacity-radar',
        'cta' => 'Open Capacity Radar',
    ],
    [
        'title' => 'Move Work Toward Pursuit',
        'current' => count($opportunities) . ' top opportunities surfaced',
        'next' => $opportunities ? 'Open the decision queue and confirm pursue, hold, or avoid.' : 'No opportunity is currently waiting on this screen.',
        'href' => '/executive-packages',
        'cta' => 'Open Decision Queue',
    ],
];

$hasWorkflowQueue = $captureQueue || $reviewQueue || $qualityQueue || $onboardingQueue || $documentQueue || $decisionQueue || $handoffQueue;
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
    <p>Acquire work, acquire capacity, acquire influence, and convert all three into revenue.</p>
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

<section class="operator-note">
  <strong>Start here:</strong>
  <span>Work the mission lanes first, then clear blockers, intake items, decisions, and handoffs waiting on Jackson.</span>
</section>

<section class="panel mission-spine">
  <div class="panel-title">
    <div><p class="eyebrow">Mission</p><h2>Acquire Work. Acquire Capacity. Acquire Influence. Convert To Revenue.</h2></div>
    <a class="btn secondary" href="/decision-support">Top Actions</a>
  </div>
  <div class="mission-lanes">
    <?php foreach (['work', 'capacity', 'influence', 'revenue'] as $laneKey): ?>
      <?php $lane = $missionLanes[$laneKey] ?? ['title' => ucfirst($laneKey), 'question' => '', 'count' => 0, 'items' => []]; ?>
      <article>
        <div class="lane-head">
          <div>
            <span><?= htmlspecialchars($lane['question'] ?? '') ?></span>
            <h3><?= htmlspecialchars($lane['title'] ?? ucfirst($laneKey)) ?></h3>
          </div>
          <strong><?= (int)($lane['count'] ?? 0) ?></strong>
        </div>
        <div class="mission-items">
          <?php foreach (array_slice($lane['items'] ?? [], 0, 5) as $item): ?>
            <a class="mission-item" href="<?= htmlspecialchars($item['href'] ?? '#') ?>">
              <strong><?= htmlspecialchars($item['title'] ?? 'Mission item') ?></strong>
              <span><?= htmlspecialchars($item['region'] ?? 'National') ?> · <?= htmlspecialchars($item['status'] ?? 'Needs Review') ?> · <?= htmlspecialchars($item['owner'] ?? 'Unassigned') ?></span>
              <?php if (!empty($item['blockers'])): ?><em><?= htmlspecialchars(implode(' / ', array_slice($item['blockers'], 0, 2))) ?></em><?php endif; ?>
              <small><?= htmlspecialchars($item['next_action'] ?? 'Confirm next action.') ?></small>
            </a>
          <?php endforeach; ?>
          <?php if (empty($lane['items'])): ?><div class="empty-state"><strong>No active records</strong><span>This lane is ready for real data.</span></div><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel command-workflow">
  <div class="panel-title">
    <div><p class="eyebrow">Operating Loop</p><h2>What Needs To Move Next</h2></div>
    <a class="btn secondary" href="/onboarding/subcontractors#add-ground-crew">Start Subcontractor Intake</a>
  </div>
  <div class="workflow-status-strip">
    <div><span>Review</span><strong><?= (int)($workflowSummary['review'] ?? 0) ?></strong></div>
    <div><span>Data Quality</span><strong><?= (int)($workflowSummary['quality'] ?? 0) ?></strong></div>
    <div><span>Active Intake Links</span><strong><?= (int)($workflowSummary['intake'] ?? 0) ?></strong></div>
    <div><span>Documents Waiting</span><strong><?= (int)($workflowSummary['documents'] ?? 0) ?></strong></div>
    <div><span>Handoff</span><strong><?= (int)($workflowSummary['handoff'] ?? 0) ?></strong></div>
  </div>
  <?php if (!$hasWorkflowQueue): ?>
    <div class="empty-state"><strong>No workflow queues waiting</strong><span>Add or import real intelligence, start subcontractor onboarding, or run the acquisition cycle when new work is available.</span></div>
  <?php endif; ?>
  <div class="workflow-queue-grid">
    <?php if ($captureQueue): ?>
      <article>
        <h3>1. Capture</h3>
        <p>New intelligence waiting for review.</p>
        <?php foreach ($captureQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['title'] ?: 'Raw intelligence') ?></strong>
            <span><?= htmlspecialchars($item['organization_name'] ?: 'Unknown source') ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?></span>
            <a class="btn secondary" href="/harvesters">Review Source</a>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($reviewQueue): ?>
      <article>
        <h3>2. Review</h3>
        <p>Decide whether the record is useful, duplicate, incomplete, or bad.</p>
        <?php foreach ($reviewQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['title']) ?></strong>
            <span><?= htmlspecialchars($item['severity']) ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['recommended_resolution'] ?: 'Review and resolve.') ?></span>
            <form method="post" action="/production-readiness/review" class="inline-form">
              <input type="hidden" name="return_to" value="/">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="status" value="Resolved">
              <input type="hidden" name="resolution_notes" value="Resolved from Command Center operating queue.">
              <button class="btn secondary">Mark Reviewed</button>
            </form>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($qualityQueue): ?>
      <article>
        <h3>3. Clean Data</h3>
        <p>Fix bad, missing, stale, duplicate, or conflicting records.</p>
        <?php foreach ($qualityQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['title']) ?></strong>
            <span><?= htmlspecialchars($item['issue_type']) ?> · <?= htmlspecialchars($item['severity']) ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?></span>
            <form method="post" action="/production-readiness/data-quality/update" class="inline-form">
              <input type="hidden" name="return_to" value="/">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="status" value="In Review">
              <input type="hidden" name="resolution_notes" value="Moved into review from Command Center.">
              <button class="btn secondary">Start Review</button>
            </form>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($onboardingQueue): ?>
      <article>
        <h3>4. Onboard</h3>
        <p>Get subcontractor information and readiness documents without manual re-entry.</p>
        <?php foreach ($onboardingQueue as $item): ?>
          <?php $missing = array_filter(array_map('trim', preg_split('/[;,]/', (string)($item['missing_items'] ?? '')) ?: [])); ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['company_name'] ?? 'Subcontractor onboarding') ?></strong>
            <span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['onboarding_status']) ?><?= $missing ? ' · Missing: ' . htmlspecialchars(implode(', ', array_slice($missing, 0, 4))) : '' ?></span>
            <div class="inline-form">
              <a class="btn secondary" href="/onboarding/subcontractors/detail?id=<?= (int)$item['id'] ?>">Open</a>
              <form method="post" action="/onboarding/intake-link" class="inline-form">
                <input type="hidden" name="return_to" value="/">
                <input type="hidden" name="onboarding_id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="expires_days" value="14">
                <button class="btn secondary"><?= (int)($item['active_intake_links'] ?? 0) > 0 ? 'Refresh Intake Link' : 'Generate Intake Link' ?></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($documentQueue): ?>
      <article>
        <h3>5. Verify Documents</h3>
        <p>Review submitted documents and request anything still missing.</p>
        <?php foreach ($documentQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['company_name'] ?? 'Onboarding document') ?></strong>
            <span><?= htmlspecialchars($item['document_type']) ?> · <?= htmlspecialchars($item['status']) ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?></span>
            <a class="btn secondary" href="/onboarding/subcontractors/detail?id=<?= (int)$item['onboarding_id'] ?>">Review Documents</a>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($decisionQueue): ?>
      <article>
        <h3>6. Decide</h3>
        <p>Executive packages that need a decision, owner action, or close-out.</p>
        <?php foreach ($decisionQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['package_title']) ?></strong>
            <span><?= htmlspecialchars($item['package_type']) ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?> · impact <?= (int)($item['impact_score'] ?? 0) ?></span>
            <a class="btn secondary" href="/executive-packages/detail?id=<?= (int)$item['id'] ?>">Open Decision</a>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>

    <?php if ($handoffQueue): ?>
      <article>
        <h3>7. Handoff</h3>
        <p>Packages that are ready or nearly ready for SyncERP handoff review.</p>
        <?php foreach ($handoffQueue as $item): ?>
          <div class="queue-row">
            <strong><?= htmlspecialchars($item['package_name']) ?></strong>
            <span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · <?= htmlspecialchars($item['package_status']) ?> · readiness <?= (int)($item['readiness_score'] ?? 0) ?></span>
            <a class="btn secondary" href="/syncerp-integration/detail?id=<?= (int)$item['id'] ?>">Open Handoff</a>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endif; ?>
  </div>
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

<section class="panel">
  <div class="panel-title"><div><p class="eyebrow">Workflow</p><h2>Move Work Forward</h2></div><span class="status">Guided Next Steps</span></div>
  <div class="workflow-rail">
    <?php foreach ($workflowCards as $card): ?>
      <article>
        <h3><?= htmlspecialchars($card['title']) ?></h3>
        <p><strong>Current:</strong> <?= htmlspecialchars($card['current']) ?></p>
        <p><strong>Next:</strong> <?= htmlspecialchars($card['next']) ?></p>
        <a class="btn secondary" href="<?= htmlspecialchars($card['href']) ?>"><?= htmlspecialchars($card['cta']) ?></a>
      </article>
    <?php endforeach; ?>
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
