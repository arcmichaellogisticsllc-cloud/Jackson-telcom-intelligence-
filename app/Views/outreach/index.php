<section class="page-header">
  <p class="eyebrow">Outreach Intelligence</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?> This is preparation only. No emails, SMS, LinkedIn messages, Facebook messages, or content are sent automatically.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= !$regionId ? 'active' : '' ?>" href="/outreach">National Outreach Queue</a>
  <a href="/outreach/southeast">Southeast</a>
  <a href="/outreach/great-lakes">Great Lakes</a>
  <a href="/outreach/southwest">Southwest</a>
</nav>

<section class="metrics">
  <div><span>Ready Outreach</span><strong><?= (int)$metrics['ready'] ?></strong></div>
  <div><span>Critical Outreach</span><strong><?= (int)$metrics['critical'] ?></strong></div>
  <div><span>Overdue Outreach</span><strong><?= (int)$metrics['overdue'] ?></strong></div>
  <div><span>Capacity Tied</span><strong><?= (int)$metrics['capacity'] ?></strong></div>
  <div><span>Relationship Tied</span><strong><?= (int)$metrics['relationship'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title">
      <h2>Outreach Queue</h2>
      <form method="post" action="/outreach/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="secondary">Rebuild</button></form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Priority</th><th>Who / Why</th><th>Theater</th><th>Channel</th><th>Goal</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><span class="priority <?= strtolower($item['priority']) ?>"><?= htmlspecialchars($item['priority']) ?></span></td>
          <td><a href="/outreach/detail?id=<?= (int)$item['id'] ?>"><strong><?= htmlspecialchars($item['outreach_title']) ?></strong></a><br><small><?= htmlspecialchars($item['reason']) ?></small></td>
          <td><?= htmlspecialchars($item['region_name'] ?? 'National') ?><br><small><?= htmlspecialchars($item['owner']) ?></small></td>
          <td><?= htmlspecialchars($item['channel']) ?></td>
          <td><?= htmlspecialchars($item['outreach_goal']) ?></td>
          <td><?= htmlspecialchars($item['status']) ?><br><small><?= htmlspecialchars($item['due_date'] ?? '') ?></small></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="6">No outreach prep records yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <h2>Scripts Needing Review</h2>
    <div class="table-wrap"><table><thead><tr><th>Type</th><th>Outreach</th><th>Status</th><th>Review</th></tr></thead><tbody>
      <?php foreach ($scripts as $script): ?>
        <tr>
          <td><?= htmlspecialchars($script['script_type']) ?></td>
          <td><a href="/outreach/detail?id=<?= (int)$script['outreach_intelligence_id'] ?>"><?= htmlspecialchars($script['outreach_title']) ?></a><br><small><?= htmlspecialchars($script['region_name'] ?? '') ?></small></td>
          <td><?= htmlspecialchars($script['review_status']) ?></td>
          <td>
            <form method="post" action="/outreach/scripts/review" class="inline-form">
              <input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
              <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
              <select name="review_status"><option>Needs Review</option><option>Approved</option><option>Rejected</option><option>Used</option></select>
              <button>Update</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Outreach by Channel</h2>
    <div class="table-wrap"><table><thead><tr><th>Channel</th><th>Count</th></tr></thead><tbody><?php foreach ($channels as $row): ?><tr><td><?= htmlspecialchars($row['label']) ?></td><td><?= (int)$row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Outreach by Goal</h2>
    <div class="table-wrap"><table><thead><tr><th>Goal</th><th>Count</th></tr></thead><tbody><?php foreach ($goals as $row): ?><tr><td><?= htmlspecialchars($row['label']) ?></td><td><?= (int)$row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="panel">
  <h2>Sequence Plan Views</h2>
  <div class="table-wrap"><table><thead><tr><th>Plan</th><th>Step</th><th>Target</th><th>Channel</th><th>Delay</th><th>Message / Goal</th></tr></thead><tbody>
    <?php foreach ($sequences as $step): ?>
      <tr><td><?= htmlspecialchars($step['name']) ?><br><small><?= htmlspecialchars($step['region_name'] ?? 'National') ?></small></td><td><?= (int)$step['step_number'] ?></td><td><?= htmlspecialchars($step['target_type']) ?></td><td><?= htmlspecialchars($step['channel']) ?></td><td><?= (int)$step['delay_days'] ?> days</td><td><?= htmlspecialchars($step['message_template']) ?><br><small><?= htmlspecialchars($step['purpose']) ?></small></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
