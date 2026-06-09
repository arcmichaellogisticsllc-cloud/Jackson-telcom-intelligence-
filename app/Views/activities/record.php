<?php
$recordTitle = trim((string)($record['name'] ?? (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))));
$recordTitle = $recordTitle ?: $label . ' #' . $record['id'];
$regionName = 'National';
foreach ($regions as $region) {
  if ((int)($record['region_id'] ?? 0) === (int)$region['id']) {
    $regionName = $region['name'];
    break;
  }
}
$recordEyebrow = 'Record Workspace';
$recordName = $recordTitle;
$recordType = $label;
$recordRegion = $regionName;
$recordOwner = $record['owner'] ?? $record['relationship_owner'] ?? $_SESSION['user']['name'] ?? 'Unassigned';
$recordStatus = $record['status'] ?? $record['stage'] ?? $record['approval_stage'] ?? 'Open';
$recordScore = (int)($record['score'] ?? $record['impact_score'] ?? $record['acquisition_score'] ?? 0);
$recordNextAction = $record['next_action'] ?? $record['recommended_next_action'] ?? 'Review the record and create the next operator action.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Owner','Mark Reviewed'];
$recordEntityType = $type;
$recordEntityId = (int)$record['id'];
$recordRegionId = (int)($record['region_id'] ?? 0);
$timelineItems = [];
foreach ($activities as $activity) {
  $timelineItems[] = ['type' => $activity['activity_type'], 'title' => $activity['title'], 'why' => $activity['notes'] ?: 'This activity may affect the record status, owner, or next action.', 'next' => 'Review current status and log the next step.', 'owner' => $activity['owner'], 'date' => $activity['activity_date']];
}
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Tasks / Actions','Notes','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$what = 'This is a generic record workspace for records that do not yet have a specialized detail view.';
$why = 'This fallback view keeps activity, notes, and next actions visible without exposing operators to raw database structure first.';
$recommended = $recordNextAction;
$next = 'Use the record action buttons to log the next activity or assign ownership.';
$risk = 'If fallback records are not actionable, useful intelligence can get stranded outside the main operating workspaces.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="grid two">
  <div class="panel">
    <h2>Record Snapshot</h2>
    <div class="table-wrap"><table><tbody><?php foreach ($record as $key => $value): ?><tr><th><?= htmlspecialchars(str_replace('_', ' ', $key)) ?></th><td><?= htmlspecialchars((string)$value) ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <?php if ($type === 'signal'): ?>
      <h2>Convert Signal</h2>
      <p class="hint">Conversion creates a starter acquisition record from the signal, then marks the signal converted.</p>
      <div class="convert-grid">
        <?php
          $targets = match ($record['signal_type']) {
            'Capacity' => ['organization' => 'Organization', 'subcontractor' => 'Subcontractor'],
            'Relationship' => ['contact' => 'Contact', 'organization' => 'Organization'],
            'Opportunity' => ['opportunity' => 'Opportunity', 'organization' => 'Organization'],
            'Market', 'SEO', 'Content', 'Outreach' => ['opportunity' => 'Opportunity', 'intelligence' => 'Intelligence Record'],
            default => ['organization' => 'Organization'],
          };
        ?>
        <?php foreach ($targets as $target => $labelText): ?>
          <form method="post" action="/signals/convert">
            <input type="hidden" name="id" value="<?= (int)$record['id'] ?>">
            <input type="hidden" name="target" value="<?= htmlspecialchars($target) ?>">
            <button class="btn secondary">Convert to <?= htmlspecialchars($labelText) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
      <hr>
    <?php endif; ?>
    <h2>Add Note</h2>
    <form method="post" action="/activities" class="form-grid">
      <input type="hidden" name="entity_type" value="<?= htmlspecialchars($type) ?>">
      <input type="hidden" name="entity_id" value="<?= (int)$record['id'] ?>">
      <input type="hidden" name="return_to" value="/record?type=<?= htmlspecialchars($type) ?>&id=<?= (int)$record['id'] ?>">
      <label>Region <select name="region_id"><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>" <?= ($record['region_id'] ?? null) == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?></select></label>
      <label>Type <select name="activity_type"><option>Note</option><option>Call</option><option>Email</option><option>Meeting</option><option>Task</option><option>Status Change</option></select></label>
      <label>Title <input name="title" required></label>
      <label>Date <input type="date" name="activity_date" value="<?= date('Y-m-d') ?>"></label>
      <label>Owner <input name="owner" value="<?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?>"></label>
      <label class="full">Note <textarea name="notes"></textarea></label>
      <button class="btn">Add Activity</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="panel">
  <h2>Activity Timeline</h2>
  <div class="activity-list timeline">
    <?php foreach ($activities as $activity): ?>
      <div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner']) ?> · <?= htmlspecialchars($activity['region_name'] ?? '') ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div>
    <?php endforeach; ?>
    <?php if (!$activities): ?><?php $emptyTitle = 'No activities yet'; $emptyBody = 'Use Add Note, Log Call, Draft Email, or Create Follow-Up to create the first record action.'; $emptyActionHref = ''; $emptyActionLabel = ''; require __DIR__ . '/../components/empty_state.php'; ?><?php endif; ?>
  </div>
</section>
