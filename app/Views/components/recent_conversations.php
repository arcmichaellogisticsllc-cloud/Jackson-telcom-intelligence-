<?php $recentConversations = $recentConversations ?? []; ?>
<section class="panel recent-conversations" id="conversations">
  <div class="panel-title">
    <div><p class="eyebrow">Recent Conversations</p><h2>Calls, drafts, meetings, notes, and follow-ups</h2></div>
    <a class="btn secondary" href="/communications">Open Communications</a>
  </div>
  <div class="timeline-list">
    <?php foreach ($recentConversations as $item): ?><article>
      <span class="status"><?= htmlspecialchars($item['communication_type'] ?? $item['activity_type'] ?? 'Note') ?></span>
      <h3><?= htmlspecialchars($item['summary'] ?? $item['title'] ?? 'Conversation') ?></h3>
      <p><strong>Outcome:</strong> <?= htmlspecialchars($item['outcome'] ?? $item['notes'] ?? 'No outcome recorded yet.') ?></p>
      <p><strong>Next step:</strong> <?= htmlspecialchars($item['next_step'] ?? $item['next_action'] ?? 'Create follow-up if needed.') ?></p>
      <small><?= htmlspecialchars(substr($item['communication_date'] ?? $item['activity_date'] ?? '', 0, 10)) ?> · <?= htmlspecialchars($item['owner'] ?? 'Unassigned') ?></small>
    </article><?php endforeach; ?>
    <?php if (!$recentConversations): ?><article class="empty-state"><h3>No conversations yet</h3><p>Log a call, meeting, note, draft, or follow-up to make this record actionable.</p></article><?php endif; ?>
  </div>
</section>
