<section class="page-header">
  <p class="eyebrow">Hunting List</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<nav class="dash-tabs">
  <a href="/targets/hunting">National Hunting List</a>
  <a href="/targets/hunting?region=southeast">Mike: Southeast</a>
  <a href="/targets/hunting?region=great-lakes">Ron: Great Lakes</a>
  <a href="/targets/hunting?region=southwest">Southwest</a>
  <a href="/targets">All Acquisition Targets</a>
</nav>

<section class="metrics">
  <div><span>Total Targets</span><strong><?= count($targets) ?></strong></div>
  <div><span>Critical</span><strong><?= count(array_filter($targets, fn($t) => $t['priority'] === 'Critical')) ?></strong></div>
  <div><span>Ready for Outreach</span><strong><?= count(array_filter($targets, fn($t) => $t['status'] === 'Ready for Outreach')) ?></strong></div>
  <div><span>Converted</span><strong><?= count(array_filter($targets, fn($t) => $t['status'] === 'Converted')) ?></strong></div>
  <div><span>Average Score</span><strong><?= $targets ? (int)round(array_sum(array_column($targets, 'acquisition_score')) / count($targets)) : 0 ?></strong></div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Build From Signals</h2><span class="status">Signal → Target</span></div>
  <form method="post" action="/targets/build"><button class="btn">Build Acquisition Targets</button></form>
</section>

<section class="panel">
  <div class="panel-title"><h2>Manual Target Entry</h2><span class="status">Use sparingly</span></div>
  <form method="post" action="/targets" class="form-grid">
    <label>Target Name <input name="target_name" required></label>
    <label>Target Type <select name="target_type"><?php foreach ($options['types'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Theater <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
    <label>Owner <select name="owner"><?php foreach ($options['owners'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>State <select name="state"><option></option><?php foreach ($options['states'] as $state): ?><option><?= htmlspecialchars($state) ?></option><?php endforeach; ?></select></label>
    <label>City <input name="city"></label>
    <label>Organization <input name="organization_name"></label>
    <label>Contact <input name="contact_name"></label>
    <label>Email <input name="email"></label>
    <label>Phone <input name="phone"></label>
    <label>Website <input name="website"></label>
    <label>Priority <select name="priority"><?php foreach ($options['priorities'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Status <select name="status"><?php foreach ($options['statuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
    <label>Next Due <input type="date" name="next_action_due_at"></label>
    <label class="full">Reason to Pursue <textarea name="reason_to_pursue"></textarea></label>
    <label class="full">Recommended Next Action <textarea name="recommended_next_action"></textarea></label>
    <button class="btn">Add Target</button>
  </form>
</section>

<section class="panel">
  <div class="panel-title"><h2>Targets</h2><span class="status"><?= count($targets) ?> total</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Target</th><th>Type</th><th>Theater</th><th>Score</th><th>Priority</th><th>Reason to Pursue</th><th>Recommended Next Action</th><th>Status</th><th>Owner</th><th>Signal Source</th></tr></thead>
      <tbody>
      <?php foreach ($targets as $target): ?>
        <tr>
          <td><a href="/targets/detail?id=<?= (int)$target['id'] ?>"><strong><?= htmlspecialchars($target['target_name']) ?></strong></a><br><small><?= htmlspecialchars(trim(($target['city'] ?? '') . ' ' . ($target['state'] ?? ''))) ?></small></td>
          <td><?= htmlspecialchars($target['target_type']) ?></td>
          <td><?= htmlspecialchars($target['region_name'] ?? 'National') ?></td>
          <td><strong><?= (int)$target['acquisition_score'] ?></strong><br><small>Urgency <?= (int)$target['urgency_score'] ?></small></td>
          <td><span class="priority <?= strtolower($target['priority']) ?>"><?= htmlspecialchars($target['priority']) ?></span></td>
          <td><?= htmlspecialchars($target['reason_to_pursue']) ?></td>
          <td><?= htmlspecialchars($target['recommended_next_action']) ?></td>
          <td><?= htmlspecialchars($target['status']) ?></td>
          <td><?= htmlspecialchars($target['owner']) ?></td>
          <td><?= htmlspecialchars($target['signal_title'] ?? '') ?><br><small><?= htmlspecialchars($target['source_type']) ?></small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
