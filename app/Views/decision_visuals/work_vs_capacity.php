<section class="page-header command-page-header"><p class="eyebrow">What Should We Pursue?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/work-vs-capacity">Work vs Capacity</a><a href="/decision-visuals/opportunity-flow">Opportunity Flow</a><a href="/decision-visuals/capacity-heatmap">Capacity Heat</a></nav>
<?php $why='The matrix prevents pursuit decisions from outrunning deployable crews or leaving crews idle in weak work markets.'; $recommended='Attack high-work/high-capacity markets, recruit in high-work/low-capacity markets, sell in low-work/high-capacity markets, and monitor low/low markets.'; $next='Select every Recruit market and create a capacity recruitment action before approving more pursuit activity.'; $risk='High work with low capacity creates bid risk, margin risk, and reputation risk.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Market Posture Matrix</h2><span class="status">Attack / Recruit / Sell / Avoid</span></div>
  <div class="table-wrap"><table><thead><tr><th>Market</th><th>Theater</th><th>Work</th><th>Capacity</th><th>Posture</th><th>Recommended Action</th><th>Drill Down</th></tr></thead><tbody>
    <?php foreach ($workCapacity as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['market']) ?></strong><br><small><?= htmlspecialchars($row['why_it_matters']) ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td>
      <td><?= (int)$row['work_score'] ?></td>
      <td><?= (int)$row['capacity_score'] ?></td>
      <td><span class="priority <?= $row['posture'] === 'Attack' ? 'high' : ($row['posture'] === 'Recruit' ? 'critical' : 'medium') ?>"><?= htmlspecialchars($row['posture']) ?></span></td>
      <td><?= htmlspecialchars($row['recommended_action']) ?></td>
      <td><a class="btn secondary" href="<?= htmlspecialchars($row['href']) ?>">Open</a></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
