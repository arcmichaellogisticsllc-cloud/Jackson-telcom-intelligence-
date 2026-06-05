<section class="page-header">
  <p class="eyebrow">Platform Module</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<section class="panel">
  <h2><?= htmlspecialchars($title) ?> Scope</h2>
  <p><?= htmlspecialchars($body) ?></p>
  <div class="module-grid">
    <?php foreach ($items as $item): ?>
      <article>
        <h3><?= htmlspecialchars($item) ?></h3>
        <p>Acquisition intelligence input for the Jackson Intelligence Platform decision layer.</p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2>Decision Support Feed</h2>
  <p>This module should create or enrich signals, capacity records, relationship context, opportunity intelligence, and recommended actions. SyncERP remains the final integration layer only.</p>
</section>
