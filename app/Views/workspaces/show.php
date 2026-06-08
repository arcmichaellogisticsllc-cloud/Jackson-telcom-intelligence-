<section class="page-header command-page-header">
  <p class="eyebrow">Workspace</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<section class="metrics">
  <?php foreach ($metrics as $label => $value): ?><div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?>
</section>

<section class="workspace-link-grid">
  <?php foreach ($links as $label => $href): ?><a class="workspace-link" href="<?= htmlspecialchars($href) ?>"><strong><?= htmlspecialchars($label) ?></strong><span>Open workspace view</span></a><?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Actions</h2><a class="btn secondary" href="/decision-support">Decision Support</a></div>
    <div class="action-stack">
      <?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?> · <?= (int)$action['decision_score'] ?></span><h3><?= htmlspecialchars($action['action_title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_step']) ?></p><small><?= htmlspecialchars($action['owner']) ?> · <?= htmlspecialchars($action['region_name'] ?? 'National') ?></small></article><?php endforeach; ?>
      <?php if (!$actions): ?><article class="empty-state"><h3>No actions waiting</h3><p>This workspace has no open daily actions.</p></article><?php endif; ?>
    </div>
  </div>
  <?php $recentConversations = $conversations; require __DIR__ . '/../components/recent_conversations.php'; ?>
</section>

<?php $listEyebrow = 'Workspace Records'; $listTitle = 'Actionable records'; require __DIR__ . '/../components/list_toolbar.php'; ?>
<section class="panel">
  <div class="table-wrap"><table><thead><tr><th>Record</th><th>Type / Status</th><th>Owner</th><th>Next Action</th></tr></thead><tbody>
    <?php foreach ($records as $record): ?><tr><td><strong><?= htmlspecialchars($record['title'] ?? '') ?></strong></td><td><?= htmlspecialchars($record['type'] ?? '') ?></td><td><?= htmlspecialchars($record['owner'] ?? '') ?></td><td><?= htmlspecialchars($record['next_action'] ?? '') ?></td></tr><?php endforeach; ?>
    <?php if (!$records): ?><tr><td colspan="4">No records surfaced for this workspace yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
