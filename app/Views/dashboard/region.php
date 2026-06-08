<?php
$slug = strtolower(str_replace(' ', '-', $region['name']));
$ownerLabel = $region['name'] === 'Southwest' ? 'Mike / Ron Shared' : $region['owner'];
$command = $commandData ?? ['metrics' => [], 'work' => [], 'capacity' => [], 'need' => [], 'influence' => []];
$widgets = [
    [
        'eyebrow' => 'Work',
        'title' => 'Who has work?',
        'score' => $command['metrics']['work'] ?? 0,
        'summary' => 'Current and future work assets in this theater.',
        'href' => '/acquisition-command/' . $slug,
        'cta' => 'Open Work',
        'items' => array_map(fn($row) => ['title' => $row['organization_name'] ?? 'Work signal', 'meta' => 'Readiness ' . (int)($row['work_readiness_score'] ?? 0) . ' · ' . ($row['work_status'] ?? '')], $command['work'] ?? []),
    ],
    [
        'eyebrow' => 'Capacity',
        'title' => 'Who can perform work?',
        'score' => $command['metrics']['capacity'] ?? 0,
        'summary' => 'Deployable providers and internal capacity available to support this theater.',
        'href' => '/capacity-radar/' . $slug,
        'cta' => 'Open Capacity',
        'items' => array_map(fn($row) => ['title' => $row['profile_name'] ?? 'Capacity provider', 'meta' => (int)($row['available_crews'] ?? 0) . ' crews · ' . ($row['mobilization_readiness'] ?? '')], $command['capacity'] ?? []),
    ],
    [
        'eyebrow' => 'Need',
        'title' => 'Who needs work?',
        'score' => $command['metrics']['need'] ?? 0,
        'summary' => 'Contractors with available capacity, idle crews, or possible work-seeking posture.',
        'href' => '/targets/hunting?region=' . $slug,
        'cta' => 'Open Hunting',
        'items' => array_map(fn($row) => ['title' => $row['organization_name'] ?? 'Need signal', 'meta' => ($row['workload_status'] ?? 'Unknown') . ' · score ' . (int)($row['need_score'] ?? 0)], $command['need'] ?? []),
    ],
    [
        'eyebrow' => 'Influence',
        'title' => 'Who influences work?',
        'score' => $command['metrics']['influence'] ?? 0,
        'summary' => 'Project managers, utility contacts, prime contacts, and construction decision makers.',
        'href' => '/relationship-graph/' . $slug,
        'cta' => 'Open Influence',
        'items' => array_map(fn($row) => ['title' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Influence contact', 'meta' => ($row['influence_role'] ?? 'Unknown') . ' · score ' . (int)($row['final_influence_score'] ?? 0)], $command['influence'] ?? []),
    ],
];
?>
<section class="page-header command-page-header">
  <p class="eyebrow"><?= htmlspecialchars($ownerLabel) ?> Mode</p>
  <h1><?= htmlspecialchars($region['name']) ?> Command Center</h1>
  <p>States covered: <?= htmlspecialchars($region['states']) ?>. This screen filters Jackson Telcom intelligence into work, capacity, need, influence, and the next actions for this theater.</p>
</section>

<nav class="dash-tabs">
  <a href="/">Command Center</a>
  <a class="<?= $region['name'] === 'Southeast' ? 'active' : '' ?>" href="/command/southeast">Mike Mode</a>
  <a class="<?= $region['name'] === 'Great Lakes' ? 'active' : '' ?>" href="/command/great-lakes">Ron Mode</a>
  <a class="<?= $region['name'] === 'Southwest' ? 'active' : '' ?>" href="/command/southwest">Shared Southwest</a>
  <a href="/daily-brief">Executive Brief</a>
</nav>

<?php
$why = $region['name'] . ' is an operating theater. The page should tell ' . $ownerLabel . ' where work, capacity, need, and influence are moving.';
$recommended = 'Work the highest decision-score actions first, then inspect capacity gaps and relationship blockers.';
$next = 'Complete or dismiss one priority, capture the outcome, then move the target, hunt, or relationship forward.';
require __DIR__ . '/../components/action_first.php';
?>

<?php $priorityActions = $decisionWidgets['topActions'] ?? []; require __DIR__ . '/../components/todays_priorities.php'; ?>

<?php $widgets = $widgets; $columns = 4; require __DIR__ . '/../components/command_widgets.php'; ?>

<section class="metrics command-metrics">
  <div><span>Capacity Score</span><strong><?= (int)$score['score'] ?></strong><small><?= htmlspecialchars($score['category']) ?></small></div>
  <div><span>Approved Network</span><strong><?= (int)$score['approved_count'] ?></strong></div>
  <div><span>Available Crews</span><strong><?= (int)$score['crew_count'] ?></strong></div>
  <div><span>Compliance Ready</span><strong><?= (int)$score['compliant_count'] ?></strong></div>
  <div><span>Services Covered</span><strong><?= (int)$score['service_coverage'] ?>/5</strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Capacity Gaps</h2><a class="btn secondary" href="/capacity-radar/<?= $slug ?>">Capacity Radar</a></div>
    <div class="gap-list">
      <?php foreach ($gaps as $service => $gap): ?>
        <div class="<?= $gap['gap'] > 0 ? 'gap' : '' ?>"><span><?= htmlspecialchars($service) ?></span><strong><?= (int)$gap['current'] ?> / <?= (int)$gap['target'] ?></strong><small><?= $gap['gap'] > 0 ? 'Capacity Gap: ' . (int)$gap['gap'] : 'Target met' ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Growth Blockers</h2><a class="btn secondary" href="/decision-support/<?= $slug ?>">Decision Support</a></div>
    <div class="table-wrap"><table><thead><tr><th>Severity</th><th>Blocker</th><th>Resolution</th></tr></thead><tbody><?php foreach ($decisionWidgets['blockers'] as $item): ?><tr><td><span class="priority <?= strtolower($item['severity']) ?>"><?= htmlspecialchars($item['severity']) ?></span></td><td><?= htmlspecialchars($item['blocker_title']) ?></td><td><?= htmlspecialchars($item['recommended_resolution']) ?></td></tr><?php endforeach; ?><?php if (!$decisionWidgets['blockers']): ?><tr><td colspan="3">No critical blockers for this theater.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Pursuits</h2><a class="btn secondary" href="/pursuits/<?= $slug ?>">Pursuit Board</a></div>
    <div class="table-wrap"><table><thead><tr><th>Decision</th><th>Opportunity</th><th>Score</th><th>Next Action</th></tr></thead><tbody><?php foreach ($pursuitWidgets['topPursuits'] as $opp): ?><tr><td><span class="priority high"><?= htmlspecialchars($opp['recommended_decision']) ?></span></td><td><a href="/pursuits/detail?id=<?= (int)$opp['id'] ?>"><?= htmlspecialchars($opp['name']) ?></a><br><small><?= htmlspecialchars($opp['classification']) ?> · <?= htmlspecialchars($opp['category']) ?></small></td><td><?= (int)$opp['pursuit_score'] ?></td><td><?= htmlspecialchars($opp['next_best_action']) ?></td></tr><?php endforeach; ?><?php if (!$pursuitWidgets['topPursuits']): ?><tr><td colspan="4">No pursuit decisions waiting for this theater.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Relationships To Strengthen</h2><a class="btn secondary" href="/relationship-graph/<?= $slug ?>">Influence Network</a></div>
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Role</th><th>Influence Value</th><th>Next Best Action</th></tr></thead><tbody><?php foreach ($topRelationships as $rel): ?><tr><td><a href="/contacts/detail?id=<?= (int)$rel['contact_id'] ?>"><?= htmlspecialchars(trim(($rel['first_name'] ?? '') . ' ' . ($rel['last_name'] ?? ''))) ?></a><br><small><?= htmlspecialchars($rel['organization_name'] ?? '') ?></small></td><td><?= htmlspecialchars($rel['influence_role'] ?? 'Unknown') ?></td><td><?= (int)$rel['relationship_value_score'] ?><br><small><?= htmlspecialchars($rel['relationship_priority']) ?></small></td><td><?= htmlspecialchars($rel['next_best_action'] ?? '') ?></td></tr><?php endforeach; ?><?php if (!$topRelationships): ?><tr><td colspan="4">No influence assets waiting for review.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Ready For SyncERP</h2><a class="btn secondary" href="/syncerp-integration/<?= $slug ?>">Handoff Queue</a></div>
    <div class="table-wrap"><table><thead><tr><th>Package</th><th>Readiness</th><th>Status</th></tr></thead><tbody><?php foreach ($syncWidgets['ready'] as $package): ?><tr><td><?= htmlspecialchars($package['package_name']) ?></td><td><?= (int)$package['readiness_score'] ?></td><td><?= htmlspecialchars($package['integration_status'] ?? '') ?></td></tr><?php endforeach; ?><?php if (!$syncWidgets['ready']): ?><tr><td colspan="3">No packages ready for SyncERP handoff.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Market Intelligence</h2><a class="btn secondary" href="/market-intelligence/<?= $slug ?>">Market Intel</a></div>
    <div class="table-wrap"><table><thead><tr><th>Market</th><th>Readiness</th><th>Priority</th></tr></thead><tbody><?php foreach ($marketWidgets['profiles'] as $profile): ?><tr><td><?= htmlspecialchars($profile['market']) ?></td><td><?= (int)$profile['market_readiness_score'] ?></td><td><?= htmlspecialchars($profile['strategic_priority']) ?></td></tr><?php endforeach; ?><?php if (!$marketWidgets['profiles']): ?><tr><td colspan="3">No market profiles available.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Strategic Accounts</h2><a class="btn secondary" href="/strategic-accounts">Accounts</a></div>
    <div class="table-wrap"><table><thead><tr><th>Account</th><th>Strategic</th><th>Owner</th><th>Next Action</th></tr></thead><tbody><?php foreach ($executiveWidgets['accounts'] ?? [] as $account): ?><tr><td><?= htmlspecialchars($account['account_name']) ?></td><td><?= (int)$account['strategic_score'] ?></td><td><?= htmlspecialchars($account['primary_owner']) ?></td><td><?= htmlspecialchars($account['next_best_action']) ?></td></tr><?php endforeach; ?><?php if (empty($executiveWidgets['accounts'])): ?><tr><td colspan="4">No strategic accounts for this theater.</td></tr><?php endif; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Strategic Recommendations</h2><a class="btn secondary" href="/executive-os">Executive OS</a></div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Recommendation</th><th>Impact</th></tr></thead><tbody><?php foreach ($executiveWidgets['recommendations'] ?? [] as $item): ?><tr><td><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span></td><td><strong><?= htmlspecialchars($item['recommendation_title']) ?></strong><br><small><?= htmlspecialchars($item['recommended_action']) ?></small></td><td><?= htmlspecialchars($item['expected_impact']) ?></td></tr><?php endforeach; ?><?php if (empty($executiveWidgets['recommendations'])): ?><tr><td colspan="3">No strategic recommendations for this theater.</td></tr><?php endif; ?></tbody></table></div>
  </div>
</section>

<?php $healthChecks = $platformData['health'] ?? []; require __DIR__ . '/../components/platform_health.php'; ?>
