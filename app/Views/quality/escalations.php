<section class="page-header">
  <p class="eyebrow">Escalation Center</p>
  <h1>What matters enough to interrupt the day?</h1>
  <p>Escalations are high-value signals or accumulated entity activity that should become owner action, target creation, or hunt movement.</p>
</section>

<section class="metrics">
  <div><span>Critical Escalations</span><strong><?= count($escalations) ?></strong></div>
  <?php foreach ($byRegion as $row): ?>
    <div><span><?= htmlspecialchars($row['region_name'] ?? 'National') ?></span><strong><?= (int)$row['count'] ?></strong></div>
  <?php endforeach; ?>
</section>

<section class="panel">
  <div class="panel-title"><h2>High Value Escalations</h2><form method="post" action="/quality/rebuild"><input type="hidden" name="return_to" value="/escalations"><button class="btn secondary">Rebuild Quality</button></form></div>
  <div class="table-wrap"><table><thead><tr><th>Escalation</th><th>Theater</th><th>Scores</th><th>Why It Escalated</th><th>Recommended Action</th><th>Supporting Signals</th></tr></thead><tbody>
    <?php foreach ($escalations as $item): ?><tr>
      <td><a href="/record?type=signal&id=<?= (int)$item['signal_id'] ?>"><strong><?= htmlspecialchars($item['title']) ?></strong></a><br><small><?= htmlspecialchars($item['signal_type']) ?> · <?= htmlspecialchars($item['source_type']) ?> · <?= htmlspecialchars($item['owner']) ?></small></td>
      <td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td>
      <td>Value <?= (int)$item['signal_value_score'] ?><br><small>Impact <?= (int)$item['impact_score'] ?> · Confidence <?= (int)$item['confidence_score'] ?></small></td>
      <td><?= htmlspecialchars($item['reason_for_classification']) ?></td>
      <td><?= htmlspecialchars($item['recommended_next_action'] ?: 'Assign next action and decide whether to create target or hunt assignment.') ?></td>
      <td>
        <?php foreach (array_slice($support[(int)($item['accumulation_profile_id'] ?? 0)] ?? [], 0, 4) as $signal): ?>
          <div><a href="/record?type=signal&id=<?= (int)$signal['signal_id'] ?>"><?= htmlspecialchars($signal['title']) ?></a></div>
        <?php endforeach; ?>
      </td>
    </tr><?php endforeach; ?>
    <?php if (!$escalations): ?><tr><td colspan="6">No escalations currently. Watchlists are monitoring lower-value intelligence.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
