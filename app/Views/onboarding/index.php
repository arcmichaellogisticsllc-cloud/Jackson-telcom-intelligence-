<?php
$section = $section ?? 'overview';
$tabs = [
  'overview' => ['/onboarding', 'Overview'],
  'subcontractors' => ['/onboarding/subcontractors', 'Subcontractors'],
  'workforce' => ['/onboarding/workforce', 'Workforce'],
  'accounts' => ['/onboarding/strategic-accounts', 'Strategic Accounts'],
  'markets' => ['/onboarding/markets', 'Markets'],
  'documents' => ['/onboarding/documents', 'Documents'],
  'reviews' => ['/onboarding/reviews', 'Reviews'],
  'metrics' => ['/onboarding/metrics', 'Metrics'],
];
$csrf = \App\Core\Auth::csrfInput();
?>

<section class="page-header command-page-header">
  <p class="eyebrow">Onboarding Workspace</p>
  <h1>Convert intelligence into operational readiness.</h1>
  <p>Subcontractors, workforce, strategic accounts, and markets move from discovery through qualification into readiness.</p>
</section>

<nav class="dash-tabs">
  <?php foreach ($tabs as $key => [$href, $label]): ?><a class="<?= $section === $key ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a><?php endforeach; ?>
  <form method="post" action="/onboarding/rebuild"><?= $csrf ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Readiness</button></form>
</nav>

<?php
$why = 'Onboarding is the missing workflow between discovery, qualification, and operational readiness.';
$recommended = 'Work the highest-score items with missing documents, pending reviews, or readiness risks.';
$next = 'Pick one onboarding action, complete the missing review or document, and log the outcome.';
$risk = 'Without onboarding discipline, intelligence never becomes approved capacity, strategic account coverage, workforce bench, or market readiness.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <?php foreach ($metrics as $label => $value): ?><div><span><?= htmlspecialchars($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?>
</section>

<?php if (in_array($section, ['overview','metrics'], true)): ?>
<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Onboarding Actions</h2><a class="btn secondary" href="/recommendations">Recommendations</a></div>
    <div class="action-stack">
      <?php foreach ($recommendations as $rec): ?><article>
        <span class="priority <?= strtolower($rec['priority']) ?>"><?= htmlspecialchars($rec['priority']) ?> · <?= (int)$rec['priority_score'] ?></span>
        <h3><?= htmlspecialchars($rec['title']) ?></h3>
        <p><?= htmlspecialchars($rec['recommended_next_action']) ?></p>
        <small><?= htmlspecialchars($rec['assigned_owner']) ?> · <?= htmlspecialchars($rec['region_name'] ?? 'National') ?></small>
      </article><?php endforeach; ?>
      <?php if (!$recommendations): ?><article class="empty-state"><h3>No onboarding actions waiting</h3><p>All readiness queues are currently clear.</p></article><?php endif; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Top Risks</h2><span class="status">Missing Readiness</span></div>
    <div class="command-items">
      <?php foreach (array_slice(array_merge($subcontractors, $markets, $accounts), 0, 8) as $item): ?>
        <?php if (!empty($item['risk_flags']) || !empty($item['missing_items'])): ?><div><strong><?= htmlspecialchars($item['company_name'] ?? $item['market'] ?? $item['account_name'] ?? 'Onboarding item') ?></strong><span><?= htmlspecialchars($item['risk_flags'] ?: $item['missing_items']) ?></span></div><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','subcontractors'], true)): ?>
