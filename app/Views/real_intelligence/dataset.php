<section class="page-header command-page-header">
  <p class="eyebrow">Real Intelligence</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<section class="metrics">
  <?php foreach ($metrics as $label => $value): ?>
    <div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div>
  <?php endforeach; ?>
</section>

<section class="panel">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Source-Gated Rows</p>
      <h2><?= htmlspecialchars($title) ?></h2>
    </div>
    <a class="btn secondary" href="/real-intelligence">Explorer</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Record</th><th>Status</th><th>Signal</th><th>Why It Matters</th><th>Next Step</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>
              <strong><a href="/real-intelligence/detail?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars($row['display_title']) ?></a></strong>
              <br><small><?= htmlspecialchars($row['created_record_type'] ?: $row['dataset']) ?> #<?= (int)($row['created_record_id'] ?? 0) ?> · row <?= (int)$row['source_row'] ?></small>
            </td>
            <td>
              <span class="status-badge"><?= htmlspecialchars($row['review_status']) ?></span>
              <br><small>Confidence <?= (int)$row['confidence_score'] ?> · <?= htmlspecialchars($row['status']) ?></small>
            </td>
            <td>
              <?= htmlspecialchars($row['classification'] ?? 'Unclassified') ?>
              <br><small><?= htmlspecialchars($row['signal_type'] ?? '') ?></small>
            </td>
            <td><?= htmlspecialchars($row['why_it_matters']) ?></td>
            <td><?= htmlspecialchars($row['next_step']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">No real-hunt rows are loaded for this dataset.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
