<section class="page-header command-page-header">
  <p class="eyebrow">Review-Gated Research</p>
  <h1>Real Intelligence Explorer</h1>
  <p>Click into imported public research, source evidence, enrichment, data quality status, and workflow context without treating it as approved operating truth.</p>
</section>

<section class="operator-note">
  <strong>Review first:</strong>
  <span>Open a dataset, inspect source evidence, and resolve review items before using imported intelligence operationally.</span>
</section>

<section class="metrics">
  <?php foreach ($metrics as $label => $value): ?>
    <div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div>
  <?php endforeach; ?>
</section>

<section class="workspace-link-grid">
  <?php foreach ($cards as $card): ?>
    <a class="workspace-link" href="/real-intelligence/dataset?dataset=<?= urlencode($card['dataset']) ?>">
      <strong><?= htmlspecialchars($card['title']) ?></strong>
      <span><?= htmlspecialchars($card['description']) ?></span>
      <small><?= (int)$card['imports'] ?> imported · <?= (int)$card['review'] ?> need review · <?= (int)$card['enriched'] ?> enriched</small>
    </a>
  <?php endforeach; ?>
</section>
