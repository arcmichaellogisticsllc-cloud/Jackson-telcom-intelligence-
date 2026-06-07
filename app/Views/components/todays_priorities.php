<?php
$priorityActions = array_slice($priorityActions ?? [], 0, 5);
$categoryLabels = ['Capacity' => 'Capacity', 'Relationship' => 'Relationship', 'Opportunity' => 'Pursuit', 'Demand' => 'Demand', 'Content' => 'Demand', 'Hunt' => 'Capacity', 'Subcontractor' => 'Capacity', 'Risk' => 'Pursuit'];
?>
<section class="panel command-priorities">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Today's Priorities</p>
      <h2>Top 5 Actions</h2>
    </div>
    <a class="btn secondary" href="/decision-support">Decision Support</a>
  </div>
  <div class="priority-list">
    <?php foreach ($priorityActions as $action): ?>
      <?php $category = $categoryLabels[$action['action_category'] ?? ''] ?? ($action['action_category'] ?? 'Work'); ?>
      <article>
        <span class="priority <?= strtolower($action['priority'] ?? 'medium') ?>"><?= htmlspecialchars($category) ?> · <?= htmlspecialchars($action['priority'] ?? 'Medium') ?></span>
        <h3><?= htmlspecialchars($action['action_title'] ?? $action['title'] ?? 'Action required') ?></h3>
        <p><?= htmlspecialchars($action['recommended_next_step'] ?? $action['recommended_next_action'] ?? '') ?></p>
        <small><?= htmlspecialchars($action['owner'] ?? $action['assigned_owner'] ?? 'Admin') ?> · Score <?= (int)($action['decision_score'] ?? $action['priority_score'] ?? 0) ?></small>
      </article>
    <?php endforeach; ?>
    <?php if (!$priorityActions): ?>
      <p>No critical priorities are open. Run the acquisition cycle and review escalations.</p>
    <?php endif; ?>
  </div>
</section>
