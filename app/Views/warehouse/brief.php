<section class="page-header">
  <p class="eyebrow">Executive Intelligence Brief</p>
  <h1>What the platform learned.</h1>
  <p>Monthly operating memory across relationships, subcontractors, hunts, demand, pursuits, preconstruction, and regions.</p>
</section>

<nav class="dash-tabs">
  <a href="/warehouse">Intelligence Warehouse</a>
  <a class="active" href="/warehouse/brief">Executive Intelligence Brief</a>
  <a href="/decision-support">Decision Support</a>
</nav>

<section class="metrics">
  <div><span>National Intelligence Score</span><strong><?= (int)$data['metrics']['intelligence_score'] ?></strong></div>
  <div><span>What Worked</span><strong><?= count($data['insights']) ?></strong></div>
  <div><span>Lessons</span><strong><?= count($data['lessons']) ?></strong></div>
  <div><span>Outcomes</span><strong><?= (int)$data['metrics']['outcomes'] ?></strong></div>
  <div><span>High Impact</span><strong><?= (int)$data['metrics']['high_impact'] ?></strong></div>
</section>

<section class="grid two">
  <article class="panel">
    <h2>What Worked This Month</h2>
    <div class="action-stack"><?php foreach (array_slice($data['insights'], 0, 6) as $row): ?><div><span class="priority <?= strtolower($row['priority']) ?>"><?= htmlspecialchars($row['priority']) ?></span><h3><?= htmlspecialchars($row['insight_title']) ?></h3><p><?= htmlspecialchars($row['insight_body']) ?></p></div><?php endforeach; ?></div>
  </article>
  <article class="panel">
    <h2>What Failed / Needs Attention</h2>
    <div class="table-wrap"><table><thead><tr><th>Module</th><th>Outcome</th><th>Region</th><th>Notes</th></tr></thead><tbody><?php foreach (array_filter($data['outcomes'], fn($o) => in_array($o['outcome_type'], ['Failure','Lost','Opportunity Lost','Bid Lost','Outreach Failed'], true)) as $row): ?><tr><td><?= htmlspecialchars($row['source_module']) ?></td><td><?= htmlspecialchars($row['outcome_type']) ?></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= htmlspecialchars($row['notes']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </article>
  <article class="panel">
    <h2>Best Relationships</h2>
    <div class="table-wrap"><table><thead><tr><th>Relationship</th><th>Score</th><th>Created</th></tr></thead><tbody><?php foreach (array_slice($data['relationships'], 0, 6) as $row): ?><tr><td><?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?><br><small><?= htmlspecialchars($row['organization_name'] ?? '') ?></small></td><td><?= (int)$row['relationship_performance_score'] ?></td><td><?= (int)$row['opportunities_created'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </article>
  <article class="panel">
    <h2>Best Subcontractors</h2>
    <div class="table-wrap"><table><thead><tr><th>Subcontractor</th><th>Category</th><th>Score</th></tr></thead><tbody><?php foreach (array_slice($data['subcontractors'], 0, 6) as $row): ?><tr><td><?= htmlspecialchars($row['company_name']) ?></td><td><?= htmlspecialchars($row['performance_category']) ?></td><td><?= (int)$row['subcontractor_performance_score'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </article>
  <article class="panel">
    <h2>Best Hunts</h2>
    <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Score</th><th>Converted</th></tr></thead><tbody><?php foreach (array_slice($data['hunts'], 0, 6) as $row): ?><tr><td><?= htmlspecialchars($row['hunt_name']) ?></td><td><?= (int)$row['hunt_effectiveness_score'] ?></td><td><?= (int)$row['targets_converted'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </article>
  <article class="panel">
    <h2>Regional Performance</h2>
    <div class="table-wrap"><table><thead><tr><th>Region</th><th>Score</th><th>Weakest Area</th><th>Blockers</th></tr></thead><tbody><?php foreach ($data['regions'] as $row): ?><tr><td><?= htmlspecialchars($row['region_name']) ?></td><td><?= (int)$row['regional_intelligence_score'] ?></td><td><?= htmlspecialchars($row['weakest_areas']) ?></td><td><?= htmlspecialchars($row['recurring_blockers']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </article>
</section>
