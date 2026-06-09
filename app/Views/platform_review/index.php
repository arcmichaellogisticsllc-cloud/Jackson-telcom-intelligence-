<section class="page-header">
  <p class="eyebrow">Platform Review</p>
  <h1>Reduce noise. Keep the operating system sharp.</h1>
  <p>Health checks and concept cleanup for a platform that should show five priorities, not 500 items.</p>
</section>

<nav class="dash-tabs">
  <a href="/operating-view">Executive Operating View</a>
  <a class="active" href="/platform-review">Platform Health</a>
  <a href="/operator-modes">Perspective Filters</a>
</nav>

<section class="grid two">
  <div class="panel">
    <h2>Module Health Checks</h2>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Module</th><th>Check</th><th>Issues</th><th>Action</th></tr></thead><tbody><?php foreach ($health as $check): ?><tr><td><span class="priority <?= strtolower($check['status']) ?>"><?= htmlspecialchars($check['status']) ?></span></td><td><?= htmlspecialchars($check['module_name']) ?></td><td><?= htmlspecialchars($check['check_type']) ?></td><td><?= (int)$check['issue_count'] ?></td><td><?= htmlspecialchars($check['recommended_action']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Duplicated Concepts Audit</h2>
    <div class="table-wrap"><table><thead><tr><th>Concept</th><th>Operational Role</th><th>Open Count</th></tr></thead><tbody><?php foreach ($duplicates as $item): ?><tr><td><strong><?= htmlspecialchars($item['concept']) ?></strong></td><td><?= htmlspecialchars($item['role']) ?></td><td><?= (int)$item['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
