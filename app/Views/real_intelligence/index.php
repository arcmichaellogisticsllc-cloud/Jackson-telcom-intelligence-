<section class="page-header command-page-header">
  <p class="eyebrow">Review-Gated Research</p>
  <h1>Real Intelligence Explorer</h1>
  <p>Click into imported public research, source evidence, enrichment, data quality status, and workflow context without treating it as approved operating truth.</p>
</section>

<section class="grid four action-first-grid">
  <article>
    <span>What This Is</span>
    <p>A read-only explorer for the real-hunt import and enrichment records.</p>
  </article>
  <article>
    <span>Why It Matters</span>
    <p>Operators can inspect the source trail before trusting a record.</p>
  </article>
  <article>
    <span>Next Step</span>
    <p>Open a dataset, inspect source evidence, and resolve review items.</p>
  </article>
  <article>
    <span>Risk Of Inaction</span>
    <p>Useful public intelligence can sit unverified and never become work, capacity, or influence.</p>
  </article>
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
