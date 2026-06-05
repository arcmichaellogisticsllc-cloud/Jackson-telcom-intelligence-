<section class="page-header">
  <p class="eyebrow">Subcontractor Candidate</p>
  <h1><?= htmlspecialchars($subcontractor['company_name'] ?: $subcontractor['organization_name']) ?></h1>
  <p><?= htmlspecialchars($subcontractor['region_name']) ?> · <?= htmlspecialchars($subcontractor['approval_stage']) ?> · <?= (int)$subcontractor['available_crew_count'] ?> available crews</p>
</section>

<section class="metrics">
  <div><span>Qualification Score</span><strong><?= (int)($subcontractor['qualification_score'] ?? 0) ?></strong><small><?= htmlspecialchars($subcontractor['qualification_result'] ?? '') ?></small></div>
  <div><span>Capacity Contribution</span><strong><?= (int)($subcontractor['capacity_contribution_score'] ?? 0) ?></strong><small><?= htmlspecialchars($subcontractor['capacity_contribution_category'] ?? '') ?></small></div>
  <div><span>Network Level</span><strong><?= htmlspecialchars($subcontractor['network_level'] ?? 'Prospect') ?></strong></div>
  <div><span>Total Crews</span><strong><?= (int)$subcontractor['crew_count'] ?></strong></div>
  <div><span>Available Crews</span><strong><?= (int)$subcontractor['available_crew_count'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Company Information</h2>
    <div class="table-wrap"><table><tbody>
      <?php foreach (['legal_name','years_in_business','website','phone','email','owner_name','primary_contact','contact_title','states_served','markets_served','services_offered','availability'] as $field): ?>
        <tr><th><?= htmlspecialchars(str_replace('_', ' ', $field)) ?></th><td><?= htmlspecialchars((string)($subcontractor[$field] ?? '')) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <hr>
    <h2>Promotion</h2>
    <p><?= htmlspecialchars($subcontractor['promotion_recommendation'] ?? 'Continue qualification and compliance follow-up.') ?></p>
    <form method="post" action="/subcontractor-acquisition/promote" class="form-grid compact">
      <input type="hidden" name="subcontractor_id" value="<?= (int)$subcontractor['id'] ?>">
      <label>Network Level <select name="level"><?php foreach ($pipeline as $stage): ?><option <?= $stage === $subcontractor['approval_stage'] ? 'selected' : '' ?>><?= htmlspecialchars($stage) ?></option><?php endforeach; ?></select></label>
      <button class="btn">Update Level</button>
    </form>
  </div>
  <div class="panel">
    <h2>Capacity and Equipment</h2>
    <div class="table-wrap"><table><tbody>
      <?php foreach (['aerial_crew_count','underground_crew_count','fiber_splicing_crew_count','directional_boring_crew_count','emergency_restoration_crew_count','traffic_control_crew_count','mowing_row_crew_count','inspection_crew_count','qc_crew_count','engineering_crew_count','make_ready_crew_count','drop_crew_count','bucket_trucks','digger_derricks','directional_drills','splicing_trailers','fusion_splicers','reel_trailers','vac_trucks'] as $field): ?>
        <tr><th><?= htmlspecialchars(str_replace('_', ' ', $field)) ?></th><td><?= (int)($subcontractor[$field] ?? 0) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Qualification Scorecard</h2><span class="status">0-10 each</span></div>
  <form method="post" action="/subcontractor-acquisition/scorecard" class="form-grid">
    <input type="hidden" name="subcontractor_id" value="<?= (int)$subcontractor['id'] ?>">
    <?php foreach (['service_fit','geographic_fit','crew_capacity','mobilization_speed','equipment_availability','insurance_readiness','w9_readiness','communication','experience','safety'] as $field): ?>
      <label><?= htmlspecialchars(str_replace('_', ' ', $field)) ?> <input type="number" min="0" max="10" name="<?= htmlspecialchars($field) ?>" value="<?= (int)($subcontractor[$field] ?? 0) ?>"></label>
    <?php endforeach; ?>
    <label class="full">Notes <textarea name="notes"><?= htmlspecialchars($subcontractor['scorecard_notes'] ?? '') ?></textarea></label>
    <button class="btn">Save Scorecard</button>
  </form>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Compliance</h2><span class="status">Required documents</span></div>
    <div class="table-wrap"><table><thead><tr><th>Document</th><th>Status</th><th>Expiration</th><th>Review</th></tr></thead><tbody>
      <?php foreach ($compliance as $item): ?><tr><td><?= htmlspecialchars($item['document_type']) ?></td><td><?= htmlspecialchars($item['status']) ?></td><td><?= htmlspecialchars($item['expiration_date'] ?? '') ?></td><td><?= htmlspecialchars($item['review_date'] ?? '') ?> · <?= htmlspecialchars($item['reviewed_by'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <form method="post" action="/subcontractor-acquisition/compliance" class="form-grid compact">
      <input type="hidden" name="subcontractor_id" value="<?= (int)$subcontractor['id'] ?>">
      <label>Document <select name="document_type"><?php foreach ($documentTypes as $type): ?><option><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></label>
      <label>Status <select name="status"><?php foreach (['Missing','Requested','Submitted','Approved','Expired'] as $status): ?><option><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label>
      <label>Expiration <input type="date" name="expiration_date"></label>
      <label>Review Date <input type="date" name="review_date" value="<?= date('Y-m-d') ?>"></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn secondary">Save Compliance</button>
    </form>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Documents</h2><span class="status">Storage records only</span></div>
    <div class="table-wrap"><table><thead><tr><th>File</th><th>Type</th><th>Status</th><th>Expiration</th></tr></thead><tbody>
      <?php foreach ($documents as $doc): ?><tr><td><?= htmlspecialchars($doc['file_name']) ?><br><small><?= htmlspecialchars($doc['storage_path'] ?? '') ?></small></td><td><?= htmlspecialchars($doc['document_type']) ?></td><td><?= htmlspecialchars($doc['status']) ?></td><td><?= htmlspecialchars($doc['expiration_date'] ?? '') ?></td></tr><?php endforeach; ?>
      <?php if (!$documents): ?><tr><td colspan="4">No document records yet.</td></tr><?php endif; ?>
    </tbody></table></div>
    <form method="post" action="/subcontractor-acquisition/documents" class="form-grid compact">
      <input type="hidden" name="subcontractor_id" value="<?= (int)$subcontractor['id'] ?>">
      <label>File Name <input name="file_name" required placeholder="abc-fiber-coi.pdf"></label>
      <label>Type <select name="document_type"><?php foreach ($documentTypes as $type): ?><option><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></label>
      <label>Status <select name="status"><?php foreach (['Missing','Requested','Submitted','Approved','Expired'] as $status): ?><option><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></label>
      <label>Expiration <input type="date" name="expiration_date"></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn secondary">Add Document Record</button>
    </form>
  </div>
</section>

<section class="panel">
  <h2>Activity Timeline</h2>
  <div class="activity-list"><?php foreach ($activities as $activity): ?><div><strong><?= htmlspecialchars($activity['title']) ?></strong><span><?= htmlspecialchars(substr($activity['activity_date'],0,10)) ?> · <?= htmlspecialchars($activity['owner']) ?></span><p><?= htmlspecialchars($activity['notes']) ?></p></div><?php endforeach; ?><?php if (!$activities): ?><p>No activity yet.</p><?php endif; ?></div>
</section>
