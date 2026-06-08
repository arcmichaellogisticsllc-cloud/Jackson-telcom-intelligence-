<section class="page-header">
  <p class="eyebrow">Strategic Account, Workforce & Competitive Intelligence</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionId === null ? 'active' : '' ?>" href="/strategic-account-intelligence">National</a>
  <a href="/strategic-account-intelligence/southeast">Southeast</a>
  <a href="/strategic-account-intelligence/great-lakes">Great Lakes</a>
  <a href="/strategic-account-intelligence/southwest">Southwest</a>
  <a href="/workforce-intelligence">Workforce</a>
  <a href="/competitive-intelligence">Competitors</a>
  <form method="post" action="/strategic-intelligence/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Intelligence</button></form>
</nav>

<section class="metrics">
  <div><span>Strategic Accounts</span><strong><?= (int)$metrics['accounts'] ?></strong></div>
  <div><span>Workforce Profiles</span><strong><?= (int)$metrics['workforce'] ?></strong></div>
  <div><span>Competitors</span><strong><?= (int)$metrics['competitors'] ?></strong></div>
  <div><span>Competitive Pressure</span><strong><?= (int)$metrics['avg_pressure'] ?></strong></div>
</section>

<?php
$listEyebrow = 'Strategic Workspace';
$listTitle = 'Filter account, workforce, and competitor intelligence';
$listStatuses = ['Active','Testing','Monitoring','Available','Recruitable','Low','Medium','High','Critical'];
require __DIR__ . '/../components/list_toolbar.php';
?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Who Has Work</h2><span class="status">Strategic Accounts</span></div>
    <div class="table-wrap"><table><thead><tr><th>Account</th><th>Theater</th><th>Scores</th><th>Gap / Action</th></tr></thead><tbody>
      <?php foreach ($accounts as $account): ?><tr>
        <td><strong><a href="/strategic-account-intelligence/detail?id=<?= (int)$account['id'] ?>"><?= htmlspecialchars($account['account_name']) ?></a></strong><br><small><?= htmlspecialchars($account['account_type']) ?> · <?= htmlspecialchars($account['market'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($account['region_name'] ?? '') ?><br><small><?= htmlspecialchars($account['owner'] ?? $account['primary_owner'] ?? '') ?></small></td>
        <td>Rel <?= (int)($account['relationship_health_score'] ?? $account['relationship_coverage_score']) ?> · Opp <?= (int)($account['opportunity_score'] ?? $account['opportunity_volume_score']) ?><br><small>Influence <?= (int)$account['influence_coverage_score'] ?> · Strategic <?= (int)$account['strategic_score'] ?></small></td>
        <td><?= htmlspecialchars($account['recommended_action'] ?? $account['next_best_action'] ?? '') ?><br><small><?= (int)($account['recent_signal_count'] ?? 0) ?> recent signals</small></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Who Runs The Work</h2><span class="status">Workforce Movers</span></div>
    <div class="table-wrap"><table><thead><tr><th>Person</th><th>Role</th><th>Scores</th><th>Recommended Action</th></tr></thead><tbody>
      <?php foreach ($workforce as $person): ?><tr>
        <td><strong><?= htmlspecialchars($person['name']) ?></strong><br><small><?= htmlspecialchars($person['current_company']) ?> · <?= htmlspecialchars($person['region_name'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($person['role_type']) ?><br><small><?= htmlspecialchars($person['availability_status']) ?></small></td>
        <td>Influence <?= (int)$person['influence_score'] ?> · Recruit <?= (int)$person['recruitability_score'] ?><br><small>Relationship <?= (int)$person['relationship_score'] ?></small></td>
        <td><?= htmlspecialchars($person['recommended_action']) ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Who Else Is Chasing Work</h2><span class="status">Competitive Pressure</span></div>
    <div class="table-wrap"><table><thead><tr><th>Competitor</th><th>Market</th><th>Pressure</th><th>Signals</th></tr></thead><tbody>
      <?php foreach ($competitors as $competitor): ?><tr>
        <td><strong><?= htmlspecialchars($competitor['competitor_name']) ?></strong><br><small><?= htmlspecialchars($competitor['region_name'] ?? '') ?> · <?= htmlspecialchars($competitor['threat_level']) ?></small></td>
        <td><?= htmlspecialchars($competitor['market']) ?><br><small><?= htmlspecialchars($competitor['services']) ?></small></td>
        <td><?= (int)$competitor['competitive_pressure_score'] ?><br><small>Capacity growth <?= (int)$competitor['capacity_growth_score'] ?></small></td>
        <td><?= htmlspecialchars($competitor['hiring_activity']) ?><br><small><?= htmlspecialchars($competitor['recommended_action']) ?></small></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>What Should We Do</h2><a class="btn secondary" href="/recommendations">Recommendations</a></div>
    <div class="action-stack">
      <?php foreach ($recommendations as $rec): ?><article>
        <span class="priority <?= strtolower($rec['priority']) ?>"><?= htmlspecialchars($rec['priority']) ?> · <?= (int)$rec['priority_score'] ?></span>
        <h3><?= htmlspecialchars($rec['title']) ?></h3>
        <p><?= htmlspecialchars($rec['recommended_next_action']) ?></p>
        <small><?= htmlspecialchars($rec['assigned_owner']) ?> · <?= htmlspecialchars($rec['region_name'] ?? 'National') ?></small>
      </article><?php endforeach; ?>
    </div>
  </div>
</section>
