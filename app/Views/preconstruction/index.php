<section class="page-header">
  <p class="eyebrow">Preconstruction Intelligence</p>
  <h1><?= htmlspecialchars($label) ?> bid readiness.</h1>
  <p>Bridge acquisition to future SyncERP: can we bid it, execute it, make money, and control risk before award?</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $label === 'National' ? 'active' : '' ?>" href="/preconstruction">National</a>
  <a class="<?= $label === 'Southeast' ? 'active' : '' ?>" href="/preconstruction/southeast">Southeast</a>
  <a class="<?= $label === 'Great Lakes' ? 'active' : '' ?>" href="/preconstruction/great-lakes">Great Lakes</a>
  <a class="<?= $label === 'Southwest' ? 'active' : '' ?>" href="/preconstruction/southwest">Southwest</a>
  <form method="post" action="/preconstruction/rebuild"><button class="btn secondary">Rebuild Preconstruction</button></form>
</nav>

<section class="metrics">
  <div><span>Profiles</span><strong><?= (int)$data['metrics']['profiles'] ?></strong></div>
  <div><span>Ready for Bid Review</span><strong><?= (int)$data['metrics']['ready'] ?></strong></div>
  <div><span>Bid Decisions</span><strong><?= (int)$data['metrics']['bid'] ?></strong></div>
  <div><span>Capacity Blocking Bids</span><strong><?= (int)$data['metrics']['blocked'] ?></strong></div>
  <div><span>Critical Risks</span><strong><?= (int)$data['metrics']['critical_risks'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Opportunities Ready for Bid Review</h2><span class="status">Pre-award</span></div>
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Theater</th><th>Status</th><th>Decision</th><th>Score</th></tr></thead><tbody>
      <?php foreach ($data['ready'] as $row): ?><tr><td><a href="/preconstruction/detail?id=<?= (int)$row['id'] ?>"><strong><?= htmlspecialchars($row['project_name']) ?></strong></a><br><small><?= htmlspecialchars($row['customer_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= htmlspecialchars($row['preconstruction_status']) ?></td><td><?= htmlspecialchars($row['recommended_decision'] ?? 'Hold') ?></td><td><?= (int)($row['bid_score'] ?? 0) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Bid / No-Bid Decisions</h2><span class="status">Decision</span></div>
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Decision</th><th>Bid</th><th>No-Bid</th></tr></thead><tbody>
      <?php foreach ($data['bidDecisions'] as $row): ?><tr><td><a href="/preconstruction/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['project_name']) ?></a></td><td><span class="priority <?= ($row['recommended_decision'] ?? '') === 'No Bid' ? 'high' : 'medium' ?>"><?= htmlspecialchars($row['recommended_decision'] ?? 'Hold') ?></span></td><td><?= (int)($row['bid_score'] ?? 0) ?></td><td><?= (int)($row['no_bid_score'] ?? 0) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Capacity Blocking Bids</h2><a class="btn secondary" href="/capacity-radar">Capacity Radar</a></div>
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Discipline</th><th>Need</th><th>Gap</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($data['capacityBlocked'] as $row): ?><tr><td><?= htmlspecialchars($row['project_name']) ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['discipline']) ?></td><td><?= (int)$row['required_crews'] ?></td><td><?= (int)$row['projected_gap'] ?></td><td><?= htmlspecialchars($row['recommended_capacity_action']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Subcontractor Fit Plans</h2><a class="btn secondary" href="/subcontractor-acquisition">Subcontractors</a></div>
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Subcontractor</th><th>Role</th><th>Fit</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($data['fitPlans'] as $row): ?><tr><td><?= htmlspecialchars($row['project_name']) ?></td><td><?= htmlspecialchars($row['company_name']) ?></td><td><?= htmlspecialchars($row['recommended_role']) ?></td><td><?= (int)$row['fit_score'] ?></td><td><?= htmlspecialchars($row['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Margin Forecasts</h2><span class="status">Forecast only</span></div>
    <div class="table-wrap"><table><thead><tr><th>Project</th><th>Revenue</th><th>Profit</th><th>Margin</th><th>Confidence</th></tr></thead><tbody>
      <?php foreach ($data['forecasts'] as $row): ?><tr><td><?= htmlspecialchars($row['project_name']) ?></td><td>$<?= number_format((float)$row['estimated_revenue']) ?></td><td>$<?= number_format((float)$row['estimated_profit']) ?></td><td><?= number_format((float)$row['estimated_margin_percent'], 1) ?>%</td><td><?= (int)$row['confidence_score'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Critical Risks</h2><span class="status">Before award</span></div>
    <div class="action-stack"><?php foreach ($data['risks'] as $row): ?><article><span class="priority <?= strtolower($row['severity']) ?>"><?= htmlspecialchars($row['severity']) ?></span><h3><?= htmlspecialchars($row['risk_type']) ?> · <?= htmlspecialchars($row['project_name']) ?></h3><p><?= htmlspecialchars($row['mitigation']) ?></p></article><?php endforeach; ?></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Scenario Plans</h2><span class="status">Conservative / Expected / Aggressive</span></div>
  <div class="table-wrap"><table><thead><tr><th>Project</th><th>Scenario</th><th>Revenue</th><th>Margin</th><th>Crews</th><th>Gap</th><th>Recommendation</th></tr></thead><tbody>
    <?php foreach ($data['scenarios'] as $row): ?><tr><td><?= htmlspecialchars($row['project_name']) ?><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?></small></td><td><?= htmlspecialchars($row['scenario_type']) ?></td><td>$<?= number_format((float)$row['revenue_estimate']) ?></td><td><?= number_format((float)$row['margin_estimate'], 1) ?>%</td><td><?= (int)$row['crew_requirement'] ?></td><td><?= (int)$row['capacity_gap'] ?></td><td><?= htmlspecialchars($row['recommendation']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
