<section class="page-header command-page-header">
  <p class="eyebrow">Communication Workspace</p>
  <h1>Capture every meaningful conversation.</h1>
  <p>Add notes, log calls and meetings, create follow-ups, and draft human-reviewed email, LinkedIn, and text messages. No automated sending happens here.</p>
</section>

<nav class="dash-tabs">
  <a href="/executive-os">Executive OS</a>
  <a class="active" href="/communications">Communications</a>
  <a href="/network-intelligence">Network Intelligence</a>
  <a href="/daily-brief">Executive Brief</a>
</nav>

<?php
$ownerOptions = (new \App\Services\OwnerModelService())->ownerOptions(false, true);
$why = 'Communication is how relationships, capacity, opportunities, hunts, and targets move from intelligence into action.';
$recommended = 'Log the call, meeting, note, follow-up, or draft while the context is fresh.';
$next = 'Record the outcome and next step, then connect it to the relationship, opportunity, capacity profile, subcontractor, hunt, or target.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="grid two">
  <div class="panel">
    <h2>Add Communication</h2>
    <form class="form-grid" method="post" action="/communications">
      <input type="hidden" name="return_to" value="/communications">
      <label>Type<select name="communication_type"><option>Note</option><option>Call</option><option>Meeting</option><option>Follow-Up</option><option>Email Draft</option><option>LinkedIn Draft</option><option>Text Draft</option><option>Relationship Action</option></select></label>
      <label>Supports<select name="linked_record_type"><option>Relationship</option><option>Opportunity</option><option>Capacity Profile</option><option>Subcontractor</option><option>Hunt</option><option>Acquisition Target</option></select></label>
      <label>Linked ID<input name="linked_record_id" type="number" min="0"></label>
      <label>Region<select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Contact<select name="contact_id"><option value="">None</option><?php foreach ($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>"><?= htmlspecialchars(trim($contact['first_name'] . ' ' . $contact['last_name'])) ?> - <?= htmlspecialchars($contact['organization_name'] ?? '') ?></option><?php endforeach; ?></select></label>
      <label>Organization<select name="organization_id"><option value="">None</option><?php foreach ($organizations as $org): ?><option value="<?= (int)$org['id'] ?>"><?= htmlspecialchars($org['name']) ?></option><?php endforeach; ?></select></label>
      <label>Owner<select name="owner"><?php foreach ($ownerOptions as $ownerOption): ?><option value="<?= htmlspecialchars($ownerOption['value']) ?>"><?= htmlspecialchars($ownerOption['label']) ?></option><?php endforeach; ?></select></label>
      <label>Date<input name="communication_date" type="date" value="<?= date('Y-m-d') ?>"></label>
      <label class="full">Summary<textarea name="summary" required></textarea></label>
      <label class="full">Outcome<textarea name="outcome"></textarea></label>
      <label class="full">Next Step<textarea name="next_step"></textarea></label>
      <label class="full">Draft Subject<input name="draft_subject"></label>
      <label class="full">Draft Body<textarea name="draft_body" placeholder="Draft only. Human review required before sending."></textarea></label>
      <button class="btn">Save Communication</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Unified Timeline</h2><span class="status">No Automated Sending</span></div>
    <div class="activity-list">
      <?php foreach ($communications as $item): ?>
        <div>
          <strong><?= htmlspecialchars($item['summary']) ?></strong>
          <span><?= htmlspecialchars(substr($item['communication_date'], 0, 10)) ?> · <?= htmlspecialchars($item['communication_type']) ?> · <?= htmlspecialchars($item['owner']) ?> · <?= htmlspecialchars($item['region_name'] ?? '') ?></span>
          <small><?= htmlspecialchars($item['outcome'] ?? '') ?> <?= htmlspecialchars($item['next_step'] ?? '') ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$communications): ?><p>No communication records yet.</p><?php endif; ?>
    </div>
  </div>
</section>
