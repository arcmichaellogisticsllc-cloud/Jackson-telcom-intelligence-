<section class="page-header command-page-header">
  <p class="eyebrow">Operator Pilot / Production Readiness</p>
  <h1>Pilot-ready and production-safe</h1>
  <p>Authorization, security hardening, data review, connector readiness, pilot feedback, action tuning, deployment readiness, and SyncERP contract validation.</p>
</section>

<nav class="dash-tabs">
  <a href="#authorization">Authorization</a>
  <a href="#data-review">Data Review</a>
  <a href="#connector">Connector</a>
  <a href="#feedback">Pilot Feedback</a>
  <a href="#tuning">Action Tuning</a>
  <a href="#deployment">Deployment</a>
  <a href="#syncerp-contract">SyncERP Contract</a>
</nav>

<?php
$why = 'The platform is architecturally complete. This page controls the remaining work to make it safe for a real operator pilot.';
$recommended = 'Start with open Critical/High data review items, then tune noisy actions and validate SyncERP handoff fields.';
$next = 'Resolve one data review item, record one pilot feedback item, and validate one SyncERP contract field during each weekly readiness review.';
$risk = 'Without this layer, bad data, weak permissions, noisy actions, or unclear handoff fields can damage operator trust.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Open Reviews</span><strong><?= (int)$metrics['open_reviews'] ?></strong></div>
  <div><span>Critical Reviews</span><strong><?= (int)$metrics['critical_reviews'] ?></strong></div>
  <div><span>Pilot Feedback</span><strong><?= (int)$metrics['pilot_feedback'] ?></strong></div>
  <div><span>Contract Pending</span><strong><?= (int)$metrics['contract_pending'] ?></strong></div>
  <div><span>CSRF / Session</span><strong>On</strong></div>
</section>

<section id="authorization" class="panel">
  <div class="panel-title"><h2>Authorization / Security Hardening</h2><span class="status">Active</span></div>
  <div class="command-items">
    <div><strong>Route Protection</strong><span>Authenticated routes require login; POST routes require CSRF tokens.</span></div>
    <div><strong>Operator Region Access</strong><span>Mike: Southeast + shared Southwest/National. Ron: Great Lakes + shared Southwest/National. Admin: all.</span></div>
    <div><strong>Session Hardening</strong><span>Session timeout, CSRF rotation on login, secure headers, HTTP-only same-site cookies.</span></div>
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

<section id="connector" class="panel">
  <div class="panel-title"><h2>First Real Connector</h2><span class="status">RSS / Opt-In</span></div>
  <p>The first production-safe connector is RSS. It only runs when a Signal Source has collection method <strong>RSS</strong> and a source URL. It parses feed titles, descriptions, links, dates, and payloads into Raw Signal Items. It does not scrape pages and does not send outreach.</p>
  <p><a class="btn secondary" href="/harvesters">Open Acquisition Harvesters</a></p>
</section>

<section id="feedback" class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Operator Pilot Feedback</h2><span class="status">30-Day Pilot</span></div>
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
    <div class="command-items"><?php foreach ($feedback as $item): ?><div><strong><?= htmlspecialchars($item['feedback_area']) ?> - <?= htmlspecialchars($item['owner']) ?></strong><span><?= htmlspecialchars($item['region_name'] ?? 'National') ?> · friction <?= (int)$item['friction_score'] ?> · impact <?= (int)$item['impact_score'] ?> · <?= htmlspecialchars($item['status']) ?></span><small><?= htmlspecialchars($item['feedback_summary']) ?></small></div><?php endforeach; ?><?php if (!$feedback): ?><div><strong>No pilot feedback yet</strong><span>Capture operator friction during the 30-day pilot.</span></div><?php endif; ?></div>
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
    <div class="panel-title"><h2>Active Tuning Rules</h2><span class="status">Pilot Controls</span></div>
    <div class="table-wrap"><table><thead><tr><th>Rule</th><th>Module</th><th>Category</th><th>Min</th><th>Max</th></tr></thead><tbody><?php foreach ($tuningRules as $rule): ?><tr><td><?= htmlspecialchars($rule['rule_name']) ?><br><small><?= htmlspecialchars($rule['region_name'] ?? 'All') ?></small></td><td><?= htmlspecialchars($rule['source_module'] ?? '') ?></td><td><?= htmlspecialchars($rule['category'] ?? '') ?></td><td><?= (int)$rule['min_priority_score'] ?></td><td><?= (int)$rule['max_daily_actions'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>

<section id="deployment" class="panel">
  <div class="panel-title"><h2>Deployment Readiness</h2><span class="status">Checklist</span></div>
  <div class="command-items">
    <div><strong>Sequential Cycle</strong><span>Use <code>php scripts/run_acquisition_cycle.php</code>. Do not run DB-writing scripts in parallel on SQLite.</span></div>
    <div><strong>Integrity Gate</strong><span>Run migrate, seed/cycle, integrity, route smoke, PHP lint, and backup/restore checks before pilot sessions.</span></div>
    <div><strong>Backup / Export</strong><span>Use <code>scripts/backup_database.php</code> and <code>scripts/export_operating_data.php</code>; verify restore before pilot kickoff.</span></div>
  </div>
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
