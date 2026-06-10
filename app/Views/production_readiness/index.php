<section class="page-header command-page-header">
  <p class="eyebrow">Operational Control / Production Readiness</p>
  <h1>Operational-ready and production-safe</h1>
  <p>Authorization, security hardening, data review, connector readiness, operator feedback, action tuning, deployment readiness, and SyncERP contract validation.</p>
</section>

<nav class="dash-tabs">
  <a href="#authorization">Authorization</a>
  <a href="#data-review">Data Review</a>
  <a href="#quality-issues">Quality Issues</a>
  <a href="#connector">Connector</a>
  <a href="#audit">Audit</a>
  <a href="#feedback">Operator Feedback</a>
  <a href="#tuning">Action Tuning</a>
  <a href="#deployment">Deployment</a>
  <a href="#syncerp-contract">SyncERP Contract</a>
</nav>

<?php
$why = 'This page controls the live safeguards that keep real operators, real data, and real handoffs trustworthy.';
$recommended = 'Start with open Critical/High data review items, then tune noisy actions and validate SyncERP handoff fields.';
$next = 'Resolve one data review item, record one operator feedback item, and validate one SyncERP contract field during each weekly readiness review.';
$risk = 'Without this layer, bad data, weak permissions, noisy actions, or unclear handoff fields can damage operator trust.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Open Reviews</span><strong><?= (int)$metrics['open_reviews'] ?></strong></div>
  <div><span>Critical Reviews</span><strong><?= (int)$metrics['critical_reviews'] ?></strong></div>
  <div><span>Quality Issues</span><strong><?= (int)$metrics['data_quality_issues'] ?></strong></div>
  <div><span>Operator Feedback</span><strong><?= (int)$metrics['pilot_feedback'] ?></strong></div>
  <div><span>Connector Reviews</span><strong><?= (int)$metrics['connector_runs_pending_review'] ?></strong></div>
  <div><span>Contract Pending</span><strong><?= (int)$metrics['contract_pending'] ?></strong></div>
  <div><span>CSRF / Session</span><strong>On</strong></div>
</section>

<section id="authorization" class="panel">
  <div class="panel-title"><h2>Authorization / Security Hardening</h2><span class="status">Active</span></div>
  <div class="command-items">
    <div><strong>Route Protection</strong><span>Authenticated routes require login; POST routes require CSRF tokens.</span></div>
    <div><strong>Server-Side Region Access</strong><span>Direct regional URLs and mapped detail records are checked against role, ownership, and regional scope; unauthorized attempts return 403 and write audit logs.</span></div>
    <div><strong>Read-Only Role</strong><span>Viewer accounts are blocked from POST workflows server-side, not only hidden in the UI.</span></div>
    <div><strong>Session Hardening</strong><span>Session timeout, CSRF rotation on login, secure headers, HTTP-only same-site cookies, login throttling, and password reset token foundation.</span></div>
  </div>
</section>

