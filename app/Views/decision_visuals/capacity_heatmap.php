<section class="page-header command-page-header"><p class="eyebrow">Who Should We Recruit?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/capacity-heatmap">Capacity Heat</a><a href="/capacity-radar">Capacity Radar</a><a href="/targets">Targets</a></nav>
<?php $why='Capacity heat identifies the exact disciplines that block growth, pursuit, and execution readiness.'; $recommended='Recruit or promote providers where severity is High or Critical.'; $next='Open Capacity Radar and assign a recruiting action for the hottest region/discipline cell.'; $risk='Unresolved gaps turn good opportunities into no-bid, low-margin, or failed execution risk.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Region / Discipline Heat Map</h2><span class="status">Current vs Target Crews</span></div>
  <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Market</th><th>Discipline</th><th>Current</th><th>Target</th><th>Gap</th><th>Severity</th><th>Recommended Recruiting Action</th></tr></thead><tbody>
    <?php foreach ($capacityHeatmap as $row): ?><tr>
      <td><?= htmlspecialchars($row['region_name']) ?></td><td><?= htmlspecialchars($row['market']) ?></td><td><strong><?= htmlspecialchars($row['discipline']) ?></strong></td>
      <td><?= (int)$row['current_capacity'] ?></td><td><?= (int)$row['target_capacity'] ?></td><td><?= (int)$row['gap'] ?></td>
      <td><span class="priority <?= strtolower($row['severity']) ?>"><?= htmlspecialchars($row['severity']) ?></span></td>
      <td><?= htmlspecialchars($row['recommended_action']) ?><br><a href="<?= htmlspecialchars($row['href']) ?>">Open Capacity Radar</a></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
