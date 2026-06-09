<?php $healthChecks = $healthChecks ?? []; ?>
<section class="panel platform-health">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Platform Health</p>
      <h2>Can operators trust the system today?</h2>
    </div>
    <a class="btn secondary" href="/platform-review">Health Review</a>
  </div>
  <div class="health-grid">
    <?php foreach (array_slice($healthChecks, 0, 6) as $check): ?>
      <div>
        <span class="status <?= strtolower($check['status'] ?? 'pass') ?>"><?= htmlspecialchars($check['status'] ?? 'Pass') ?></span>
        <strong><?= htmlspecialchars($check['check_type'] ?? '') ?></strong>
        <small><?= htmlspecialchars($check['module_name'] ?? '') ?> · <?= (int)($check['issue_count'] ?? 0) ?> issues</small>
      </div>
    <?php endforeach; ?>
    <?php if (!$healthChecks): ?>
      <?php
      $emptyTitle = 'No health checks generated';
      $emptyBody = 'Run the acquisition cycle and data integrity check before relying on pilot data.';
      $emptyActionHref = '/production-readiness';
      $emptyActionLabel = 'Open Production Readiness';
      require __DIR__ . '/empty_state.php';
      ?>
    <?php endif; ?>
  </div>
</section>
