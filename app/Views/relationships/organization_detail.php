<?php
$classificationOptions = ['Has Work','Has Capacity','Needs Work','Influences Work','Competitor','Strategic Account','Engineering Firm','Prime Contractor','Capacity Provider'];
$activeClasses = $classificationNames ?? [];
$recordEyebrow = 'Organization Workspace';
$recordName = $organization['name'];
$recordType = $organization['type'] ?: 'Organization';
$recordRegion = $organization['region_name'] ?? 'National';
$recordOwner = $organization['primary_owner'] ?? $organization['owner'] ?? 'Unassigned';
$recordPrimaryOwner = $organization['primary_owner'] ?? $recordOwner;
$recordSecondaryOwner = $organization['secondary_owner'] ?? '';
$recordSharedOwnerFlag = (int)($organization['shared_owner_flag'] ?? 0);
$recordOwnershipNotes = $organization['ownership_notes'] ?? '';
$recordStatus = $organization['status'] ?? 'Active';
$recordScore = (int)($organizationConfidenceScore ?? 0);
$recordNextAction = $organizationNextBestAction ?? 'Review this organization and assign the next action.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Owner','Mark Reviewed'];
$recordEntityType = 'organization';
$recordEntityId = (int)$organization['id'];
$recordRegionId = (int)($organization['region_id'] ?? 0);
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Intelligence','Timeline','Source Evidence','Opportunities / Work','Capacity','Relationships','Onboarding','Actions'];
require __DIR__ . '/../components/record_tabs.php';

$h = fn($value) => htmlspecialchars((string)($value ?? ''));
$count = fn($key) => (int)($associationCounts[$key] ?? 0);
?>