<section class="panel">
  <div class="panel-title"><h2>New Capacity Being Created</h2><span class="status">Subcontractor Onboarding</span></div>
  <div class="table-wrap"><table><thead><tr><th>Subcontractor</th><th>Theater</th><th>Stage</th><th>Readiness</th><th>Missing / Risk</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($subcontractors as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['company_name'] ?: 'Subcontractor #' . $row['subcontractor_id']) ?></strong><br><small><?= (int)$row['available_crew_count'] ?> available / <?= (int)$row['crew_count'] ?> total crews</small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?><br><small><?= htmlspecialchars($row['assigned_owner'] ?? 'Admin') ?></small></td>
      <td><?= htmlspecialchars($row['onboarding_status']) ?></td>
      <td><?= (int)$row['onboarding_score'] ?><br><small><?= htmlspecialchars($row['readiness_category']) ?></small></td>
      <td><?= htmlspecialchars($row['missing_items'] ?: $row['risk_flags'] ?: 'Ready for review') ?></td>
      <td><form method="post" action="/onboarding/stage" class="inline-form"><?= $csrf ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><input type="hidden" name="onboarding_type" value="Subcontractor"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="status"><option>Qualified</option><option>Documents Requested</option><option>Compliance Review</option><option>Capacity Review</option><option>Approved</option><option>Preferred</option><option>Strategic Partner</option><option>Rejected</option></select><input name="notes" placeholder="Notes"><button class="btn secondary">Move</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','workforce'], true)): ?>
