<section class="page-header command-page-header"><p class="eyebrow">What Should We Avoid Or Defend?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/competitive-pressure">Competitive Pressure</a><a href="/competitive-intelligence">Competitive Intelligence</a></nav>
<?php $why='Competitive pressure shows where rivals are hiring, winning awards, entering markets, opening offices, or recruiting subcontractors.'; $recommended='Increase account coverage and capacity recruiting where pressure is High or Critical.'; $next='Open the hottest competitive pressure row and assign a response in Decision Support.'; $risk='Competitors can lock up relationships, crews, and market position before Jackson reacts.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Competitor Pressure By Market</h2><span class="status">Threat Response</span></div>
  <div class="table-wrap"><table><thead><tr><th>Competitor</th><th>Market</th><th>Hiring</th><th>Awards</th><th>Relationship</th><th>Capacity</th><th>Entry</th><th>Threat</th><th>Response</th></tr></thead><tbody>
    <?php foreach ($competitivePressure as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['competitor_name'] ?? 'Competitor') ?></strong><br><small><?= htmlspecialchars($row['region_name'] ?? 'National') ?></small></td>
      <td><?= htmlspecialchars($row['market'] ?: 'Regional') ?><br><small><?= htmlspecialchars($row['strategic_account'] ?: '') ?> <?= htmlspecialchars($row['discipline'] ?: '') ?></small></td>
      <td><?= (int)$row['hiring_pressure'] ?></td><td><?= (int)$row['award_pressure'] ?></td><td><?= (int)$row['relationship_pressure'] ?></td><td><?= (int)$row['capacity_pressure'] ?></td><td><?= (int)$row['market_entry_pressure'] ?></td>
      <td><span class="priority <?= strtolower($row['threat_level']) ?>"><?= htmlspecialchars($row['threat_level']) ?> · <?= (int)$row['competitive_pressure_score'] ?></span></td>
      <td><?= htmlspecialchars($row['recommended_action']) ?><br><a href="<?= htmlspecialchars($row['href']) ?>">Open Competitive Intel</a></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
