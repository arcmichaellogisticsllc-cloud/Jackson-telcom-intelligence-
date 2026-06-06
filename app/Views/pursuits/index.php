<section class="page-header">
  <p class="eyebrow">Fiber Backbone Opportunity & Pursuit Engine</p>
  <h1><?= htmlspecialchars($label) ?> Pursuit Board</h1>
  <p>Decide what work Jackson should pursue, monitor, or avoid. Fiber backbone construction, expansion, maintenance, and restoration are weighted highest.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $label === 'National' ? 'active' : '' ?>" href="/pursuits">National</a>
  <a class="<?= $label === 'Southeast' ? 'active' : '' ?>" href="/pursuits/southeast">Southeast</a>
  <a class="<?= $label === 'Great Lakes' ? 'active' : '' ?>" href="/pursuits/great-lakes">Great Lakes</a>
  <a class="<?= $label === 'Southwest' ? 'active' : '' ?>" href="/pursuits/southwest">Southwest</a>
  <form method="post" action="/pursuits/rebuild"><button class="btn secondary">Rebuild Pursuit Scores</button></form>
</nav>

<section class="metrics">
  <div><span>Top Pursuits</span><strong><?= (int)$data['metrics']['top_pursuits'] ?></strong></div>
  <div><span>Fiber Backbone Opportunities</span><strong><?= (int)$data['metrics']['fiber_backbone'] ?></strong></div>
  <div><span>Opportunities To Avoid</span><strong><?= (int)$data['metrics']['avoid'] ?></strong></div>
  <div><span>Capacity Blocking Pursuits</span><strong><?= (int)$data['metrics']['capacity_blocked'] ?></strong></div>
  <div><span>Relationship Gaps</span><strong><?= (int)$data['metrics']['relationship_blocked'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Pursuits</h2><span class="status">Action</span></div>
    <div class="table-wrap"><table><thead><tr><th>Decision</th><th>Opportunity</th><th>Scores</th><th>Next Best Action</th></tr></thead><tbody>
      <?php foreach ($data['topPursuits'] as $row): ?><tr>
        <td><span class="priority high"><?= htmlspecialchars($row['recommended_decision']) ?></span></td>
        <td><a href="/pursuits/detail?id=<?= (int)$row['id'] ?>"><strong><?= htmlspecialchars($row['name']) ?></strong></a><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?> · <?= htmlspecialchars($row['classification']) ?> · <?= htmlspecialchars($row['category']) ?></small></td>
        <td>P <?= (int)$row['pursuit_score'] ?> · R <?= (int)$row['relationship_fit_score'] ?> · C <?= (int)$row['capacity_fit_score'] ?></td>
        <td><?= htmlspecialchars($row['next_best_action']) ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Top Fiber Backbone Opportunities</h2><span class="status">Core</span></div>
    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Theater</th><th>Alignment</th><th>Decision</th></tr></thead><tbody>
      <?php foreach ($data['fiberBackbone'] as $row): ?><tr><td><a href="/pursuits/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></td><td><?= htmlspecialchars($row['region_name'] ?? '') ?></td><td><?= (int)$row['strategic_alignment_score'] ?></td><td><?= htmlspecialchars($row['recommended_decision']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Opportunities To Avoid</h2><span class="status">Discipline</span></div>
    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Risk</th><th>Reason</th></tr></thead><tbody>
      <?php foreach ($data['avoid'] as $row): ?><tr><td><a href="/pursuits/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a><br><small><?= htmlspecialchars($row['classification']) ?></small></td><td><?= (int)$row['risk_score'] ?></td><td><?= htmlspecialchars($row['next_best_action']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Future Watchlist Opportunities</h2><span class="status">Monitor</span></div>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Opportunity</th><th>Review</th><th>Owner</th></tr></thead><tbody>
      <?php foreach ($data['watchlist'] as $row): ?><tr><td><?= htmlspecialchars($row['status']) ?></td><td><a href="/pursuits/detail?id=<?= (int)$row['opportunity_id'] ?>"><?= htmlspecialchars($row['opportunity_name']) ?></a><br><small><?= htmlspecialchars($row['region_name'] ?? '') ?> · Score <?= (int)$row['pursuit_score'] ?></small></td><td><?= htmlspecialchars($row['next_review_date']) ?></td><td><?= htmlspecialchars($row['owner']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Capacity Blocking Pursuits</h2><a class="btn secondary" href="/capacity-radar">Capacity Radar</a></div>
    <div class="action-stack"><?php foreach ($data['capacityBlocked'] as $row): ?><div><span class="priority medium">Capacity Gap</span><h3><a href="/pursuits/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></h3><p><?= htmlspecialchars($row['capacity_gap']) ?></p></div><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Relationship Gaps Blocking Pursuits</h2><a class="btn secondary" href="/relationship-graph">Relationship Graph</a></div>
    <div class="action-stack"><?php foreach ($data['relationshipBlocked'] as $row): ?><div><span class="priority medium">Relationship Gap</span><h3><a href="/pursuits/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></h3><p><?= htmlspecialchars($row['relationship_gap']) ?></p></div><?php endforeach; ?></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Pursuit Board</h2><span class="status">Stages</span></div>
  <div class="kanban">
    <?php foreach ($data['board'] as $stage => $items): ?>
      <div class="kanban-column">
        <h3><?= htmlspecialchars($stage) ?></h3>
        <?php foreach (array_slice($items, 0, 8) as $item): ?>
          <article class="mini-card">
            <strong><a href="/pursuits/detail?id=<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['name']) ?></a></strong>
            <small><?= htmlspecialchars($item['recommended_decision']) ?> · Score <?= (int)$item['pursuit_score'] ?></small>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Pursuit Recommendations</h2><a class="btn secondary" href="/recommendations">Recommended Actions</a></div>
  <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Theater</th><th>Recommended Action</th><th>Owner</th></tr></thead><tbody>
    <?php foreach ($data['recommendations'] as $row): ?><tr><td><span class="priority <?= strtolower($row['priority']) ?>"><?= htmlspecialchars($row['priority']) ?></span></td><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><strong><?= htmlspecialchars($row['title']) ?></strong><br><small><?= htmlspecialchars($row['recommended_next_action']) ?></small></td><td><?= htmlspecialchars($row['assigned_owner']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
