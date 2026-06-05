<section class="page-header">
  <p class="eyebrow">Signal Center</p>
  <h1>Signals become acquisition actions.</h1>
  <p>JAS is not a CRM. This layer captures capacity, opportunity, relationship, and market signals, scores them, routes them, and converts them into acquisition records.</p>
</section>

<section class="metrics signal-metrics">
  <div><span>New Signals Today</span><strong><?= $metrics['new_today'] ?></strong></div>
  <?php foreach (['Capacity','Opportunity','Relationship','Market','SEO','Content','Outreach'] as $type): ?>
    <?php $count = 0; foreach ($metrics['by_type'] as $row) { if ($row['signal_type'] === $type) $count = (int)$row['count']; } ?>
    <div><span><?= htmlspecialchars($type) ?> Signals</span><strong><?= $count ?></strong></div>
  <?php endforeach; ?>
</section>

<section class="grid two">
  <div class="panel">
    <h2>By Region</h2>
    <div class="gap-list">
      <?php foreach ($metrics['by_region'] as $row): ?>
        <div><span><?= htmlspecialchars($row['region_name']) ?></span><strong><?= (int)$row['count'] ?></strong></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h2>By Priority</h2>
    <div class="gap-list">
      <?php foreach (['Critical','High','Medium','Low'] as $priority): ?>
        <?php $count = 0; foreach ($metrics['by_priority'] as $row) { if ($row['priority'] === $priority) $count = (int)$row['count']; } ?>
        <div><span><?= htmlspecialchars($priority) ?></span><strong><?= $count ?></strong></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Capture Signal</h2><span class="status">Automatic scoring</span></div>
  <form method="post" action="/signals" class="form-grid">
    <label>Title <input name="title" required></label>
    <label>Type <select name="signal_type"><?php foreach ($options['types'] as $type): ?><option><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></label>
    <label>Source <select name="source_type"><?php foreach ($options['sources'] as $source): ?><option><?= htmlspecialchars($source) ?></option><?php endforeach; ?></select></label>
    <label>Region <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
    <label>State <select name="state"><option value="">Select</option><?php foreach ($options['states'] as $state): ?><option><?= htmlspecialchars($state) ?></option><?php endforeach; ?></select></label>
    <label>City <input name="city"></label>
    <label>Owner <select name="owner"><?php foreach ($options['owners'] as $owner): ?><option><?= htmlspecialchars($owner) ?></option><?php endforeach; ?></select></label>
    <label>Status <select name="status"><?php foreach ($options['statuses'] as $status): ?><option><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label>
    <label>Source URL <input name="source_url" type="url"></label>
    <label>Organization Name <input name="organization_name"></label>
    <label>Contact Name <input name="contact_name"></label>
    <label class="full">Description <textarea name="description"></textarea></label>
    <label class="full">Recommended Next Action <textarea name="recommended_next_action"></textarea></label>
    <label class="full">Notes <textarea name="notes"></textarea></label>
    <button class="btn">Add Signal</button>
  </form>
</section>

<section class="panel">
  <div class="panel-title"><h2>Signal Workflow</h2><span class="status">New → Reviewed → Assigned → Converted / Ignored</span></div>
  <div class="kanban">
    <?php foreach ($kanban as $status => $items): ?>
      <div class="kanban-column">
        <h3><?= htmlspecialchars($status) ?> <span><?= count($items) ?></span></h3>
        <?php foreach ($items as $signal): ?>
          <article class="signal-card">
            <div class="panel-title">
              <span class="priority <?= strtolower($signal['priority']) ?>"><?= htmlspecialchars($signal['priority']) ?></span>
              <small><?= (int)$signal['confidence_score'] ?> conf · <?= (int)$signal['impact_score'] ?> impact</small>
            </div>
            <h4><a href="/record?type=signal&id=<?= (int)$signal['id'] ?>"><?= htmlspecialchars($signal['title']) ?></a></h4>
            <p><?= htmlspecialchars($signal['signal_type']) ?> · <?= htmlspecialchars($signal['source_type']) ?></p>
          <small><?= htmlspecialchars($signal['region_name']) ?><?= $signal['state'] ? ' · ' . htmlspecialchars($signal['state']) : '' ?><?= $signal['city'] ? ' · ' . htmlspecialchars($signal['city']) : '' ?> · <?= htmlspecialchars($signal['owner']) ?></small>
            <form method="post" action="/signals/status" class="inline-form">
              <input type="hidden" name="id" value="<?= (int)$signal['id'] ?>">
              <select name="status"><?php foreach ($options['statuses'] as $next): ?><option <?= $next === $signal['status'] ? 'selected' : '' ?>><?= htmlspecialchars($next) ?></option><?php endforeach; ?></select>
              <button class="btn secondary">Move</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Signal Inventory</h2><span class="status"><?= count($signals) ?> total</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Signal</th><th>Type</th><th>Theater</th><th>Priority</th><th>Scores</th><th>Status</th><th>Owner</th></tr></thead>
      <tbody>
      <?php foreach ($signals as $signal): ?>
        <tr>
          <td><a href="/record?type=signal&id=<?= (int)$signal['id'] ?>"><?= (int)$signal['id'] ?></a></td>
          <td><strong><?= htmlspecialchars($signal['title']) ?></strong><br><small><?= htmlspecialchars($signal['source_type']) ?></small></td>
          <td><?= htmlspecialchars($signal['signal_type']) ?></td>
          <td><?= htmlspecialchars($signal['region_name']) ?><br><small><?= htmlspecialchars(trim(($signal['city'] ?? '') . ' ' . ($signal['state'] ?? ''))) ?></small></td>
          <td><span class="priority <?= strtolower($signal['priority']) ?>"><?= htmlspecialchars($signal['priority']) ?></span></td>
          <td><?= (int)$signal['confidence_score'] ?> / <?= (int)$signal['impact_score'] ?></td>
          <td><?= htmlspecialchars($signal['status']) ?></td>
          <td><?= htmlspecialchars($signal['owner']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
