<?php
$title = $businessRecord['title'] ?? ($signal['title'] ?? 'Real hunt record');
$sourceUrl = $import['source_url'] ?? '';
?>
<section class="page-header command-page-header">
  <p class="eyebrow">Real Intelligence Detail</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($import['dataset']) ?> · <?= htmlspecialchars($import['review_status']) ?> · Confidence <?= (int)$import['confidence_score'] ?></p>
</section>

<section class="grid four action-first-grid">
  <article><span>What This Is</span><p>Imported public research linked to raw source, signal, enrichment, and review context.</p></article>
  <article><span>Why It Matters</span><p>This record may become work, capacity, need, or influence only after review.</p></article>
  <article><span>Next Step</span><p>Verify source evidence, resolve review items, and fill missing fields.</p></article>
  <article><span>Risk Of Inaction</span><p>Unreviewed intelligence cannot safely drive pursuit, onboarding, or relationship action.</p></article>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Source Evidence</h2><?php if ($sourceUrl): ?><a class="btn secondary" href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener">Open Source</a><?php endif; ?></div>
    <dl class="detail-list">
      <dt>Import Source</dt><dd><?= htmlspecialchars($import['import_source']) ?></dd>
      <dt>Source Type</dt><dd><?= htmlspecialchars($import['source_type']) ?></dd>
      <dt>Source URL</dt><dd><?= htmlspecialchars($sourceUrl ?: 'Not provided') ?></dd>
      <dt>Source File</dt><dd><?= htmlspecialchars($import['source_file']) ?> row <?= (int)$import['source_row'] ?></dd>
      <dt>Status</dt><dd><?= htmlspecialchars($import['status']) ?> · <?= htmlspecialchars($import['review_status']) ?></dd>
      <dt>Notes</dt><dd><?= htmlspecialchars($import['notes'] ?? '') ?></dd>
    </dl>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Created Business Record</h2></div>
    <?php if ($businessRecord): ?>
      <dl class="detail-list">
        <dt>Type</dt><dd><?= htmlspecialchars($businessRecord['_record_type']) ?></dd>
        <dt>Name</dt><dd><?= htmlspecialchars($businessRecord['title'] ?? '') ?></dd>
        <?php foreach (['type','account_type','status','approval_stage','stage','market','state','website','notes'] as $field): ?>
          <?php if (array_key_exists($field, $businessRecord) && $businessRecord[$field] !== '' && $businessRecord[$field] !== null): ?>
            <dt><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?></dt><dd><?= htmlspecialchars((string)$businessRecord[$field]) ?></dd>
          <?php endif; ?>
        <?php endforeach; ?>
      </dl>
    <?php else: ?>
      <p>No trusted business record was created. This row remains a review-gated source item.</p>
    <?php endif; ?>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Signal Quality</h2></div>
    <?php if ($signal): ?>
      <dl class="detail-list">
        <dt>Signal</dt><dd><?= htmlspecialchars($signal['title']) ?></dd>
        <dt>Type</dt><dd><?= htmlspecialchars($signal['signal_type']) ?> · <?= htmlspecialchars($signal['source_type']) ?></dd>
        <dt>Region</dt><dd><?= htmlspecialchars($signal['region_name'] ?? '') ?></dd>
        <dt>Recommended Next Action</dt><dd><?= htmlspecialchars($signal['recommended_next_action'] ?? '') ?></dd>
        <dt>Classification</dt><dd><?= htmlspecialchars($quality['classification'] ?? 'Not classified') ?></dd>
        <dt>Reason</dt><dd><?= htmlspecialchars($quality['reason_for_classification'] ?? '') ?></dd>
      </dl>
    <?php else: ?>
      <p>No signal is linked.</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Review Items</h2></div>
    <div class="stack-list">
      <?php foreach ($reviewItems as $item): ?>
        <article>
          <strong><?= htmlspecialchars($item['title'] ?? $item['issue_type'] ?? 'Review item') ?></strong>
          <p><?= htmlspecialchars($item['description'] ?? '') ?></p>
          <small><?= htmlspecialchars($item['severity'] ?? '') ?> · <?= htmlspecialchars($item['status'] ?? '') ?> · <?= htmlspecialchars($item['assigned_owner'] ?? '') ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$reviewItems): ?><p>No review items are linked.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Enrichment Trail</h2></div>
    <div class="stack-list">
      <?php foreach ($enrichments as $item): ?>
        <article>
          <strong><?= htmlspecialchars($item['enrichment_type']) ?></strong>
          <p><?= htmlspecialchars($item['notes'] ?? '') ?></p>
          <small><?= htmlspecialchars($item['enriched_record_type'] ?? '') ?> · confidence <?= (int)$item['confidence_score'] ?> · <?= htmlspecialchars($item['review_status']) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$enrichments): ?><p>No enrichment records are linked.</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Executive Packages</h2></div>
    <div class="stack-list">
      <?php foreach ($packages as $package): ?>
        <article>
          <strong><?= htmlspecialchars($package['package_title']) ?></strong>
          <p><?= htmlspecialchars($package['executive_summary']) ?></p>
          <small><?= htmlspecialchars($package['package_type']) ?> · <?= htmlspecialchars($package['owner']) ?> · <?= htmlspecialchars($package['package_status']) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$packages): ?><p>No executive packages are linked.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Raw Import Payload</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Field</th><th>Value</th></tr></thead>
      <tbody>
        <?php foreach (($rawPayload['row'] ?? []) as $field => $value): ?>
          <tr><td><?= htmlspecialchars((string)$field) ?></td><td><?= htmlspecialchars((string)$value) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($rawPayload['row'])): ?><tr><td colspan="2">No raw payload was available.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
