<?php
$company = $intake['company_name'] ?? 'Subcontractor';
$services = (string)($intake['services_offered'] ?? '');
$serviceChecked = function (string $needle) use ($services): string {
    return str_contains(strtolower($services), strtolower($needle)) ? 'checked' : '';
};
?>

<section class="page-header command-page-header">
  <p class="eyebrow">Jackson Telcom Subcontractor Intake</p>
  <h1>Subcontractor readiness information.</h1>
  <p>Complete this form so Jackson can review coverage, services, crews, equipment, and onboarding documents.</p>
</section>

<?php if (!empty($submitted)): ?>
<section class="panel">
  <div class="panel-title"><h2>Intake Submitted</h2><span class="status">Jackson Review Required</span></div>
  <p>Thank you. Jackson has received the intake information and will review it before any approval or onboarding decision.</p>
</section>
<?php elseif (empty($intake)): ?>
<section class="panel">
  <div class="panel-title"><h2>Intake Link Not Available</h2><span class="status">Expired Or Used</span></div>
  <p><?= htmlspecialchars($invalidReason ?? 'This intake link is not active.') ?></p>
</section>
<?php else: ?>
<section class="action-first-grid">
  <article><span>WHAT THIS IS</span><p>A subcontractor intake form for <?= htmlspecialchars($company) ?>.</p></article>
  <article><span>WHY IT MATTERS</span><p>Jackson uses this information to decide whether capacity is real, available, and ready for compliance review.</p></article>
  <article><span>NEXT STEP</span><p>Fill in what you know. Leave unknown items blank or mark documents as not ready.</p></article>
  <article><span>RISK OF INACTION</span><p>Incomplete information slows onboarding and prevents Jackson from using your crews on real work.</p></article>
</section>

