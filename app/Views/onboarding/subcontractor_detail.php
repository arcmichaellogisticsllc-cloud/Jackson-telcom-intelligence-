<?php
$csrf = \App\Core\Auth::csrfInput();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$sub = $subcontractor;
$requiredDocs = ['W9','COI','MSA','NDA','Safety Program'];
$requiredReviews = ['Compliance Review','Capacity Review'];
$why = 'This page turns a discovered subcontractor into review-ready capacity without approving anything blindly.';
$recommended = $approvalGate['canApprove']
    ? 'All gates are clear. Approve the subcontractor only if the operator accepts the documented risk.'
    : 'Clear the blockers before approving this subcontractor for deployable capacity.';
$next = $approvalGate['canApprove']
    ? 'Choose Approved, Preferred, or Strategic Partner and record the approval reason.'
    : 'Review submitted documents, complete compliance/capacity reviews, and capture any follow-up actions.';
$risk = 'If this workflow is skipped, unverified crews can enter the capacity picture and create execution risk.';
$latestDocumentFor = function (array $documents, string $docType): ?array {
    $matches = array_values(array_filter($documents, fn($doc) => $doc['document_type'] === $docType));
    usort($matches, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')) ?: ((int)$b['id'] <=> (int)$a['id']));
    return $matches[0] ?? null;
};
?>

<section class="page-header command-page-header">
  <p class="eyebrow">Subcontractor Onboarding</p>
  <h1><?= htmlspecialchars($sub['company_name'] ?: 'Subcontractor #' . $sub['subcontractor_id']) ?></h1>
  <p><?= htmlspecialchars($sub['region_name'] ?? 'National') ?> · <?= htmlspecialchars($sub['onboarding_status']) ?> · <?= (int)$sub['available_crew_count'] ?> available / <?= (int)$sub['crew_count'] ?> total crews</p>
  <div class="record-actions">
    <a class="btn secondary" href="/onboarding/subcontractors">Back To Onboarding</a>
    <form method="post" action="/onboarding/intake-link" class="inline-form">
      <?= $csrf ?>
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="onboarding_id" value="<?= (int)$sub['onboarding_id'] ?>">
      <input type="hidden" name="expires_days" value="14">
      <button class="btn secondary">Generate Intake Link</button>
    </form>
  </div>
</section>

<?php if ($flash): ?>
<section class="panel"><strong>Workflow Notice</strong><p><?= htmlspecialchars($flash) ?></p></section>
<?php endif; ?>

<?php require __DIR__ . '/../components/action_first.php'; ?>

<section class="grid four">
  <div class="panel"><span class="eyebrow">Readiness</span><h2><?= (int)$sub['onboarding_score'] ?></h2><p><?= htmlspecialchars($sub['readiness_category']) ?></p></div>
  <div class="panel"><span class="eyebrow">Stage</span><h2><?= htmlspecialchars($sub['onboarding_status']) ?></h2><p><?= htmlspecialchars($sub['assigned_owner'] ?? 'Admin') ?></p></div>
  <div class="panel"><span class="eyebrow">Approval Gate</span><h2><?= $approvalGate['canApprove'] ? 'Clear' : 'Blocked' ?></h2><p><?= $approvalGate['canApprove'] ? 'Ready for approval decision.' : count($approvalGate['blockers']) . ' blocker(s)' ?></p></div>
  <div class="panel"><span class="eyebrow">Coverage</span><h2><?= htmlspecialchars($sub['coverage_area'] ?: $sub['states_served'] ?: 'Not Set') ?></h2><p><?= htmlspecialchars($sub['markets_served'] ?: 'Market not verified') ?></p></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Approval Gate</h2><span class="status"><?= $approvalGate['canApprove'] ? 'Ready' : 'Blocked' ?></span></div>
    <?php if (!$approvalGate['canApprove']): ?>
      <div class="command-items">
        <?php foreach ($approvalGate['blockers'] as $blocker): ?><div><strong><?= htmlspecialchars($blocker) ?></strong><span>Resolve before approving this subcontractor.</span></div><?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>Required documents and reviews are approved. Confirm risk acceptance before moving this subcontractor into deployable capacity.</p>
    <?php endif; ?>
	    <form method="post" action="/onboarding/stage" class="form-grid compact">
      <?= $csrf ?>
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="onboarding_type" value="Subcontractor">
      <input type="hidden" name="id" value="<?= (int)$sub['onboarding_id'] ?>">
      <label>Approval Decision
	        <select name="status">
	          <option>Compliance Review</option>
	          <option>Capacity Review</option>
	          <option <?= !$approvalGate['canApprove'] ? 'disabled' : '' ?>>Approved</option>
	          <option <?= !$approvalGate['canApprove'] ? 'disabled' : '' ?>>Preferred</option>
	          <option <?= !$approvalGate['canApprove'] ? 'disabled' : '' ?>>Strategic Partner</option>
	          <option>Rejected</option>
	        </select>
      </label>
      <label class="full">Decision Notes <textarea name="notes" placeholder="Why this decision is being made, what risk remains, and who owns next step."></textarea></label>
      <button class="btn">Save Decision</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Capacity Snapshot</h2><span class="status">Operator Review</span></div>
    <dl class="detail-list">
      <dt>Services / Disciplines</dt><dd><?= htmlspecialchars($sub['disciplines'] ?: $sub['services_offered'] ?: 'Not verified') ?></dd>
      <dt>Crew Counts</dt><dd><?= htmlspecialchars($sub['crew_counts'] ?: ((int)$sub['available_crew_count'] . ' available / ' . (int)$sub['crew_count'] . ' total')) ?></dd>
      <dt>Equipment</dt><dd><?= htmlspecialchars($sub['equipment_counts'] ?: 'Not verified') ?></dd>
      <dt>Primary Contact</dt><dd><?= htmlspecialchars($sub['primary_contact'] ?: 'Not provided') ?><?= $sub['phone'] ? ' · ' . htmlspecialchars($sub['phone']) : '' ?><?= $sub['email'] ? ' · ' . htmlspecialchars($sub['email']) : '' ?></dd>
      <dt>Risk Flags</dt><dd><?= htmlspecialchars($sub['risk_flags'] ?: 'No current risk flags') ?></dd>
    </dl>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Documents</h2><span class="status">Required Gate</span></div>
    <div class="table-wrap"><table><thead><tr><th>Document</th><th>Current Gate</th><th>Latest Record</th><th>Action</th></tr></thead><tbody>
	      <?php foreach ($requiredDocs as $docType): ?>
	        <?php $latest = $latestDocumentFor($documents, $docType); ?>
        <tr>
          <td><strong><?= htmlspecialchars($docType) ?></strong></td>
          <td><?= htmlspecialchars($approvalGate['documents'][$docType] ?? 'Missing') ?></td>
          <td><?= $latest ? htmlspecialchars($latest['file_name']) . '<br><small>' . htmlspecialchars($latest['status']) . '</small>' : 'No record yet' ?></td>
          <td>
            <form method="post" action="/onboarding/document" class="inline-form">
              <?= $csrf ?>
              <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
              <input type="hidden" name="onboarding_type" value="Subcontractor">
              <input type="hidden" name="onboarding_id" value="<?= (int)$sub['onboarding_id'] ?>">
              <input type="hidden" name="document_type" value="<?= htmlspecialchars($docType) ?>">
	              <input name="file_name" value="<?= htmlspecialchars(($latest['file_name'] ?? '')) ?>" placeholder="received-w9.pdf or source reference" required>
	              <select name="status"><option>Approved</option><option>Submitted</option><option>Requested</option><option>Rejected</option><option>Expired</option></select>
              <input name="notes" placeholder="Review notes">
              <button class="btn secondary">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Reviews</h2><span class="status">Required Gate</span></div>
    <div class="table-wrap"><table><thead><tr><th>Review</th><th>Gate</th><th>Latest Notes</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($requiredReviews as $reviewType): ?>
        <?php $latest = array_values(array_filter($reviews, fn($review) => $review['review_type'] === $reviewType))[0] ?? null; ?>
        <tr>
          <td><strong><?= htmlspecialchars($reviewType) ?></strong></td>
          <td><?= htmlspecialchars($approvalGate['reviews'][$reviewType] ?? 'Pending') ?></td>
          <td><?= $latest ? htmlspecialchars($latest['review_notes'] ?: $latest['status']) : 'No review yet' ?></td>
          <td>
            <form method="post" action="/onboarding/review" class="inline-form">
              <?= $csrf ?>
              <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
              <input type="hidden" name="onboarding_type" value="Subcontractor">
              <input type="hidden" name="onboarding_id" value="<?= (int)$sub['onboarding_id'] ?>">
              <input type="hidden" name="review_type" value="<?= htmlspecialchars($reviewType) ?>">
              <select name="status"><option>Approved</option><option>Needs Information</option><option>Rejected</option><option>Pending</option></select>
              <input name="review_notes" placeholder="Review notes">
              <input name="follow_up_action" placeholder="Follow-up action">
              <button class="btn secondary">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Open Actions</h2><span class="status"><?= count($actions) ?></span></div>
    <div class="command-items">
      <?php foreach ($actions as $action): ?><div><strong><?= htmlspecialchars($action['action_title']) ?></strong><span><?= htmlspecialchars($action['recommended_next_step'] ?: $action['reason']) ?></span></div><?php endforeach; ?>
      <?php if (!$actions): ?><div class="empty-state"><strong>No open actions</strong><span>Create follow-up actions from reviews when the subcontractor needs to provide more information.</span></div><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Recent Intake Links</h2><span class="status">Manual Send Only</span></div>
    <div class="table-wrap"><table><thead><tr><th>Status</th><th>Requested</th><th>Expires</th></tr></thead><tbody>
      <?php foreach ($intakeLinks as $link): ?><tr><td><?= htmlspecialchars($link['status']) ?></td><td><?= htmlspecialchars($link['requested_by'] ?? 'Admin') ?><br><small><?= htmlspecialchars($link['created_at']) ?></small></td><td><?= htmlspecialchars($link['expires_at']) ?></td></tr><?php endforeach; ?>
      <?php if (!$intakeLinks): ?><tr><td colspan="3">No intake links have been generated.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Timeline</h2><span class="status">Onboarding Evidence</span></div>
  <div class="timeline">
    <?php foreach ($activities as $activity): ?>
      <article>
        <strong><?= htmlspecialchars($activity['title']) ?></strong>
        <span><?= htmlspecialchars($activity['activity_type']) ?> · <?= htmlspecialchars($activity['owner'] ?? 'Admin') ?> · <?= htmlspecialchars($activity['activity_date']) ?></span>
        <p><?= nl2br(htmlspecialchars($activity['notes'] ?? '')) ?></p>
      </article>
    <?php endforeach; ?>
    <?php if (!$activities): ?><div class="empty-state"><strong>No timeline yet</strong><span>Actions, documents, reviews, and decisions will appear here.</span></div><?php endif; ?>
  </div>
</section>
