<section class="page-header">
  <p class="eyebrow">Acquisition Harvesters</p>
  <h1>Automated acquisition fuel.</h1>
  <p>Automated and semi-automated sources capture review-gated source items. Reviewed items become scored signals, and signals create recommended actions. Manual entry is reserved for physical traffic: referrals, conferences, jobsite conversations, phone calls, and face-to-face networking.</p>
</section>

<section class="metrics">
  <div><span>Active Sources</span><strong><?= $metrics['active_sources'] ?></strong></div>
  <div><span>Source Items Waiting Review</span><strong><?= $metrics['waiting'] ?></strong></div>
  <div><span>Signals This Week</span><strong><?= $metrics['signals_week'] ?></strong></div>
  <div><span>Recommendations This Week</span><strong><?= $metrics['recommendations_week'] ?></strong></div>
  <div><span>Failed Sources</span><strong><?= $metrics['failed_sources'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Signal Source Registry</h2><span class="status">Automated first</span></div>
    <form method="post" action="/harvesters/sources" class="form-grid compact">
      <label>Name <input name="name" required></label>
      <label>Source Type <select name="source_type"><?php foreach ($options['sourceTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Theater <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Target <select name="target_category"><?php foreach ($options['categories'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Collection <select name="collection_method"><?php foreach ($options['methods'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>State <select name="state"><option></option><?php foreach ($options['states'] as $state): ?><option><?= htmlspecialchars($state) ?></option><?php endforeach; ?></select></label>
      <label>City <input name="city"></label>
      <label>Frequency <select name="frequency"><?php foreach ($options['frequencies'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Status <select name="status"><?php foreach ($options['statuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Source URL <input name="source_url"></label>
      <label class="full">Search Query <input name="search_query"></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn">Add Source</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Run Pipeline</h2><span class="status">Mock adapters</span></div>
    <form method="post" action="/harvesters/run" class="form-card">
      <label>Source <select name="source_id"><option value="">All Active Sources</option><?php foreach ($sources as $source): ?><option value="<?= $source['id'] ?>"><?= htmlspecialchars($source['name']) ?></option><?php endforeach; ?></select></label>
      <button class="btn">Run Harvesters</button>
    </form>
    <form method="post" action="/harvesters/process" class="form-card">
      <button class="btn secondary">Review Source Items</button>
    </form>
    <hr>
    <h2>CSV Import</h2>
    <p class="hint">CSV columns: title, description, source_type, source_url, company_name, contact_name, phone, email, city, state, notes.</p>
    <form method="post" action="/harvesters/import-csv" enctype="multipart/form-data" class="form-card">
      <label>Source <select name="source_id"><?php foreach ($sources as $source): ?><option value="<?= $source['id'] ?>"><?= htmlspecialchars($source['name']) ?></option><?php endforeach; ?></select></label>
      <label>CSV <input type="file" name="csv" accept=".csv"></label>
      <button class="btn secondary">Import CSV</button>
    </form>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Top Source Types</h2>
    <div class="gap-list"><?php foreach ($metrics['top_sources'] as $row): ?><div><span><?= htmlspecialchars($row['source_type']) ?></span><strong><?= (int)$row['count'] ?></strong></div><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <h2>Top Theaters by Harvested Signals</h2>
    <div class="gap-list"><?php foreach ($metrics['top_regions'] as $row): ?><div><span><?= htmlspecialchars($row['region_name'] ?: 'National') ?></span><strong><?= (int)$row['count'] ?></strong></div><?php endforeach; ?></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Sources</h2><span class="status"><?= count($sources) ?> total</span></div>
  <div class="table-wrap"><table><thead><tr><th>Name</th><th>Source Type</th><th>Theater</th><th>Target</th><th>Collection</th><th>Frequency</th><th>Status</th><th>Last Run</th><th>Created</th></tr></thead><tbody><?php foreach ($sources as $source): ?><tr><td><strong><?= htmlspecialchars($source['name']) ?></strong><br><small><?= htmlspecialchars($source['search_query']) ?></small></td><td><?= htmlspecialchars($source['source_type']) ?></td><td><?= htmlspecialchars($source['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($source['target_category']) ?></td><td><?= htmlspecialchars($source['collection_method']) ?></td><td><?= htmlspecialchars($source['frequency']) ?></td><td><?= htmlspecialchars($source['status']) ?></td><td><?= htmlspecialchars($source['last_run_at']) ?></td><td><?= (int)$source['records_created_last_run'] ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Last Harvester Runs</h2>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Source</th><th>Found</th><th>Created</th><th>Errors</th><th>Summary</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><?= htmlspecialchars($run['status']) ?></td><td><?= htmlspecialchars($run['source_name']) ?><br><small><?= htmlspecialchars($run['source_type']) ?></small></td><td><?= (int)$run['records_found'] ?></td><td><?= (int)$run['records_created'] ?></td><td><?= (int)$run['errors_count'] ?></td><td><?= htmlspecialchars($run['summary']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <h2>Source Item Queue</h2>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Source Item</th><th>Source</th><th>Location</th></tr></thead><tbody><?php foreach ($rawItems as $item): ?><tr><td><?= htmlspecialchars($item['processing_status']) ?></td><td><strong><?= htmlspecialchars($item['raw_title']) ?></strong><br><small><?= htmlspecialchars($item['raw_company_name']) ?></small></td><td><?= htmlspecialchars($item['source_name']) ?></td><td><?= htmlspecialchars(trim(($item['raw_city'] ?? '') . ' ' . ($item['raw_state'] ?? ''))) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
