<section class="page-header">
  <p class="eyebrow">Demand & Distribution Intelligence</p>
  <h1>Participate in the ecosystem.</h1>
  <p>Traffic creates signals, signals create content, content creates distribution, distribution creates relationships, capacity, opportunities, and more signals. Human review is required before publication.</p>
</section>

<section class="metrics">
  <div><span>Channels</span><strong><?= $metrics['channels'] ?></strong></div>
  <div><span>Demand Opportunities</span><strong><?= $metrics['opportunities'] ?></strong></div>
  <div><span>Content Awaiting Review</span><strong><?= $metrics['review'] ?></strong></div>
  <div><span>Distribution Queue</span><strong><?= $metrics['queue'] ?></strong></div>
  <div><span>Elite Channels</span><strong><?= $metrics['elite'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Channel Registry</h2><span class="status">Distribution sources</span></div>
    <form method="post" action="/demand/channels" class="form-grid compact">
      <label class="full">Channel Name <input name="channel_name" required></label>
      <label>Type <select name="channel_type"><?php foreach ($options['channelTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Audience <select name="audience_type"><?php foreach ($options['audiences'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><option value="">National</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Relationship Score <input type="number" name="relationship_generation_score" min="0" max="100" value="50"></label>
      <label>Capacity Score <input type="number" name="capacity_generation_score" min="0" max="100" value="50"></label>
      <label>Opportunity Score <input type="number" name="opportunity_generation_score" min="0" max="100" value="50"></label>
      <label>Status <select name="status"><?php foreach ($options['statuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn">Add Channel</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Demand Signal</h2><span class="status">SEO plus acquisition intent</span></div>
    <form method="post" action="/demand/signals" class="form-grid compact">
      <label class="full">Topic <input name="topic" required></label>
      <label>Demand Score <input type="number" name="demand_score" min="0" max="100" value="70"></label>
      <label>Trend <select name="trend_direction"><?php foreach ($options['trends'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><option value="">National</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Audience <select name="audience"><?php foreach ($options['audiences'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Suggested Content <textarea name="suggested_content"></textarea></label>
      <label class="full">Suggested Distribution <textarea name="suggested_distribution"></textarea></label>
      <button class="btn">Add Demand Signal</button>
    </form>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Content Opportunity</h2><span class="status">No auto-publishing</span></div>
  <form method="post" action="/demand/content" class="form-grid compact">
    <label class="full">Title <input name="title" required></label>
    <label>Type <select name="content_type"><?php foreach ($options['contentTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Audience <select name="audience"><?php foreach ($options['audiences'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Region <select name="region_id"><option value="">National</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
    <label>Source <input name="source_type" value="Manual"></label>
    <label>Strategic Value <input type="number" name="strategic_value" min="0" max="100" value="70"></label>
    <label>Capacity Impact <input type="number" name="expected_capacity_impact" min="0" max="100" value="50"></label>
    <label>Relationship Impact <input type="number" name="expected_relationship_impact" min="0" max="100" value="50"></label>
    <label>Opportunity Impact <input type="number" name="expected_opportunity_impact" min="0" max="100" value="50"></label>
    <label>Status <select name="status"><?php foreach ($options['contentStatuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label class="full">Notes <textarea name="notes"></textarea></label>
    <button class="btn">Add Content Opportunity</button>
  </form>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Channels</h2><a class="btn secondary" href="/demand-briefing">Demand Briefing</a></div>
    <div class="table-wrap"><table><thead><tr><th>Channel</th><th>Audience</th><th>Region</th><th>Quality</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($channels as $channel): ?><tr><td><?= htmlspecialchars($channel['channel_name']) ?><br><small><?= htmlspecialchars($channel['channel_type']) ?></small></td><td><?= htmlspecialchars($channel['audience_type']) ?></td><td><?= htmlspecialchars($channel['region_name'] ?? 'National') ?></td><td><strong><?= (int)$channel['quality_score'] ?></strong><br><small><?= htmlspecialchars($channel['quality_category']) ?></small></td><td><?= htmlspecialchars($channel['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Demand Signals</h2><span class="status">Intent, not rankings alone</span></div>
    <div class="table-wrap"><table><thead><tr><th>Topic</th><th>Audience</th><th>Region</th><th>Demand</th><th>Suggested Content</th></tr></thead><tbody>
      <?php foreach (array_slice($signals, 0, 12) as $signal): ?><tr><td><?= htmlspecialchars($signal['topic']) ?></td><td><?= htmlspecialchars($signal['audience']) ?></td><td><?= htmlspecialchars($signal['region_name'] ?? 'National') ?></td><td><?= (int)$signal['demand_score'] ?><br><small><?= htmlspecialchars($signal['trend_direction']) ?></small></td><td><?= htmlspecialchars($signal['suggested_content'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Content Opportunities</h2><span class="status"><?= count($content) ?> total</span></div>
  <div class="table-wrap"><table><thead><tr><th>Content</th><th>Audience</th><th>Region</th><th>Source</th><th>Strategic</th><th>Impacts</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($content as $item): ?><tr><td><?= htmlspecialchars($item['title']) ?><br><small><?= htmlspecialchars($item['content_type']) ?></small></td><td><?= htmlspecialchars($item['audience']) ?></td><td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($item['source_type'] ?? '') ?></td><td><?= (int)$item['strategic_value'] ?></td><td>C <?= (int)$item['expected_capacity_impact'] ?> · R <?= (int)$item['expected_relationship_impact'] ?> · O <?= (int)$item['expected_opportunity_impact'] ?></td><td><?= htmlspecialchars($item['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Content Needing Review</h2><span class="status">Human approval required</span></div>
    <div class="action-stack">
      <?php foreach (array_slice($drafts, 0, 10) as $draft): ?><article><span class="priority medium"><?= htmlspecialchars($draft['review_status']) ?></span><h3><?= htmlspecialchars($draft['draft_title']) ?></h3><p><?= htmlspecialchars($draft['draft_summary'] ?? '') ?></p><small><?= htmlspecialchars($draft['audience']) ?> · <?= htmlspecialchars($draft['region_name'] ?? 'National') ?></small><form method="post" action="/demand/drafts/review" class="inline-form"><input type="hidden" name="id" value="<?= (int)$draft['id'] ?>"><input type="hidden" name="return_to" value="/demand"><select name="review_status"><option>Review Needed</option><option>Approved</option><option>Rejected</option></select><button class="link-button">Update</button></form></article><?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Distribution Queue</h2><span class="status">Review before publishing</span></div>
    <div class="table-wrap"><table><thead><tr><th>Content</th><th>Channel</th><th>Match</th><th>Priority</th><th>Status</th></tr></thead><tbody>
      <?php foreach (array_slice($plans, 0, 14) as $plan): ?><tr><td><?= htmlspecialchars($plan['content_title']) ?></td><td><?= htmlspecialchars($plan['channel_name']) ?><br><small><?= htmlspecialchars($plan['channel_type']) ?></small></td><td><?= (int)$plan['audience_match_score'] ?></td><td><?= htmlspecialchars($plan['priority']) ?></td><td><form method="post" action="/demand/distribution/status" class="inline-form"><input type="hidden" name="id" value="<?= (int)$plan['id'] ?>"><input type="hidden" name="return_to" value="/demand"><select name="status"><option><?= htmlspecialchars($plan['status']) ?></option><option>Approved</option><option>Scheduled</option><option>Published</option><option>Skipped</option></select><button class="link-button">Save</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>
