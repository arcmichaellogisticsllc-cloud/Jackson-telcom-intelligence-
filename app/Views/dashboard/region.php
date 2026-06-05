<section class="page-header">
  <p class="eyebrow"><?= htmlspecialchars($region['owner']) ?> Command Center</p>
  <h1><?= htmlspecialchars($region['name']) ?> acquisition command.</h1>
  <p>States covered: <?= htmlspecialchars($region['states']) ?>. Daily focus: capacity gaps, relationship risk, compliance risk, and pursuit discipline.</p>
</section>

<nav class="dash-tabs">
  <a href="/">Executive Overview</a>
  <a class="<?= $region['name'] === 'Southeast' ? 'active' : '' ?>" href="/command/southeast">Southeast Command Center</a>
  <a class="<?= $region['name'] === 'Great Lakes' ? 'active' : '' ?>" href="/command/great-lakes">Great Lakes Command Center</a>
</nav>

<section class="metrics">
  <div><span>Capacity Score</span><strong><?= $score['score'] ?></strong><small><?= htmlspecialchars($score['category']) ?></small></div>
  <div><span>Approved Subcontractors</span><strong><?= $score['approved_count'] ?></strong></div>
  <div><span>Available Crews</span><strong><?= $score['crew_count'] ?></strong></div>
  <div><span>Compliance Ready</span><strong><?= $score['compliant_count'] ?></strong></div>
  <div><span>Services Covered</span><strong><?= $score['service_coverage'] ?>/5</strong></div>
</section>

<section class="metrics">
  <div><span>New Signals</span><strong><?= $signalWidgets['new'] ?></strong></div>
  <div><span>Critical Signals</span><strong><?= $signalWidgets['critical'] ?></strong></div>
  <div><span>Signals Needing Review</span><strong><?= $signalWidgets['needing_review'] ?></strong></div>
  <div><span>Signals Assigned To Me</span><strong><?= $signalWidgets['assigned_to_me'] ?></strong></div>
  <div><span>Converted This Month</span><strong><?= $signalWidgets['converted_month'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Available Crews by Service Type</h2><span class="score <?= strtolower($score['category']) ?>"><?= htmlspecialchars($score['category']) ?></span></div>
    <div class="gap-list">
      <?php foreach ($gaps as $service => $gap): ?>
        <div class="<?= $gap['gap'] > 0 ? 'gap' : '' ?>"><span><?= htmlspecialchars($service) ?></span><strong><?= $gap['current'] ?> / <?= $gap['target'] ?></strong><small><?= $gap['gap'] > 0 ? 'Capacity Gap: ' . $gap['gap'] : 'Target met' ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recommended Daily Actions</h2><a class="btn secondary" href="/recommendations">All Actions</a></div>
    <div class="action-stack"><?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?></span><h3><?= htmlspecialchars($action['title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_action']) ?></p></article><?php endforeach; ?><?php if (!$actions): ?><p>No open actions.</p><?php endif; ?></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Open Opportunities</h2>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Stage</th><th>Value</th><th>Pursuit Score</th><th>Capacity</th></tr></thead><tbody><?php foreach ($opportunities as $opp): ?><tr><td><a href="/record?type=opportunity&id=<?= $opp['id'] ?>"><strong><?= htmlspecialchars($opp['name']) ?></strong></a><br><small><?= htmlspecialchars($opp['organization_name'] ?? '') ?></small></td><td><?= htmlspecialchars($opp['stage']) ?></td><td>$<?= number_format((float)$opp['estimated_value']) ?></td><td><?= $opp['pursuit']['score'] ?> · <?= htmlspecialchars($opp['pursuit']['label']) ?></td><td><?= (int)$opp['available_crews'] ?> / <?= (int)$opp['capacity_required'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Top Relationships Needing Follow-Up</h2>
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Influence</th><th>Strength</th><th>Last Contact</th></tr></thead><tbody><?php foreach ($relationships as $rel): ?><tr><td><a href="/record?type=contact&id=<?= $rel['id'] ?>"><?= htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']) ?></a><br><small><?= htmlspecialchars($rel['organization_name'] ?? '') ?></small></td><td><?= htmlspecialchars($rel['influence_level']) ?></td><td><?= htmlspecialchars($rel['relationship_strength']) ?></td><td><?= htmlspecialchars($rel['last_contact_date'] ?: 'Missing') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="panel">
  <h2>Compliance Issues</h2>
  <div class="table-wrap"><table><thead><tr><th>Subcontractor</th><th>Insurance</th><th>W9</th><th>Approval Stage</th><th>Availability</th></tr></thead><tbody><?php foreach ($compliance as $item): ?><tr><td><a href="/record?type=subcontractor&id=<?= $item['id'] ?>"><?= htmlspecialchars($item['organization_name']) ?></a></td><td><?= htmlspecialchars($item['insurance_status']) ?></td><td><?= htmlspecialchars($item['w9_status']) ?></td><td><?= htmlspecialchars($item['approval_stage']) ?></td><td><?= htmlspecialchars($item['availability']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