<section class="org-workspace">
  <aside class="org-left-rail">
    <div class="panel org-snapshot">
      <div class="panel-title"><h2>Record Snapshot</h2><span class="status"><?= $h($organizationReviewStatus ?? 'No Evidence') ?></span></div>
      <dl class="detail-list">
        <dt>Type</dt><dd><?= $h($organization['type'] ?: 'Unknown') ?></dd>
        <dt>Region</dt><dd><?= $h($organization['region_name'] ?? 'National') ?></dd>
        <dt>Market</dt><dd><?= $h($organization['city'] ?: ($organization['state'] ?? 'Unknown')) ?></dd>
        <dt>Owner</dt><dd><?= $h($recordPrimaryOwner ?: 'Unassigned') ?></dd>
        <dt>Status</dt><dd><?= $h($recordStatus ?: 'Active') ?></dd>
        <dt>Website</dt><dd><?php if (!empty($organization['website'])): ?><a href="<?= $h($organization['website']) ?>" target="_blank" rel="noopener"><?= $h($organization['website']) ?></a><?php else: ?>Not captured<?php endif; ?></dd>
        <dt>Confidence</dt><dd><?= $recordScore > 0 ? $recordScore . '/100' : 'No score yet' ?></dd>
        <dt>Review</dt><dd><?= $h($organizationReviewStatus ?? 'No Evidence') ?></dd>
      </dl>
    </div>

    <div class="panel classification-card">
      <div class="panel-title"><h2>Classifications</h2><span class="status"><?= count($activeClasses) ?> active</span></div>
      <div class="classification-grid">
        <?php foreach ($classificationOptions as $classification): ?>
          <?php $active = in_array($classification, $activeClasses, true); ?>
          <span class="classification-pill <?= $active ? 'active' : '' ?>"><?= $h($classification) ?></span>
        <?php endforeach; ?>
      </div>
      <?php if (!$activeClasses): ?><p class="hint">No classifications yet. Import source evidence or mark the organization during review.</p><?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-title"><h2>Next Best Action</h2><span class="status">Do Next</span></div>
      <p class="next-best-action"><?= $h($recordNextAction) ?></p>
      <div class="mini-actions">
        <a class="btn" href="#actions">Open Actions</a>
        <a class="btn secondary" href="#source-evidence">Review Evidence</a>
      </div>
    </div>
  </aside>

  <main class="org-main">
    <section class="panel evidence-first" id="overview">
      <div class="panel-title">
        <div><p class="eyebrow">Evidence First</p><h2>Trust this record only as far as the sources support it</h2></div>
        <span class="status"><?= count($sourceEvidence ?? []) ?> sources</span>
      </div>
      <?php if (!empty($sourceEvidence)): ?>
        <?php $primaryEvidence = $sourceEvidence[0]; ?>
        <article class="source-card">
          <strong><?= $h($primaryEvidence['source_name'] ?: $primaryEvidence['source_type'] ?: 'Source evidence') ?></strong>
          <p><?= $h($primaryEvidence['evidence_summary'] ?: 'Source captured for review.') ?></p>
          <small><?= $h($primaryEvidence['source_type']) ?> · Confidence <?= (int)($primaryEvidence['confidence_score'] ?? 0) ?> · <?= $h($primaryEvidence['review_status']) ?> · <?= $h(substr((string)($primaryEvidence['collected_at'] ?? ''), 0, 10)) ?></small>
          <?php if (!empty($primaryEvidence['source_url'])): ?><a class="btn secondary" href="<?= $h($primaryEvidence['source_url']) ?>" target="_blank" rel="noopener">Open Source</a><?php endif; ?>
        </article>
      <?php else: ?>
        <article class="empty-state source-card">
          <strong>No source evidence yet</strong>
          <p>Add source evidence before treating this organization as trusted work, capacity, or influence intelligence.</p>
        </article>
      <?php endif; ?>
    </section>

    <section class="grid three" id="intelligence">
      <article class="panel intel-card">
        <p class="eyebrow">Work Fit</p>
        <h2>Does this organization have work?</h2>
        <strong><?= $count('opportunities') ?></strong>
        <p><?= $h($workFit ?? 'No opportunity watches yet.') ?></p>
      </article>
      <article class="panel intel-card">
        <p class="eyebrow">Capacity Fit</p>
        <h2>Can this organization perform work?</h2>
        <strong><?= $count('capacity') ?></strong>
        <p><?= $h($capacityFit ?? 'No capacity profile tied to this organization yet.') ?></p>
      </article>
      <article class="panel intel-card">
        <p class="eyebrow">Influence Fit</p>
        <h2>Does this organization influence work?</h2>
        <strong><?= $count('contacts') + count($profiles ?? []) ?></strong>
        <p><?= $h($relationshipFit ?? 'No contacts tied to this organization yet.') ?></p>
      </article>
    </section>

    <?php require __DIR__ . '/../components/recent_conversations.php'; ?>
    <?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

    <section class="panel" id="source-evidence">
      <div class="panel-title"><h2>Source Evidence</h2><span class="status"><?= count($sourceEvidence ?? []) ?> items</span></div>
      <div class="stack-list">
        <?php foreach ($sourceEvidence as $item): ?><article>
          <strong><?= $h($item['source_name'] ?: $item['source_type'] ?: 'Source evidence') ?></strong>
          <p><?= $h($item['evidence_summary'] ?: 'No summary recorded.') ?></p>
          <small>Confidence <?= (int)($item['confidence_score'] ?? 0) ?> · <?= $h($item['review_status']) ?> · <?= $h(substr((string)($item['collected_at'] ?? ''), 0, 10)) ?></small>
          <?php if (!empty($item['source_url'])): ?><a href="<?= $h($item['source_url']) ?>" target="_blank" rel="noopener">Open source</a><?php endif; ?>
        </article><?php endforeach; ?>
        <?php if (empty($sourceEvidence)): ?><article class="empty-state"><strong>No evidence linked</strong><p>Imported or reviewed source evidence will appear here.</p></article><?php endif; ?>
      </div>
    </section>

    <section class="panel" id="opportunities-work">
      <div class="panel-title"><h2>Opportunities / Work</h2><a class="btn secondary" href="/opportunities">Open Work List</a></div>
      <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Stage</th><th>Value</th><th>Next Action</th></tr></thead><tbody>
        <?php foreach ($opportunities as $opportunity): ?><tr>
          <td><a href="/pursuits/detail?id=<?= (int)$opportunity['id'] ?>"><?= $h($opportunity['name']) ?></a><br><small><?= $h($opportunity['opportunity_type'] ?? '') ?></small></td>
          <td><?= $h($opportunity['stage']) ?></td>
          <td><?= $opportunity['estimated_value'] !== null ? '$' . number_format((float)$opportunity['estimated_value']) : 'Unknown' ?></td>
          <td><?= $h($opportunity['next_action']) ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$opportunities): ?><tr><td colspan="4">No opportunity watches tied to this organization yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>

    <section class="panel" id="capacity">
      <div class="panel-title"><h2>Capacity</h2><a class="btn secondary" href="/capacity-radar">Open Capacity Radar</a></div>
      <div class="grid two">
        <div>
          <h3>Capacity Profiles</h3>
          <div class="stack-list">
            <?php foreach ($capacityProfiles as $profile): ?><article><strong><?= $h($profile['profile_name']) ?></strong><p><?= $h($profile['profile_type']) ?> · <?= $h($profile['status']) ?> · Readiness <?= (int)($profile['primary_mobilization_readiness'] ?? 0) ?></p><small><?= $h($profile['states_served'] ?: $profile['markets_served']) ?></small></article><?php endforeach; ?>
            <?php if (!$capacityProfiles): ?><article class="empty-state"><strong>No capacity profile</strong><p>Create one if this organization can perform work.</p></article><?php endif; ?>
          </div>
        </div>
        <div>
          <h3>Subcontractor Records</h3>
          <div class="stack-list">
            <?php foreach ($subcontractors as $sub): ?><article><strong><?= $h($sub['company_name'] ?: $organization['name']) ?></strong><p><?= $h($sub['approval_stage']) ?> · <?= (int)($sub['available_crew_count'] ?? $sub['crew_count'] ?? 0) ?> crews available</p><small><?= $h($sub['services_offered']) ?></small></article><?php endforeach; ?>
            <?php if (!$subcontractors): ?><article class="empty-state"><strong>No subcontractor record</strong><p>Qualify before onboarding or approving capacity.</p></article><?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="panel" id="relationships">
      <div class="panel-title"><h2>Relationships</h2><span class="status"><?= count($contacts) ?> contacts</span></div>
      <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Role / Access</th><th>Influence</th><th>Next Action</th></tr></thead><tbody>
        <?php foreach ($contacts as $contact): ?><tr>
          <td><a href="/contacts/detail?id=<?= (int)$contact['id'] ?>"><?= $h(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?></a><br><small><?= $h($contact['title']) ?></small></td>
          <td><?= $h($contact['role_types'] ?: 'Needs role review') ?><br><small><?= $h($contact['access_categories'] ?: 'Access not classified') ?></small></td>
          <td><?= $h($contact['influence_level'] ?: 'Unknown') ?></td>
          <td><?= $h($contact['next_action'] ?: 'Log first conversation or assign follow-up.') ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$contacts): ?><tr><td colspan="4">No contacts tied to this organization yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>

    <section class="panel" id="onboarding">
      <div class="panel-title"><h2>Onboarding</h2><a class="btn secondary" href="/onboarding">Open Onboarding</a></div>
      <div class="table-wrap"><table><thead><tr><th>Record</th><th>Status</th><th>Readiness</th><th>Missing / Risk</th></tr></thead><tbody>
        <?php foreach ($subcontractors as $sub): ?>
          <?php if (!empty($sub['onboarding_id'])): ?><tr>
            <td><a href="/onboarding/subcontractors/detail?id=<?= (int)$sub['onboarding_id'] ?>"><?= $h($sub['company_name'] ?: $organization['name']) ?></a></td>
            <td><?= $h($sub['onboarding_status']) ?></td>
            <td><?= (int)($sub['onboarding_score'] ?? 0) ?> · <?= $h($sub['readiness_category']) ?></td>
            <td><?= $h($sub['missing_items'] ?: $sub['risk_flags']) ?></td>
          </tr><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($count('onboarding') === 0): ?><tr><td colspan="4">No onboarding record tied to this organization yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>

    <section class="panel" id="actions">
      <div class="panel-title"><h2>Actions</h2><a class="btn secondary" href="/recommendations">Open Recommendations</a></div>
      <div class="stack-list">
        <?php foreach ($recommendedActions as $action): ?><article>
          <strong><?= $h($action['title']) ?></strong>
          <p><?= $h($action['recommended_next_action'] ?: $action['reason']) ?></p>
          <small><?= $h($action['category']) ?> · <?= $h($action['priority']) ?> · <?= $h($action['assigned_owner'] ?: 'Unassigned') ?></small>
        </article><?php endforeach; ?>
        <?php if (!$recommendedActions): ?><article class="empty-state"><strong>No open actions</strong><p>Create a follow-up from the record header when this organization needs movement.</p></article><?php endif; ?>
      </div>
    </section>
  </main>

  <aside class="org-right-rail">
    <div class="panel association-rail">
      <div class="panel-title"><h2>Associated Records</h2><span class="status">Live</span></div>
      <?php
      $associations = [
          ['Contacts', $count('contacts'), '#relationships'],
          ['Opportunities / Watches', $count('opportunities'), '#opportunities-work'],
          ['Capacity Profiles', count($capacityProfiles), '#capacity'],
          ['Subcontractor Onboarding', $count('onboarding'), '#onboarding'],
          ['Influence Relationships', count($profiles ?? []), '#relationships'],
          ['Recommended Actions', $count('actions'), '#actions'],
          ['Data Quality Issues', $count('quality'), '#data-quality'],
          ['Documents', $count('documents'), '#documents'],
          ['Notes / Calls / Emails', $count('conversations'), '#conversations'],
      ];
      foreach ($associations as [$label, $value, $href]): ?>
        <a class="association-row" href="<?= $h($href) ?>"><span><?= $h($label) ?></span><strong><?= (int)$value ?></strong></a>
      <?php endforeach; ?>
    </div>

    <div class="panel" id="data-quality">
      <div class="panel-title"><h2>Data Quality</h2><span class="status"><?= $count('quality') ?></span></div>
      <div class="stack-list compact">
        <?php foreach (array_slice($dataQualityIssues, 0, 5) as $issue): ?><article><strong><?= $h($issue['title']) ?></strong><small><?= $h($issue['severity']) ?> · <?= $h($issue['status']) ?></small></article><?php endforeach; ?>
        <?php if (!$dataQualityIssues): ?><article class="empty-state"><strong>No open issues</strong><p>This record has no active quality blockers.</p></article><?php endif; ?>
      </div>
    </div>

    <div class="panel" id="documents">
      <div class="panel-title"><h2>Documents</h2><span class="status"><?= $count('documents') ?></span></div>
      <div class="stack-list compact">
        <?php foreach (array_slice($documents, 0, 6) as $document): ?><article><strong><?= $h($document['document_type']) ?></strong><small><?= $h($document['status']) ?> · <?= $h($document['file_name']) ?></small></article><?php endforeach; ?>
        <?php if (!$documents): ?><article class="empty-state"><strong>No documents</strong><p>Onboarding documents will appear here when requested or submitted.</p></article><?php endif; ?>
      </div>
    </div>
  </aside>
</section>
