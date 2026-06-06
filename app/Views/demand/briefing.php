<section class="page-header">
  <p class="eyebrow">Daily Demand Briefing</p>
  <h1>What should Jackson publish, share, or participate in?</h1>
  <p>Recommended publications and channel participation only. Human review is required before anything is published.</p>
</section>

<section class="metrics">
  <div><span>Content Opportunities</span><strong><?= count($topContent) ?></strong></div>
  <div><span>Demand Signals</span><strong><?= count($topDemand) ?></strong></div>
  <div><span>Top Channels</span><strong><?= count($topChannels) ?></strong></div>
  <div><span>Distribution Queue</span><strong><?= count($distribution) ?></strong></div>
  <div><span>Needs Review</span><strong><?= count($review) ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Content Opportunities</h2><a class="btn secondary" href="/demand">Demand Engine</a></div>
    <div class="action-stack"><?php foreach ($topContent as $item): ?><article><span class="priority high"><?= htmlspecialchars($item['content_type']) ?></span><h3><?= htmlspecialchars($item['title']) ?></h3><p><?= htmlspecialchars($item['notes'] ?? '') ?></p><small><?= htmlspecialchars($item['audience']) ?> · <?= htmlspecialchars($item['region_name'] ?? 'National') ?> · Strategic <?= (int)$item['strategic_value'] ?></small></article><?php endforeach; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Demand Signals</h2><span class="status">Search intent plus acquisition value</span></div>
    <div class="table-wrap"><table><thead><tr><th>Topic</th><th>Trend</th><th>Audience</th><th>Suggested Content</th></tr></thead><tbody><?php foreach ($topDemand as $signal): ?><tr><td><?= htmlspecialchars($signal['topic']) ?><br><small><?= htmlspecialchars($signal['region_name'] ?? 'National') ?></small></td><td><?= (int)$signal['demand_score'] ?> · <?= htmlspecialchars($signal['trend_direction']) ?></td><td><?= htmlspecialchars($signal['audience']) ?></td><td><?= htmlspecialchars($signal['suggested_content'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Channels</h2><span class="status">Prioritize elite channels</span></div>
    <div class="table-wrap"><table><thead><tr><th>Channel</th><th>Audience</th><th>Quality</th><th>Region</th></tr></thead><tbody><?php foreach ($topChannels as $channel): ?><tr><td><?= htmlspecialchars($channel['channel_name']) ?><br><small><?= htmlspecialchars($channel['channel_type']) ?></small></td><td><?= htmlspecialchars($channel['audience_type']) ?></td><td><?= (int)$channel['quality_score'] ?><br><small><?= htmlspecialchars($channel['quality_category']) ?></small></td><td><?= htmlspecialchars($channel['region_name'] ?? 'National') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Distribution Opportunities</h2><span class="status">Planned, not automated</span></div>
    <div class="table-wrap"><table><thead><tr><th>Content</th><th>Channel</th><th>Match</th><th>Status</th></tr></thead><tbody><?php foreach ($distribution as $plan): ?><tr><td><?= htmlspecialchars($plan['content_title']) ?></td><td><?= htmlspecialchars($plan['channel_name']) ?><br><small><?= htmlspecialchars($plan['channel_type']) ?></small></td><td><?= (int)$plan['audience_match_score'] ?><br><small><?= htmlspecialchars($plan['priority']) ?></small></td><td><?= htmlspecialchars($plan['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Content Needing Review</h2><span class="status">Human approval gate</span></div>
    <div class="action-stack"><?php foreach ($review as $draft): ?><article><span class="priority medium"><?= htmlspecialchars($draft['review_status']) ?></span><h3><?= htmlspecialchars($draft['draft_title']) ?></h3><p><?= htmlspecialchars($draft['draft_summary'] ?? '') ?></p><small><?= htmlspecialchars($draft['audience']) ?></small></article><?php endforeach; ?><?php if (!$review): ?><p>No content currently needs review.</p><?php endif; ?></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recommended Publications & Participation</h2><span class="status">Actions only</span></div>
    <div class="action-stack"><?php foreach ($actions as $action): ?><article><span class="priority <?= strtolower($action['priority']) ?>"><?= htmlspecialchars($action['priority']) ?></span><h3><?= htmlspecialchars($action['title']) ?></h3><p><?= htmlspecialchars($action['recommended_next_action']) ?></p><small><?= htmlspecialchars($action['region_name'] ?? 'National') ?> · <?= htmlspecialchars($action['assigned_owner']) ?></small></article><?php endforeach; ?><?php if (!$actions): ?><p>No demand recommendations currently open.</p><?php endif; ?></div>
  </div>
</section>
