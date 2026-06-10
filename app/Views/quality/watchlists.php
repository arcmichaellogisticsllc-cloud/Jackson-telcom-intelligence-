<section class="page-header">
  <p class="eyebrow">Watchlists</p>
  <h1>Keep useful intelligence without creating noise.</h1>
  <p>Watchlists preserve organizations, contacts, and signals that have future value but may not be ready for active pursuit today.</p>
</section>

<section class="metrics">
  <?php foreach (['Monitoring','Escalated','Archived'] as $status): ?>
    <?php $count = 0; foreach ($summary as $row) { if ($row['status'] === $status) $count = (int)$row['count']; } ?>
    <div><span><?= htmlspecialchars($status) ?></span><strong><?= $count ?></strong></div>
  <?php endforeach; ?>
</section>

<section class="panel">
  <div class="panel-title"><h2>Watchlist Intelligence</h2><form method="post" action="/quality/rebuild"><input type="hidden" name="return_to" value="/watchlists"><button class="btn secondary">Rebuild Quality</button></form></div>
  <div class="table-wrap"><table><thead><tr><th>Status</th><th>Entity</th><th>Theater</th><th>Signal</th><th>Accumulation</th><th>Purpose</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?><tr>
      <td><span class="priority <?= $item['status'] === 'Escalated' ? 'critical' : 'medium' ?>"><?= htmlspecialchars($item['status']) ?></span></td>
      <td><strong><?= htmlspecialchars($item['organization_name'] ?: $item['contact_name']) ?></strong><br><small><?= htmlspecialchars($item['contact_name'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td>
      <td><a href="/record?type=signal&id=<?= (int)$item['signal_id'] ?>"><?= htmlspecialchars($item['signal_title']) ?></a><br><small><?= htmlspecialchars($item['signal_type']) ?> · <?= htmlspecialchars($item['source_type']) ?></small></td>
      <td><?= htmlspecialchars($item['current_status'] ?? '') ?><br><small><?= (int)($item['accumulated_signal_count'] ?? 0) ?> related signals</small></td>
      <td><?= htmlspecialchars($item['purpose']) ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="6">No watchlist items yet. Run harvesters and process source items.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
