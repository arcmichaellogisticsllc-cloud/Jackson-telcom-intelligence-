<section class="page-header">
  <p class="eyebrow">Subcontractor Acquisition</p>
  <h1>Turn targets into deployable capacity.</h1>
  <p>Qualification, compliance, approved capacity, preferred network, and strategic partner development.</p>
</section>

<section class="metrics">
  <div><span>New Subcontractor Candidates</span><strong><?= $metrics['new_candidates'] ?></strong></div>
  <div><span>Compliance Issues</span><strong><?= $metrics['compliance_issues'] ?></strong></div>
  <div><span>Capacity Added This Month</span><strong><?= $metrics['capacity_added_month'] ?></strong></div>
  <div><span>Strategic Partner Candidates</span><strong><?= $metrics['strategic_candidates'] ?></strong></div>
  <div><span>Preferred Network Growth</span><strong><?= $metrics['preferred_growth'] ?></strong></div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Pipeline Kanban</h2><span class="status">Prospect to Strategic Partner</span></div>
  <div class="kanban">
    <?php foreach ($kanban as $stage => $items): ?>
      <div class="kanban-column">
        <h3><?= htmlspecialchars($stage) ?> <span><?= count($items) ?></span></h3>
        <?php foreach ($items as $item): ?>
          <article class="signal-card">
            <div class="panel-title">
              <span class="priority <?= strtolower(str_replace(' ', '-', $item['capacity_contribution_category'] ?? 'low')) ?>"><?= htmlspecialchars($item['capacity_contribution_category'] ?? 'Low') ?></span>
              <small><?= (int)($item['capacity_contribution_score'] ?? 0) ?> capacity</small>
            </div>
            <h4><a href="/subcontractor-acquisition/detail?id=<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['company_name'] ?: $item['organization_name']) ?></a></h4>
            <p><?= htmlspecialchars($item['region_name']) ?> · <?= (int)$item['available_crew_count'] ?> available crews</p>
            <small><?= htmlspecialchars($item['qualification_result'] ?? 'No scorecard') ?> · <?= (int)($item['qualification_score'] ?? 0) ?> qualification</small>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Preferred Network Candidates</h2><span class="status"><?= count($rows) ?> subcontractors</span></div>
  <div class="table-wrap"><table><thead><tr><th>Company</th><th>Theater</th><th>Stage</th><th>Qualification</th><th>Capacity Contribution</th><th>Available Crews</th><th>Promotion</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr>
      <td><a href="/subcontractor-acquisition/detail?id=<?= (int)$row['id'] ?>"><strong><?= htmlspecialchars($row['company_name'] ?: $row['organization_name']) ?></strong></a><br><small><?= htmlspecialchars($row['services_offered']) ?></small></td>
      <td><?= htmlspecialchars($row['region_name']) ?><br><small><?= htmlspecialchars($row['states_served'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($row['approval_stage']) ?></td>
      <td><?= (int)($row['qualification_score'] ?? 0) ?><br><small><?= htmlspecialchars($row['qualification_result'] ?? '') ?></small></td>
      <td><?= (int)($row['capacity_contribution_score'] ?? 0) ?><br><small><?= htmlspecialchars($row['capacity_contribution_category'] ?? '') ?></small></td>
      <td><?= (int)$row['available_crew_count'] ?> / <?= (int)$row['crew_count'] ?></td>
      <td><?= htmlspecialchars($row['promotion_recommendation'] ?? '') ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
