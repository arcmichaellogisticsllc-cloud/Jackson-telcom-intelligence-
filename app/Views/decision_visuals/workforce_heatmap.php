<section class="page-header command-page-header"><p class="eyebrow">Where Are Talent Gaps?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/workforce-heatmap">Workforce Heat</a><a href="/workforce-intelligence">Workforce Intelligence</a></nav>
<?php $why='Workforce heat shows where project leadership and technical talent can create capacity, influence, or competitive advantage.'; $recommended='Recruit high-recruitability roles in markets with movement signals or weak capacity disciplines.'; $next='Open Workforce Intelligence and assign relationship or recruiting action for the top role cluster.'; $risk='Talent movement can shift work access and field capacity before Jackson sees the opportunity.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Market / Role Heat Map</h2><span class="status">Strength + Recruitability</span></div>
  <div class="table-wrap"><table><thead><tr><th>Market</th><th>Theater</th><th>Role</th><th>Strength</th><th>Recruitability</th><th>Movement</th><th>Recommended Action</th></tr></thead><tbody>
    <?php foreach ($workforceHeatmap as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['market'] ?: 'Regional') ?></strong></td><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($row['role_type']) ?></td>
      <td><?= (int)$row['workforce_strength'] ?></td><td><?= (int)$row['recruitability'] ?></td><td><?= (int)$row['movement_signals'] ?></td>
      <td><?= htmlspecialchars($row['recommended_action']) ?><br><a href="<?= htmlspecialchars($row['href']) ?>">Open Workforce</a></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