<section class="panel">
  <div class="panel-title"><h2>New Workforce Candidates</h2><span class="status">Workforce Onboarding</span></div>
  <div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Role</th><th>Theater</th><th>Readiness</th><th>Availability</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($workforce as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['name']) ?></strong><br><small><?= htmlspecialchars($row['current_company'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($row['role']) ?><br><small><?= htmlspecialchars($row['skills'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?><br><small><?= htmlspecialchars($row['market'] ?? '') ?></small></td>
      <td><?= (int)$row['onboarding_score'] ?><br><small><?= htmlspecialchars($row['readiness_category']) ?></small></td>
      <td><?= htmlspecialchars($row['availability'] ?? '') ?></td>
      <td><form method="post" action="/onboarding/stage" class="inline-form"><?= $csrf ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><input type="hidden" name="onboarding_type" value="Workforce"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="status"><option>Contacted</option><option>Interview</option><option>Evaluation</option><option>Offer</option><option>Accepted</option><option>Declined</option><option>Archived</option></select><input name="notes" placeholder="Notes"><button class="btn secondary">Move</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','accounts'], true)): ?>
<section class="panel">
  <div class="panel-title"><h2>New Strategic Accounts</h2><span class="status">Account Onboarding</span></div>
  <div class="table-wrap"><table><thead><tr><th>Account</th><th>Theater</th><th>Stage</th><th>Readiness</th><th>Missing / Next</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($accounts as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['account_name']) ?></strong><br><small><?= htmlspecialchars($row['account_type']) ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?><br><small><?= htmlspecialchars($row['account_owner'] ?? 'Admin') ?></small></td>
      <td><?= htmlspecialchars($row['onboarding_status']) ?></td>
      <td><?= (int)$row['account_readiness_score'] ?><br><small><?= htmlspecialchars($row['readiness_category']) ?></small></td>
      <td><?= htmlspecialchars($row['missing_items'] ?: $row['next_action'] ?: 'Ready for review') ?></td>
      <td><form method="post" action="/onboarding/stage" class="inline-form"><?= $csrf ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><input type="hidden" name="onboarding_type" value="Strategic Account"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="status"><option>Researching</option><option>Relationship Mapping</option><option>Influence Mapping</option><option>Opportunity Mapping</option><option>Owner Assigned</option><option>Active Strategic Account</option></select><input name="notes" placeholder="Notes"><button class="btn secondary">Move</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','markets'], true)): ?>
<section class="panel">
  <div class="panel-title"><h2>Markets In Development</h2><span class="status">Market Onboarding</span></div>
  <div class="table-wrap"><table><thead><tr><th>Market</th><th>Theater</th><th>Stage</th><th>Readiness</th><th>Maps / Gaps</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($markets as $row): ?><tr>
      <td><strong><?= htmlspecialchars($row['market']) ?></strong><br><small>Opportunity density <?= (int)$row['opportunity_density'] ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?><br><small><?= htmlspecialchars($row['assigned_owner'] ?? 'Admin') ?></small></td>
      <td><?= htmlspecialchars($row['onboarding_status']) ?></td>
      <td><?= (int)$row['market_readiness_score'] ?><br><small><?= htmlspecialchars($row['readiness_category']) ?></small></td>
      <td><?= htmlspecialchars($row['missing_items'] ?: $row['next_action'] ?: 'Ready for review') ?></td>
      <td><form method="post" action="/onboarding/stage" class="inline-form"><?= $csrf ?><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><input type="hidden" name="onboarding_type" value="Market"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><select name="status"><option>Researching</option><option>Utility Mapping</option><option>Prime Mapping</option><option>Capacity Mapping</option><option>Relationship Mapping</option><option>Market Ready</option></select><input name="notes" placeholder="Notes"><button class="btn secondary">Move</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','reviews'], true)): ?>
<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Onboarding Reviews</h2><span class="status">Compliance / Capacity / Relationship / Strategic</span></div>
    <form method="post" action="/onboarding/review" class="form-grid compact">
      <?= $csrf ?>
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <label>Type <select name="onboarding_type"><option>Subcontractor</option><option>Workforce</option><option>Strategic Account</option><option>Market</option></select></label>
      <label>Onboarding ID <input type="number" name="onboarding_id" required></label>
      <label>Review <select name="review_type"><option>Compliance Review</option><option>Capacity Review</option><option>Relationship Review</option><option>Strategic Review</option></select></label>
      <label>Status <select name="status"><option>Pending</option><option>Approved</option><option>Rejected</option><option>Needs Information</option></select></label>
      <label class="full">Notes <textarea name="review_notes"></textarea></label>
      <label class="full">Follow-Up Action <input name="follow_up_action"></label>
      <button class="btn">Save Review</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Overdue / Pending Reviews</h2><span class="status"><?= count($reviews) ?></span></div>
    <div class="table-wrap"><table><thead><tr><th>Review</th><th>Type</th><th>Status</th><th>Follow-Up</th></tr></thead><tbody><?php foreach ($reviews as $review): ?><tr><td>#<?= (int)$review['onboarding_id'] ?> · <?= htmlspecialchars($review['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($review['onboarding_type']) ?><br><small><?= htmlspecialchars($review['review_type']) ?></small></td><td><?= htmlspecialchars($review['status']) ?></td><td><?= htmlspecialchars($review['follow_up_action'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
<?php endif; ?>

<?php if (in_array($section, ['overview','documents'], true)): ?>
<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Document Registry</h2><span class="status">Missing Document Alerts</span></div>
    <form method="post" action="/onboarding/document" class="form-grid compact">
      <?= $csrf ?>
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
      <label>Type <select name="onboarding_type"><option>Subcontractor</option><option>Workforce</option><option>Strategic Account</option><option>Market</option></select></label>
      <label>Onboarding ID <input type="number" name="onboarding_id" required></label>
      <label>Document <select name="document_type"><option>W9</option><option>COI</option><option>NDA</option><option>MSA</option><option>Safety Program</option><option>Certifications</option><option>Coverage Maps</option><option>Workforce Documents</option><option>Other</option></select></label>
      <label>Status <select name="status"><option>Submitted</option><option>Requested</option><option>Approved</option><option>Expired</option><option>Rejected</option></select></label>
      <label>File Name <input name="file_name" required></label>
      <label>Expires <input type="date" name="expires_at"></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn">Add Document</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Documents</h2><span class="status"><?= count($documents) ?></span></div>
    <div class="table-wrap"><table><thead><tr><th>Document</th><th>Type</th><th>Status</th><th>Reviewed</th></tr></thead><tbody><?php foreach ($documents as $doc): ?><tr><td><strong><?= htmlspecialchars($doc['file_name']) ?></strong><br><small><?= htmlspecialchars($doc['region_name'] ?? 'National') ?></small></td><td><?= htmlspecialchars($doc['onboarding_type']) ?> #<?= (int)$doc['onboarding_id'] ?><br><small><?= htmlspecialchars($doc['document_type']) ?></small></td><td><?= htmlspecialchars($doc['status']) ?><br><small><?= htmlspecialchars($doc['expires_at'] ?? '') ?></small></td><td><?= htmlspecialchars($doc['reviewed_by'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
<?php endif; ?>
