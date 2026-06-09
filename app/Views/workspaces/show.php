<section class="page-header command-page-header">
  <p class="eyebrow">Workspace</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<?php
$why = match ($title) {
    'WORK' => 'This workspace shows what work exists, which opportunities deserve pursuit, and what must be bid or avoided.',
    'CAPACITY' => 'This workspace shows who can perform work, who needs work, and which gaps block growth.',
    'RELATIONSHIPS' => 'This workspace shows who influences work and which conversations need follow-up.',
    'MARKET' => 'This workspace shows what is happening in the market before it becomes obvious work.',
    'GROWTH' => 'This workspace shows content, demand, and distribution actions that can create signals and relationships.',
    'ONBOARDING' => 'This workspace turns discovered targets into operationally ready capacity, workforce, accounts, and markets.',
    'OPERATIONS' => 'This workspace shows what is ready for SyncERP handoff without starting execution inside this platform.',
    default => 'This workspace keeps system controls, health, data quality, and operating discipline visible.',
};
$recommended = 'Start with Top Actions, then open only the records with a next step or risk.';
$next = 'Use one record action, log the outcome, and return to this workspace to continue.';
$risk = 'If this workspace becomes a browsing page instead of an action page, decisions slow down and intelligence becomes stale.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <?php foreach ($metrics as $label => $value): ?><div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?>
</section>

<section class="workspace-link-grid">
  <?php foreach ($links as $label => $href): ?><a class="workspace-link" href="<?= htmlspecialchars($href) ?>"><strong><?= htmlspecialchars($label) ?></strong><span>Open the action view for this operating area</span></a><?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Actions</h2><a class="btn secondary" href="/decision-support">Decision Support</a></div>
    <div class="action-stack">
      <?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span><h3><?= htmlspecialchars($action['action_title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_step']) ?></p><small><?= htmlspecialchars($action['owner']) ?> · <?= htmlspecialchars($action['region_name'] ?? 'National') ?></small></article><?php endforeach; ?>
      <?php if (!$actions): ?>
        <?php
        $emptyTitle = 'No actions waiting';
        $emptyBody = 'This workspace has no open daily actions. Create a follow-up or run the acquisition cycle when real data is ready.';
        $emptyActionHref = '/decision-support';
        $emptyActionLabel = 'Open Decision Support';
        require __DIR__ . '/../components/empty_state.php';
        ?>
      <?php endif; ?>
    </div>
  </div>
  <?php $recentConversations = $conversations; require __DIR__ . '/../components/recent_conversations.php'; ?>
</section>

<?php $listEyebrow = 'Workspace Records'; $listTitle = 'Actionable records'; require __DIR__ . '/../components/list_toolbar.php'; ?>
<section class="panel">
  <div class="table-wrap"><table><thead><tr><th>Record</th><th>Type / Status</th><th>Owner</th><th>Next Action</th></tr></thead><tbody>
    <?php foreach ($records as $record): ?><tr><td><strong><?= htmlspecialchars($record['title'] ?? '') ?></strong></td><td><?= htmlspecialchars($record['type'] ?? '') ?></td><td><?= htmlspecialchars($record['owner'] ?? '') ?></td><td><?= htmlspecialchars($record['next_action'] ?? '') ?></td></tr><?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="4"><?php $emptyTitle = 'No records surfaced'; $emptyBody = 'No reviewed records are ready for this workspace yet. Add real data, run a connector, or resolve data quality issues.'; $emptyActionHref = '/data-quality'; $emptyActionLabel = 'Open Data Quality'; require __DIR__ . '/../components/empty_state.php'; ?></td></tr><?php endif; ?>
  </tbody></table></div>
</section>