<section id="data-review" class="panel">
  <div class="panel-title"><h2>Data Review Queue</h2><span class="status">Human Review</span></div>
  <div class="table-wrap"><table><thead><tr><th>Issue</th><th>Type</th><th>Theater</th><th>Severity</th><th>Owner</th><th>Resolution</th></tr></thead><tbody>
    <?php foreach ($reviewItems as $item): ?><tr>
      <td><strong><?= htmlspecialchars($item['title']) ?></strong><br><small><?= htmlspecialchars($item['issue_summary'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($item['review_type']) ?><br><small><?= htmlspecialchars($item['linked_record_type'] ?? '') ?> #<?= (int)$item['linked_record_id'] ?></small></td>
      <td><?= htmlspecialchars($item['region_name'] ?? 'National') ?></td>
      <td><span class="priority <?= strtolower($item['severity']) ?>"><?= htmlspecialchars($item['severity']) ?></span></td>
      <td><?= htmlspecialchars($item['assigned_owner'] ?? 'Admin') ?></td>
      <td><form method="post" action="/production-readiness/review" class="inline-form"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><select name="status"><option>In Review</option><option>Resolved</option><option>Dismissed</option></select><input name="resolution_notes" placeholder="Resolution notes"><button class="btn secondary">Update</button></form></td>
    </tr><?php endforeach; ?>
    <?php if (!$reviewItems): ?><tr><td colspan="6">No open data review items.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section id="quality-issues" class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Data Quality Review</h2><span class="status">Operator-Owned</span></div>
    <form method="post" action="/production-readiness/data-quality" class="form-grid compact">
      <label>Issue Type <select name="issue_type"><option>Duplicate Entity</option><option>Missing Contact Info</option><option>Bad Import</option><option>Low Confidence Signal</option><option>Disputed Classification</option><option>Source Reliability Concern</option><option>Stale Contact</option><option>Conflicting Data</option><option>Missing Region</option><option>Missing Owner</option><option>Other</option></select></label>
      <label>Theater <select name="region_id"><option value="">National / Shared</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Severity <select name="severity"><option>Medium</option><option>Low</option><option>High</option><option>Critical</option></select></label>
      <label>Assigned Owner <input name="assigned_owner" value="<?= htmlspecialchars($user['name'] ?? 'Admin') ?>"></label>
      <label>Record Type <input name="linked_record_type" placeholder="contact"></label>
      <label>Record ID <input type="number" name="linked_record_id"></label>
      <label class="full">Title <input name="title" required></label>
      <label class="full">Description <textarea name="description"></textarea></label>
      <button class="btn">Create Quality Issue</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Open Quality Issues</h2><span class="status">Resolve Before Trust</span></div>
    <div class="table-wrap"><table><thead><tr><th>Issue</th><th>Type</th><th>Theater</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($dataQualityIssues as $issue): ?><tr>
        <td><strong><?= htmlspecialchars($issue['title']) ?></strong><br><small><?= htmlspecialchars($issue['description'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($issue['issue_type']) ?><br><span class="priority <?= strtolower($issue['severity']) ?>"><?= htmlspecialchars($issue['severity']) ?></span></td>
        <td><?= htmlspecialchars($issue['region_name'] ?? 'National') ?></td>
        <td>
          <form method="post" action="/production-readiness/data-quality/update" class="inline-form stacked-inline">
            <input type="hidden" name="id" value="<?= (int)$issue['id'] ?>">
            <select name="status"><option>In Review</option><option>Resolved</option><option>Dismissed</option></select>
            <input name="correction_owner" placeholder="Owner">
            <input name="correction_region" placeholder="Theater">
            <input name="correction_email" placeholder="Business email">
            <input name="correction_phone" placeholder="Business phone">
            <input name="correction_website" placeholder="Website">
            <input name="correction_status" placeholder="Status">
            <input name="correction_market" placeholder="Market">
            <input name="correction_classification" placeholder="Signal classification">
            <input name="correction_recommended_action" placeholder="Recommended action">
            <input name="correction_notes" placeholder="Correction note">
            <input name="resolution_outcome" placeholder="Outcome summary">
            <input name="resolution_notes" placeholder="Resolution notes">
            <button class="btn secondary">Apply</button>
          </form>
        </td>
      </tr><?php endforeach; ?>
      <?php if (!$dataQualityIssues): ?><tr><td colspan="4">No open data quality issues.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section id="connector" class="panel">
  <div class="panel-title"><h2>First Real Connector Path</h2><span class="status">Review-Gated</span></div>
  <p>The connector framework supports an opt-in RSS/static-source path and a CSV/source-file fallback. Connector rows write to review-gated source items as <strong>Needs Review</strong> and never bypass signal quality.</p>
  <div class="grid two">
    <div class="table-wrap"><table><thead><tr><th>Connector</th><th>Status</th><th>Imported</th><th>Run</th></tr></thead><tbody>
      <?php foreach ($connectors as $connector): ?><tr><td><strong><?= htmlspecialchars($connector['connector_name']) ?></strong><br><small><?= htmlspecialchars($connector['source_type']) ?> · <?= htmlspecialchars($connector['run_mode']) ?> · <?= htmlspecialchars($connector['region_name'] ?? 'National') ?></small></td><td><?= htmlspecialchars($connector['status']) ?></td><td><?= (int)$connector['records_imported'] ?> / <?= (int)$connector['records_found'] ?></td><td><form method="post" action="/production-readiness/connectors/run"><input type="hidden" name="connector_id" value="<?= (int)$connector['id'] ?>"><button class="btn secondary">Run</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="table-wrap"><table><thead><tr><th>Run</th><th>Status</th><th>Review</th></tr></thead><tbody>
      <?php foreach ($connectorRuns as $run): ?><tr><td><?= htmlspecialchars($run['connector_name']) ?><br><small><?= htmlspecialchars($run['started_at']) ?> · <?= htmlspecialchars($run['region_name'] ?? 'National') ?></small></td><td><?= htmlspecialchars($run['status']) ?> · imported <?= (int)$run['imported_count'] ?> · skipped <?= (int)$run['skipped_count'] ?></td><td><?= htmlspecialchars($run['review_status']) ?></td></tr><?php endforeach; ?>
      <?php if (!$connectorRuns): ?><tr><td colspan="3">No connector runs yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section id="feedback" class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Operator Feedback</h2><span class="status">Live Operations</span></div>
    <form method="post" action="/production-readiness/feedback" class="form-grid compact">
      <label>Owner <input name="owner" value="<?= htmlspecialchars($user['name'] ?? 'Admin') ?>"></label>
      <label>Theater <select name="region_id"><option value="">National / Shared</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Area <select name="feedback_area"><option>Command Center</option><option>Daily Actions</option><option>Recommendations</option><option>Visuals</option><option>Data Quality</option><option>Workflow</option><option>Security</option><option>Other</option></select></label>
      <label>Friction <input type="number" name="friction_score" min="0" max="100" value="50"></label>
      <label>Impact <input type="number" name="impact_score" min="0" max="100" value="70"></label>
      <label class="full">Feedback <textarea name="feedback_summary" required></textarea></label>
      <label class="full">Recommended Change <textarea name="recommended_change"></textarea></label>
      <button class="btn">Capture Feedback</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recent Feedback</h2><span class="status">Operator Input</span></div>
    <div class="command-items"><?php foreach ($feedback as $item): ?><div><strong><?= htmlspecialchars($item['feedback_area']) ?> - <?= htmlspecialchars($item['owner']) ?></strong><span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · friction <?= (int)$item['friction_score'] ?> · impact <?= (int)$item['impact_score'] ?> · <?= htmlspecialchars($item['status']) ?></span><small><?= htmlspecialchars($item['feedback_summary']) ?></small></div><?php endforeach; ?><?php if (!$feedback): ?><div><strong>No operator feedback yet</strong><span>Capture real workflow friction as operators use the platform.</span></div><?php endif; ?></div>
  </div>
</section>

<section id="tuning" class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Recommendation / Daily Action Tuning</h2><span class="status">Noise Control</span></div>
    <form method="post" action="/production-readiness/tuning" class="form-grid compact">
      <label>Rule Name <input name="rule_name" required></label>
      <label>Source Module <input name="source_module" placeholder="Operational Maturity Engine"></label>
      <label>Category <input name="category" placeholder="Capacity"></label>
      <label>Theater <select name="region_id"><option value="">All</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Min Score <input type="number" name="min_priority_score" value="70"></label>
      <label>Max Daily Actions <input type="number" name="max_daily_actions" value="5"></label>
      <label><input type="checkbox" name="promote_to_daily_action" value="1" checked> Promote</label>
      <label><input type="checkbox" name="active" value="1" checked> Active</label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn">Add Tuning Rule</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Active Tuning Rules</h2><form method="post" action="/production-readiness/tuning/apply"><button class="btn secondary">Apply Action Tuning</button></form></div>
    <div class="table-wrap"><table><thead><tr><th>Rule</th><th>Module</th><th>Category</th><th>Min</th><th>Max</th></tr></thead><tbody><?php foreach ($tuningRules as $rule): ?><tr><td><?= htmlspecialchars($rule['rule_name']) ?><br><small><?= htmlspecialchars($rule['region_name'] ?? 'All') ?></small></td><td><?= htmlspecialchars($rule['source_module'] ?? '') ?></td><td><?= htmlspecialchars($rule['category'] ?? '') ?></td><td><?= (int)$rule['min_priority_score'] ?></td><td><?= (int)$rule['max_daily_actions'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section id="recommendation-governance" class="panel">
  <div class="panel-title"><h2>Noisy Recommendation Suppression</h2><span class="status">Governance</span></div>
  <div class="table-wrap"><table><thead><tr><th>Recommendation</th><th>Module</th><th>Score</th><th>Suppress</th></tr></thead><tbody>
    <?php foreach ($noisyRecommendations as $rec): ?><tr>
      <td><strong><?= htmlspecialchars($rec['title']) ?></strong><br><small><?= htmlspecialchars($rec['region_name'] ?? 'National') ?> · <?= htmlspecialchars($rec['status']) ?></small></td>
      <td><?= htmlspecialchars($rec['source_module'] ?? '') ?><br><small><?= htmlspecialchars($rec['category']) ?></small></td>
      <td><?= (int)$rec['priority_score'] ?></td>
      <td><form method="post" action="/production-readiness/recommendations/not-useful" class="inline-form"><input type="hidden" name="recommendation_id" value="<?= (int)$rec['id'] ?>"><input name="reason" placeholder="Why not useful?"><button class="btn secondary">Not Useful</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section id="deployment" class="panel">
  <div class="panel-title"><h2>Deployment Readiness</h2><span class="status">Checklist</span></div>
  <div class="command-items">
    <div><strong>Sequential Cycle</strong><span>Use <code>php scripts/run_acquisition_cycle.php</code>. Do not run DB-writing scripts in parallel on SQLite.</span></div>
    <div><strong>Integrity Gate</strong><span>Run migrate, production seed/cycle, integrity, route smoke, PHP lint, and backup/restore checks before operating sessions.</span></div>
    <div><strong>Backup / Restore / Export</strong><span>Use <code>scripts/backup_database.php</code>, <code>scripts/restore_database.php</code>, and <code>scripts/export_operating_data.php</code>; verify restore before real-data work.</span></div>
    <div><strong>Password Recovery</strong><span>Reset tokens expire after 60 minutes and are one-time use. Production must configure a mailer; local/dev logs tokens only for controlled testing.</span></div>
    <div><strong>Error Logs</strong><span>Application errors write to <code>storage/logs/app.log</code>. Production mode hides stack traces from operators.</span></div>
  </div>
</section>

<section id="audit" class="panel">
  <div class="panel-title"><h2>Audit Log</h2><span class="status">Admin Control</span></div>
  <form method="get" action="/production-readiness" class="list-toolbar">
    <input type="hidden" name="section" value="audit">
    <input name="audit_q" value="<?= htmlspecialchars($auditFilters['audit_q'] ?? '') ?>" placeholder="Search audit">
    <input name="audit_user_name" value="<?= htmlspecialchars($auditFilters['audit_user_name'] ?? '') ?>" placeholder="User">
    <input name="audit_action" value="<?= htmlspecialchars($auditFilters['audit_action'] ?? '') ?>" placeholder="Action">
    <input name="audit_record_type" value="<?= htmlspecialchars($auditFilters['audit_record_type'] ?? '') ?>" placeholder="Record type">
    <input name="audit_outcome" value="<?= htmlspecialchars($auditFilters['audit_outcome'] ?? '') ?>" placeholder="Outcome">
    <input type="date" name="audit_from" value="<?= htmlspecialchars($auditFilters['audit_from'] ?? '') ?>">
    <input type="date" name="audit_to" value="<?= htmlspecialchars($auditFilters['audit_to'] ?? '') ?>">
    <button class="btn secondary">Filter Audit</button>
    <a class="btn ghost" href="/production-readiness#audit">Clear</a>
  </form>
  <div class="table-wrap"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Outcome</th></tr></thead><tbody>
    <?php foreach ($auditLogs as $log): ?><tr><td><?= htmlspecialchars($log['created_at']) ?></td><td><?= htmlspecialchars(($log['user_name'] ?? 'System') . ' ' . ($log['role'] ? '(' . $log['role'] . ')' : '')) ?></td><td><?= htmlspecialchars($log['action']) ?><br><small><?= htmlspecialchars($log['details'] ?? '') ?></small></td><td><?= htmlspecialchars(($log['record_type'] ?? '') . ($log['record_id'] ? ' #' . $log['record_id'] : '')) ?></td><td><?= htmlspecialchars($log['outcome']) ?></td></tr><?php endforeach; ?>
    <?php if (!$auditLogs): ?><tr><td colspan="5">No audit events yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section id="syncerp-contract" class="panel">
  <div class="panel-title"><h2>SyncERP Contract Validation</h2><a class="btn secondary" href="/syncerp-integration">Open SyncERP Integration</a></div>
  <div class="table-wrap"><table><thead><tr><th>Area</th><th>Field</th><th>Source</th><th>Status</th><th>Update</th></tr></thead><tbody>
    <?php foreach ($erpValidation as $item): ?><tr>
      <td><?= htmlspecialchars($item['contract_area']) ?></td><td><strong><?= htmlspecialchars($item['field_name']) ?></strong><br><small><?= $item['required_for_handoff'] ? 'Required' : 'Optional' ?></small></td><td><?= htmlspecialchars($item['source_record_type'] ?? '') ?>.<?= htmlspecialchars($item['source_field'] ?? '') ?></td><td><?= htmlspecialchars($item['validation_status']) ?></td>
      <td><form method="post" action="/production-readiness/erp-validation" class="inline-form"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><select name="validation_status"><option>Pending</option><option>Validated</option><option>Needs SyncERP Review</option><option>Not Required</option></select><input name="notes" placeholder="Notes"><button class="btn secondary">Save</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
