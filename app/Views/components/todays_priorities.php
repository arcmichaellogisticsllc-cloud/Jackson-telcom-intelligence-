<?php
$priorityActions = array_slice($priorityActions ?? [], 0, 5);
$categoryLabels = ['Capacity' => 'Capacity', 'Relationship' => 'Relationship', 'Opportunity' => 'Pursuit', 'Demand' => 'Demand', 'Content' => 'Demand', 'Hunt' => 'Capacity', 'Subcontractor' => 'Capacity', 'Risk' => 'Pursuit'];
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
        'acquisition_target', 'Acquisition Target' => $id ? '/targets/detail?id=' . $id : '/targets',
        default => '/decision-support',
    };
};
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
        <h3><a href="<?= htmlspecialchars($actionHref($action)) ?>"><?= htmlspecialchars($action['action_title'] ?? $action['title'] ?? 'Action required') ?></a></h3>
        <p><strong>Reason:</strong> <?= htmlspecialchars($action['reason'] ?? $action['why_it_matters'] ?? 'This item is high enough priority to require operator attention.') ?></p>
        <p><strong>Next step:</strong> <?= htmlspecialchars($action['recommended_next_step'] ?? $action['recommended_next_action'] ?? '') ?></p>
        <small><?= htmlspecialchars($action['owner'] ?? $action['assigned_owner'] ?? 'Admin') ?> · <?= htmlspecialchars($action['region_name'] ?? $action['region'] ?? 'National') ?> · Urgency <?= (int)($action['urgency_score'] ?? $action['priority_score'] ?? $action['decision_score'] ?? 0) ?></small>
      </article>
    <?php endforeach; ?>
    <?php if (!$priorityActions): ?>
      <?php
      $emptyTitle = 'No top actions yet';
      $emptyBody = 'No executive-level actions are open for this operator view. Run the acquisition cycle, review data quality, or create the first real action.';
      $emptyActionHref = '/decision-support';
      $emptyActionLabel = 'Open Decision Support';
      require __DIR__ . '/empty_state.php';
      ?>
    <?php endif; ?>
  </div>
</section>
