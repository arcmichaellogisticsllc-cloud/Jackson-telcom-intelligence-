<section class="page-header command-page-header"><p class="eyebrow">Which Relationships Should We Strengthen?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/account-health">Account Health</a><a href="/decision-visuals/ecosystem-map">Ecosystem Map</a><a href="/strategic-account-intelligence">Strategic Accounts</a></nav>
<?php $why='Strategic accounts control repeat work, account influence, and future fiber backbone opportunity volume.'; $recommended='Strengthen high-value accounts where relationship health or influence coverage is below the opportunity and capacity demand.'; $next='Open the weakest high-value account and assign a relationship action to the primary owner, secondary owner, or shared regional ownership.'; $risk='Weak account coverage lets competitors become the default path to work.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2>Strategic Account Health</h2><span class="status">Coverage vs Threat</span></div>
  <div class="table-wrap"><table><thead><tr><th>Account</th><th>Theater</th><th>Relationship</th><th>Influence</th><th>Opportunity</th><th>Demand</th><th>Threat</th><th>Health</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($accountHealth as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['account_name']) ?></strong><br><small><?= htmlspecialchars($row['account_type']) ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td>
      <td><?= (int)$row['relationship_health_score'] ?></td>
      <td><?= (int)$row['influence_coverage_score'] ?></td>
      <td><?= (int)$row['opportunity_score'] ?></td>
      <td><?= (int)$row['capacity_demand_score'] ?></td>
      <td><?= (int)$row['competitive_threat'] ?></td>
      <td><strong><?= (int)$row['account_health_score'] ?></strong></td>
      <td><?= htmlspecialchars($row['recommended_action']) ?><br><a href="<?= htmlspecialchars($row['href']) ?>">Open account</a></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
