<?php $timelineItems = $timelineItems ?? []; ?>
<section class="panel intelligence-timeline" id="timeline">
  <div class="panel-title">
    <div><p class="eyebrow">Unified Timeline</p><h2>What happened and why it matters</h2></div>
    <span class="status"><?= count($timelineItems) ?> items</span>
  </div>
  <div class="timeline-list">
    <?php foreach ($timelineItems as $item): ?><article>
      <span class="status"><?= htmlspecialchars($item['type'] ?? 'Event') ?></span>
      <h3><?= htmlspecialchars($item['title'] ?? 'Timeline event') ?></h3>
      <p><strong>Why it matters:</strong> <?= htmlspecialchars($item['why'] ?? $item['notes'] ?? 'This event may affect work, capacity, relationship, pursuit, or handoff decisions.') ?></p>
      <p><strong>Next step:</strong> <?= htmlspecialchars($item['next'] ?? 'Review and assign the next action.') ?></p>
      <small><?= htmlspecialchars(substr($item['date'] ?? '', 0, 10)) ?> · <?= htmlspecialchars($item['owner'] ?? 'Unassigned') ?></small>
    </article><?php endforeach; ?>
    <?php if (!$timelineItems): ?><article class="empty-state"><h3>No timeline items</h3><p>Signals, recommendations, conversations, risks, decisions, and handoff events will appear here as the record develops.</p></article><?php endif; ?>
  </div>
</section>
