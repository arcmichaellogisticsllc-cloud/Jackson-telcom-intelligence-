<section class="page-header">
  <p class="eyebrow"><?= htmlspecialchars($outreach['target_type']) ?> Outreach</p>
  <h1><?= htmlspecialchars($outreach['outreach_title']) ?></h1>
  <p><?= htmlspecialchars($outreach['reason']) ?></p>
</section>

<nav class="dash-tabs">
  <a href="/outreach">Outreach Queue</a>
  <a href="/decision-support">Decision Support</a>
  <a href="/targets">Acquisition Targets</a>
  <a href="/relationship-graph">Relationship Graph</a>
</nav>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Why This Exists</h2><span class="priority <?= strtolower($outreach['priority']) ?>"><?= htmlspecialchars($outreach['priority']) ?></span></div>
    <p><strong>Theater:</strong> <?= htmlspecialchars($outreach['region_name'] ?? 'National') ?></p>
    <p><strong>Owner:</strong> <?= htmlspecialchars($outreach['owner']) ?></p>
    <p><strong>Channel:</strong> <?= htmlspecialchars($outreach['channel']) ?></p>
    <p><strong>Goal:</strong> <?= htmlspecialchars($outreach['outreach_goal']) ?></p>
    <p><strong>Linked source:</strong> <?= htmlspecialchars($outreach['linked_record_type']) ?> #<?= (int)$outreach['linked_record_id'] ?></p>
    <p><strong>Desired outcome:</strong> <?= htmlspecialchars($outreach['desired_outcome']) ?></p>
  </div>
  <div class="panel">
    <h2>Recommended Opening</h2>
    <p><?= nl2br(htmlspecialchars($outreach['recommended_opening'])) ?></p>
    <h3>Discovery Questions</h3>
    <p><?= nl2br(htmlspecialchars($outreach['discovery_questions'])) ?></p>
  </div>
</section>

<section class="panel">
  <h2>Scripts</h2>
  <div class="table-wrap"><table><thead><tr><th>Type</th><th>Subject</th><th>Body</th><th>Review</th></tr></thead><tbody>
    <?php foreach ($scripts as $script): ?>
      <tr>
        <td><?= htmlspecialchars($script['script_type']) ?></td>
        <td><?= htmlspecialchars($script['subject_line']) ?></td>
        <td><pre><?= htmlspecialchars($script['body']) ?></pre></td>
        <td>
          <form method="post" action="/outreach/scripts/review" class="inline-form">
            <input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
            <input type="hidden" name="return_to" value="/outreach/detail?id=<?= (int)$outreach['id'] ?>">
            <select name="review_status"><option><?= htmlspecialchars($script['review_status']) ?></option><option>Needs Review</option><option>Approved</option><option>Rejected</option><option>Used</option></select>
            <button>Update</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Capture Outcome</h2>
    <form method="post" action="/outreach/outcome" class="form-grid">
      <input type="hidden" name="outreach_intelligence_id" value="<?= (int)$outreach['id'] ?>">
      <input type="hidden" name="return_to" value="/outreach/detail?id=<?= (int)$outreach['id'] ?>">
      <label>Outcome<select name="outcome_type"><option>No Answer</option><option>Left Message</option><option>Interested</option><option>Not Interested</option><option>Needs Follow-Up</option><option>Meeting Scheduled</option><option>Documents Requested</option><option>Converted</option><option>Bad Data</option></select></label>
      <label>Follow-Up Date<input type="date" name="follow_up_date"></label>
      <label class="full">Outcome Notes<textarea name="outcome_notes" rows="4"></textarea></label>
      <button>Save Outcome</button>
    </form>
  </div>
  <div class="panel">
    <h2>Outcome History</h2>
    <div class="activity-list"><?php foreach ($outcomes as $outcome): ?><div><strong><?= htmlspecialchars($outcome['outcome_type']) ?></strong><span><?= htmlspecialchars($outcome['created_at']) ?> · <?= htmlspecialchars($outcome['created_by']) ?></span><p><?= htmlspecialchars($outcome['outcome_notes']) ?></p></div><?php endforeach; ?><?php if (!$outcomes): ?><p>No outcomes captured yet.</p><?php endif; ?></div>
  </div>
</section>

<section class="panel">
  <h2>Activity</h2>
  <div class="activity-list"><?php foreach ($activities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars($activity['activity_date']) ?> · <?= htmlspecialchars($activity['owner']) ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div><?php endforeach; ?><?php if (!$activities): ?><p>No outreach activity recorded yet.</p><?php endif; ?></div>
</section>