<section class="panel">
  <div class="panel-title"><h2><?= htmlspecialchars($company) ?></h2><span class="status"><?= htmlspecialchars($intake['region_name'] ?? 'Jackson') ?></span></div>
  <form method="post" action="/onboarding/intake" class="form-grid">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <label>Company Name <input name="company_name" value="<?= htmlspecialchars($intake['company_name'] ?? '') ?>" required></label>
    <label>Legal Name <input name="legal_name" value="<?= htmlspecialchars($intake['legal_name'] ?? '') ?>"></label>
    <label>Years In Business <input type="number" name="years_in_business" min="0" value="0"></label>
    <label>Website <input name="website" value="<?= htmlspecialchars($intake['website'] ?? '') ?>"></label>
    <label>Business Phone <input name="phone" value="<?= htmlspecialchars($intake['phone'] ?? '') ?>"></label>
    <label>Business Email <input name="email" value="<?= htmlspecialchars($intake['email'] ?? '') ?>"></label>

    <label>Owner Name <input name="owner_name" value="<?= htmlspecialchars($intake['owner_name'] ?? '') ?>"></label>
    <label>Primary Contact <input name="primary_contact" value="<?= htmlspecialchars($intake['primary_contact'] ?? '') ?>"></label>
    <label>Contact Title <input name="contact_title" value="<?= htmlspecialchars($intake['contact_title'] ?? '') ?>"></label>

    <label>States Served <input name="states_served" value="<?= htmlspecialchars($intake['states_served'] ?? '') ?>" placeholder="GA, FL, AL"></label>
    <label>Markets Served <input name="markets_served" value="<?= htmlspecialchars($intake['markets_served'] ?? '') ?>" placeholder="Atlanta, Macon, Jacksonville"></label>
    <label>Availability <input name="availability" value="<?= htmlspecialchars($intake['availability'] ?? '') ?>" placeholder="Now, 2 weeks, 30 days"></label>

    <div class="full checklist-grid">
      <strong>Services You Self-Perform</strong>
      <label><input type="checkbox" name="service_aerial" value="1" <?= $serviceChecked('Aerial') ?>> Aerial</label>
      <label><input type="checkbox" name="service_underground" value="1" <?= $serviceChecked('Underground') ?>> Underground</label>
      <label><input type="checkbox" name="service_fiber_splicing" value="1" <?= $serviceChecked('Fiber Splicing') ?>> Fiber Splicing</label>
      <label><input type="checkbox" name="service_directional_boring" value="1" <?= $serviceChecked('Directional Boring') ?>> Directional Boring</label>
      <label><input type="checkbox" name="service_traffic_control" value="1" <?= $serviceChecked('Traffic Control') ?>> Traffic Control</label>
      <label><input type="checkbox" name="service_row_mowing" value="1" <?= $serviceChecked('ROW') ?>> ROW / Mowing</label>
      <label><input type="checkbox" name="service_inspection" value="1" <?= $serviceChecked('Inspection') ?>> Inspection</label>
      <label><input type="checkbox" name="service_qc" value="1" <?= $serviceChecked('QC') ?>> QC</label>
      <label><input type="checkbox" name="service_make_ready" value="1" <?= $serviceChecked('Make Ready') ?>> Make Ready</label>
    </div>
    <label class="full">Other Services <input name="services_other" placeholder="Other telecom construction work"></label>

    <label>Total Crew Count <input type="number" name="crew_count" min="0" value="<?= (int)($intake['crew_count'] ?? 0) ?>"></label>
    <label>Available Crew Count <input type="number" name="available_crew_count" min="0" value="<?= (int)($intake['available_crew_count'] ?? 0) ?>"></label>
    <label>Aerial Crews <input type="number" name="aerial_crew_count" min="0" value="0"></label>
    <label>Underground Crews <input type="number" name="underground_crew_count" min="0" value="0"></label>
    <label>Fiber Splicing Crews <input type="number" name="fiber_splicing_crew_count" min="0" value="0"></label>
    <label>Directional Boring Crews <input type="number" name="directional_boring_crew_count" min="0" value="0"></label>
    <label>Traffic Control Crews <input type="number" name="traffic_control_crew_count" min="0" value="0"></label>
    <label>ROW / Mowing Crews <input type="number" name="mowing_row_crew_count" min="0" value="0"></label>
    <label>Inspection Crews <input type="number" name="inspection_crew_count" min="0" value="0"></label>
    <label>QC Crews <input type="number" name="qc_crew_count" min="0" value="0"></label>
    <label>Make Ready Crews <input type="number" name="make_ready_crew_count" min="0" value="0"></label>

    <label>Bucket Trucks <input type="number" name="bucket_trucks" min="0" value="0"></label>
    <label>Digger Derricks <input type="number" name="digger_derricks" min="0" value="0"></label>
    <label>Directional Drills <input type="number" name="directional_drills" min="0" value="0"></label>
    <label>Splicing Trailers <input type="number" name="splicing_trailers" min="0" value="0"></label>
    <label>Fusion Splicers <input type="number" name="fusion_splicers" min="0" value="0"></label>
    <label>Reel Trailers <input type="number" name="reel_trailers" min="0" value="0"></label>
    <label>Vac Trucks <input type="number" name="vac_trucks" min="0" value="0"></label>
    <label class="full">Equipment Notes <textarea name="equipment_notes" placeholder="Other trucks, trailers, tools, locating, compactors, restoration equipment"></textarea></label>

    <div class="full checklist-grid">
      <strong>Document Readiness</strong>
      <p class="full"><small>No files are uploaded here. Mark what is ready so Jackson can request/review the actual documents separately.</small></p>
      <?php foreach (['W9','COI','MSA','NDA','Safety Program'] as $doc): ?>
        <label><?= htmlspecialchars($doc) ?>
          <select name="doc_<?= strtolower(str_replace(' ', '_', $doc)) ?>">
            <option>Requested</option>
            <option>Submitted</option>
            <option>Missing</option>
          </select>
        </label>
      <?php endforeach; ?>
    </div>

    <label class="full">Notes <textarea name="notes" placeholder="Coverage limits, crew readiness, safety notes, current workload, references, or anything Jackson should know"></textarea></label>
    <button class="btn">Submit Intake For Jackson Review</button>
  </form>
</section>
<?php endif; ?>
