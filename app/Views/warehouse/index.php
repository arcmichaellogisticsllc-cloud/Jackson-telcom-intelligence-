<section class="page-header">
  <p class="eyebrow">Intelligence Warehouse</p>
  <h1><?= htmlspecialchars($label) ?> institutional memory.</h1>
  <p>Preserve outcomes, learn what works, and turn relationship, capacity, demand, pursuit, and preconstruction history into better decisions.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $label === 'National' ? 'active' : '' ?>" href="/warehouse">National</a>
  <a class="<?= $label === 'Southeast' ? 'active' : '' ?>" href="/warehouse/southeast">Southeast</a>
  <a class="<?= $label === 'Great Lakes' ? 'active' : '' ?>" href="/warehouse/great-lakes">Great Lakes</a>
  <a class="<?= $label === 'Southwest' ? 'active' : '' ?>" href="/warehouse/southwest">Southwest</a>
  <a href="/warehouse/brief">Executive Intelligence Brief</a>
  <form method="post" action="/warehouse/rebuild"><button class="btn secondary">Rebuild Warehouse</button></form>
</nav>

<section class="metrics">
  <div><span>Intelligence Score</span><strong><?= (int)$data['metrics']['intelligence_score'] ?></strong></div>
  <div><span>Outcome Records</span><strong><?= (int)$data['metrics']['outcomes'] ?></strong></div>
  <div><span>Learning Insights</span><strong><?= (int)$data['metrics']['insights'] ?></strong></div>
  <div><span>Lessons Learned</span><strong><?= (int)$data['metrics']['lessons'] ?></strong></div>
  <div><span>High Impact Lessons</span><strong><?= (int)$data['metrics']['high_impact'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Learning Insights</h2><span class="status">What changed?</span></div>
    <div class="action-stack"><?php foreach ($data['insights'] as $row): ?><article><span class="priority <?= strtolower($row['priority']) ?>"><?= htmlspecialchars($row['priority']) ?></span><h3><?= htmlspecialchars($row['insight_title']) ?></h3><p><?= htmlspecialchars($row['recommended_action']) ?></p><small><?= htmlspecialchars($row['region_name'] ?? 'National') ?></small></article><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recent Outcomes</h2><span class="status">Institutional memory</span></div>
    <div class="table-wrap"><table><thead><tr><th>Module</th><th>Outcome</th><th>Region</th><th>Impact</th><th>Notes</th></tr></thead><tbody><?php foreach ($data['outcomes'] as $row): ?><tr><td><?= htmlspecialchars($row['source_module']) ?></td><td><?= htmlspecialchars($row['outcome_type']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= (int)$row['impact_score'] ?></td><td><?= htmlspecialchars($row['notes']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Best Performing Relationships</h2>
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Organization</th><th>Score</th><th>Output</th></tr></thead><tbody><?php foreach ($data['relationships'] as $row): ?><tr><td><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td><td><?= htmlspecialchars($row['organization_name'] ?? '') ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= (int)$row['relationship_performance_score'] ?></td><td><?= (int)$row['opportunities_created'] ?> opps · <?= (int)$row['introductions_generated'] ?> intros</td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Best Performing Subcontractors</h2>
    <div class="table-wrap"><table><thead><tr><th>Subcontractor</th><th>Category</th><th>Score</th><th>Capacity</th></tr></thead><tbody><?php foreach ($data['subcontractors'] as $row): ?><tr><td><?= htmlspecialchars($row['company_name']) ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['performance_category']) ?></td><td><?= (int)$row['subcontractor_performance_score'] ?></td><td><?= (int)$row['capacity_contribution_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Best Hunts</h2>
    <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Region</th><th>Score</th><th>Conversion</th></tr></thead><tbody><?php foreach ($data['hunts'] as $row): ?><tr><td><?= htmlspecialchars($row['hunt_name']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= (int)$row['hunt_effectiveness_score'] ?></td><td><?= (int)$row['targets_converted'] ?> / <?= (int)$row['targets_hunted'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Best Demand Assets</h2>
    <div class="table-wrap"><table><thead><tr><th>Content</th><th>Region</th><th>Score</th><th>Attribution</th></tr></thead><tbody><?php foreach ($data['demand'] as $row): ?><tr><td><?= htmlspecialchars($row['title']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= (int)$row['demand_performance_score'] ?></td><td>S <?= (int)$row['attributed_signals'] ?> · T <?= (int)$row['attributed_targets'] ?> · O <?= (int)$row['attributed_opportunities'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Pursuit Performance</h2>
    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Region</th><th>Score</th><th>Outcome</th></tr></thead><tbody><?php foreach ($data['pursuits'] as $row): ?><tr><td><?= htmlspecialchars($row['opportunity_name']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= (int)$row['pursuit_performance_score'] ?></td><td><?= (int)$row['pursued'] ? 'Pursued' : ((int)$row['avoided'] ? 'Avoided' : 'Watch') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Regional Intelligence Scores</h2>
    <div class="table-wrap"><table><thead><tr><th>Region</th><th>Overall</th><th>Relationship</th><th>Capacity</th><th>Demand</th><th>Pursuit</th></tr></thead><tbody><?php foreach ($data['regions'] as $row): ?><tr><td><?= htmlspecialchars($row['region_name']) ?></td><td><?= (int)$row['regional_intelligence_score'] ?></td><td><?= (int)$row['relationship_intelligence_score'] ?></td><td><?= (int)$row['capacity_intelligence_score'] ?></td><td><?= (int)$row['demand_intelligence_score'] ?></td><td><?= (int)$row['pursuit_intelligence_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="panel">
  <h2>Top Lessons Learned</h2>
  <div class="table-wrap"><table><thead><tr><th>Impact</th><th>Category</th><th>Region</th><th>Lesson</th></tr></thead><tbody><?php foreach ($data['lessons'] as $row): ?><tr><td><span class="priority <?= strtolower($row['impact_level']) ?>"><?= htmlspecialchars($row['impact_level']) ?></span></td><td><?= htmlspecialchars($row['category']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['lesson']) ?></small></td></tr><?php endforeach; ?></tbody></table></div>
</section>
