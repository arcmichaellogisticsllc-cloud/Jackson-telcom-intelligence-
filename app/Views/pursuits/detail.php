<?php
$recordEyebrow = 'Pursuit Workspace';
$recordName = $opportunity['name'];
$recordType = $opportunity['classification'] ?? 'Opportunity';
$recordRegion = $opportunity['region_name'] ?? 'National';
$recordOwner = $opportunity['owner'] ?? 'Unassigned';
$recordStatus = $opportunity['recommended_decision'] ?? 'Monitor';
$recordScore = (int)($opportunity['pursuit_score'] ?? 0);
$recordNextAction = $opportunity['next_best_action'] ?? $opportunity['next_action'] ?? '';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Approve Pursuit','Create Preconstruction Profile'];
$recordEntityType = 'opportunity';
$recordEntityId = (int)$opportunity['id'];
$recordRegionId = (int)($opportunity['region_id'] ?? 0);
$timelineItems = [
  ['type' => 'Pursuit Decision', 'title' => $recordStatus, 'why' => $opportunity['decision_reason'] ?? $opportunity['reason'] ?? 'Pursuit decision affects whether Jackson spends relationship, capacity, and preconstruction attention.', 'next' => $recordNextAction, 'owner' => $recordOwner, 'date' => $opportunity['updated_at'] ?? $opportunity['created_at'] ?? ''],
  ['type' => 'Capacity Fit', 'title' => 'Capacity fit score ' . (int)($opportunity['capacity_fit_score'] ?? 0), 'why' => $opportunity['capacity_gap'] ?: 'Capacity appears sufficient for the current pursuit stage.', 'next' => 'Review Capacity Radar before proposal commitment.', 'owner' => $recordOwner, 'date' => $opportunity['updated_at'] ?? ''],
];
foreach ($recentConversations ?? [] as $conversation) {
  $timelineItems[] = ['type' => $conversation['communication_type'], 'title' => $conversation['summary'], 'why' => $conversation['outcome'] ?: 'Conversation may affect pursuit access, risk, or next action.', 'next' => $conversation['next_step'] ?: 'Create follow-up if needed.', 'owner' => $conversation['owner'], 'date' => $conversation['communication_date']];
}
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Contacts / People','Conversations','Opportunities / Pursuits','Capacity','Tasks / Actions','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$why = $opportunity['decision_reason'] ?? $opportunity['reason'] ?? 'This pursuit determines whether Jackson should spend capacity, relationship, and preconstruction attention.';
$recommended = $recordNextAction ?: 'Review pursuit score, relationship fit, and capacity fit before advancing.';
$next = 'Log pursuit activity or create a preconstruction profile when the decision is ready.';
$risk = 'If the pursuit is not acted on, Jackson may miss aligned fiber backbone work or chase weak-fit work.';
require __DIR__ . '/../components/action_first.php';
?>

<nav class="dash-tabs">
  <a href="/pursuits">Pursuit Board</a>
  <a href="/opportunities">Opportunities</a>
  <a href="/capacity-radar">Capacity Radar</a>
  <a href="/relationship-graph">Relationship Graph</a>
  <form method="post" action="/preconstruction/create">
    <input type="hidden" name="opportunity_id" value="<?= (int)$opportunity['id'] ?>">
    <button class="btn secondary">Create Preconstruction Profile</button>
  </form>
</nav>

<section class="metrics">
  <div><span>Strategic Alignment</span><strong><?= (int)($opportunity['strategic_alignment_score'] ?? 0) ?></strong></div>
  <div><span>Pursuit Score</span><strong><?= (int)($opportunity['pursuit_score'] ?? 0) ?></strong></div>
  <div><span>Relationship Fit</span><strong><?= (int)($opportunity['relationship_fit_score'] ?? 0) ?></strong></div>
  <div><span>Capacity Fit</span><strong><?= (int)($opportunity['capacity_fit_score'] ?? 0) ?></strong></div>
  <div><span>Risk</span><strong><?= (int)($opportunity['risk_score'] ?? 0) ?></strong></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="grid two">
  <article class="panel">
    <div class="panel-title"><h2>Why This Decision</h2><span class="priority high"><?= htmlspecialchars($opportunity['recommended_decision'] ?? 'Monitor') ?></span></div>
    <p><?= htmlspecialchars($opportunity['decision_reason'] ?? $opportunity['reason'] ?? '') ?></p>
    <h3>Next Best Action</h3>
    <p><?= htmlspecialchars($opportunity['next_best_action'] ?? '') ?></p>
    <h3>Watchlist</h3>
    <p><?= htmlspecialchars($opportunity['watch_status'] ?? 'Watch') ?> · Review <?= htmlspecialchars($opportunity['next_review_date'] ?? '') ?></p>
  </article>
  <article class="panel">
    <div class="panel-title"><h2>Opportunity Profile</h2><span class="status"><?= htmlspecialchars($opportunity['stage'] ?? 'Intelligence') ?></span></div>
    <div class="mini-metrics">
      <div><span>Type</span><strong><?= htmlspecialchars($opportunity['opportunity_type'] ?? '') ?></strong></div>
      <div><span>Customer</span><strong><?= htmlspecialchars($opportunity['customer_type'] ?? '') ?></strong></div>
      <div><span>Funding</span><strong><?= htmlspecialchars($opportunity['funding_source'] ?? '') ?></strong></div>
      <div><span>Value</span><strong>$<?= number_format((float)($opportunity['estimated_value'] ?? 0)) ?></strong></div>
    </div>
    <p><?= htmlspecialchars($opportunity['notes'] ?? '') ?></p>
  </article>
</section>

<section class="grid two">
  <article class="panel">
    <div class="panel-title"><h2>Relationship Fit</h2><span class="score stable"><?= (int)($opportunity['relationship_fit_score'] ?? 0) ?></span></div>
    <p><?= htmlspecialchars($opportunity['relationship_gap'] ?: 'Relationship access appears sufficient for current pursuit stage.') ?></p>
    <p><strong>Decision makers:</strong> <?= htmlspecialchars($opportunity['decision_makers'] ?? '') ?></p>
  </article>
  <article class="panel">
    <div class="panel-title"><h2>Capacity Fit</h2><span class="score stable"><?= (int)($opportunity['capacity_fit_score'] ?? 0) ?></span></div>
    <p><?= htmlspecialchars($opportunity['capacity_gap'] ?: 'Capacity fit appears sufficient for current pursuit stage.') ?></p>
    <p><strong>Capacity required:</strong> <?= (int)($opportunity['capacity_required'] ?? 0) ?> crews</p>
  </article>
</section>
