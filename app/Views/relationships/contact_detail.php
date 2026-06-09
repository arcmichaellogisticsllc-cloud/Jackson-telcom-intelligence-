<?php
$recordEyebrow = 'Relationship Workspace';
$recordName = trim($contact['first_name'] . ' ' . $contact['last_name']);
$recordType = $contact['title'] ?: 'Contact';
$recordRegion = $contact['region_name'] ?? 'National';
$recordOwner = $contact['relationship_owner'] ?? 'Unassigned';
$recordStatus = $contact['relationship_status'] ?? $contact['relationship_strength'] ?? 'Unknown';
$recordScore = (int)($contact['relationship_value_score'] ?? 0);
$recordNextAction = $contact['next_best_action'] ?? $contact['next_action'] ?? '';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Relationship Action','Mark Reviewed'];
$recordEntityType = 'contact';
$recordEntityId = (int)$contact['id'];
$recordRegionId = (int)($contact['region_id'] ?? 0);
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Conversations','People','Tasks / Actions','Notes','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$what = 'This is a relationship asset, not a passive contact record.';
$why = $contact['relationship_summary'] ?? 'This contact may create work, capacity, influence, or market intelligence.';
$recommended = $recordNextAction ?: 'Confirm the relationship objective and create the next relationship action.';
$next = 'Use Add Note, Log Call, Draft Email, or Create Follow-Up to move the relationship forward.';
$risk = 'If this relationship sits without action, Jackson may lose project access, capacity access, or market intelligence.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Influence Value</span><strong><?= (int)($contact['relationship_value_score'] ?? 0) ?></strong><small><?= htmlspecialchars($contact['relationship_priority'] ?? '') ?></small></div>
  <div><span>Relationship Status</span><strong><?= htmlspecialchars($contact['relationship_status'] ?? 'Unknown') ?></strong></div>
  <div><span>Influence Level</span><strong><?= htmlspecialchars($contact['influence_level'] ?? '') ?></strong></div>
  <div><span>Relationship Strength</span><strong><?= htmlspecialchars($contact['relationship_strength'] ?? '') ?></strong></div>
  <div><span>Owner</span><strong><?= htmlspecialchars($contact['relationship_owner'] ?? '') ?></strong></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="grid two">
  <div class="panel">
    <h2>Why This Relationship Matters</h2>
    <p><?= htmlspecialchars($contact['relationship_summary'] ?? 'No relationship profile generated yet.') ?></p>
    <p><strong>Next Best Action:</strong> <?= htmlspecialchars($contact['next_best_action'] ?? '') ?></p>
    <div class="table-wrap"><table><tbody>
      <tr><th>Email</th><td><?= htmlspecialchars($contact['email'] ?? '') ?></td></tr>
      <tr><th>Phone</th><td><?= htmlspecialchars($contact['phone'] ?? '') ?></td></tr>
      <tr><th>Organization Type</th><td><?= htmlspecialchars($contact['organization_type'] ?? '') ?></td></tr>
      <tr><th>Last Contact</th><td><?= htmlspecialchars($contact['last_contact_date'] ?? '') ?></td></tr>
      <tr><th>Current Next Action</th><td><?= htmlspecialchars($contact['next_action'] ?? '') ?></td></tr>
    </tbody></table></div>
  </div>
  <div class="panel">
    <h2>Influence Roles</h2>
    <div class="table-wrap"><table><thead><tr><th>Role</th><th>Scope</th><th>Notes</th></tr></thead><tbody>
      <?php foreach ($detail['roles'] as $role): ?><tr><td><?= htmlspecialchars($role['influence_role']) ?></td><td><?= htmlspecialchars($role['influence_scope']) ?></td><td><?= htmlspecialchars($role['influence_notes'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Relationship Objectives</h2>
    <div class="table-wrap"><table><thead><tr><th>Objective</th><th>Priority</th><th>Status</th><th>Notes</th></tr></thead><tbody>
      <?php foreach ($detail['objectives'] as $objective): ?><tr><td><?= htmlspecialchars($objective['objective_type']) ?></td><td><?= htmlspecialchars($objective['priority']) ?></td><td><?= htmlspecialchars($objective['status']) ?></td><td><?= htmlspecialchars($objective['notes'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <h2>Win Conditions</h2>
    <div class="table-wrap"><table><thead><tr><th>Win</th><th>Status</th><th>Date</th><th>Notes</th></tr></thead><tbody>
      <?php foreach ($detail['wins'] as $win): ?><tr><td><?= htmlspecialchars($win['win_type']) ?></td><td><?= htmlspecialchars($win['win_status']) ?></td><td><?= htmlspecialchars($win['win_date'] ?? '') ?></td><td><?= htmlspecialchars($win['win_notes'] ?? '') ?></td></tr><?php endforeach; ?>
      <?php if (!$detail['wins']): ?><tr><td colspan="4">No win condition recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Relationship Risks</h2>
    <div class="table-wrap"><table><thead><tr><th>Risk</th><th>Severity</th><th>Mitigation</th></tr></thead><tbody>
      <?php foreach ($detail['risks'] as $risk): ?><tr><td><?= htmlspecialchars($risk['risk_type']) ?><br><small><?= htmlspecialchars($risk['reason'] ?? '') ?></small></td><td><?= htmlspecialchars($risk['severity']) ?></td><td><?= htmlspecialchars($risk['recommended_mitigation'] ?? '') ?></td></tr><?php endforeach; ?>
      <?php if (!$detail['risks']): ?><tr><td colspan="3">No open risks.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <h2>Relationship Actions</h2>
    <div class="action-stack">
      <?php foreach ($detail['actions'] as $action): ?><article>
        <span class="priority high"><?= htmlspecialchars($action['action_type']) ?></span>
        <h3><?= htmlspecialchars($action['status']) ?> · Due <?= htmlspecialchars($action['due_date'] ?? '') ?></h3>
        <p><?= htmlspecialchars($action['recommended_script'] ?? '') ?></p>
        <small><?= htmlspecialchars($action['owner']) ?></small>
      </article><?php endforeach; ?>
      <?php if (!$detail['actions']): ?><p>No relationship actions yet.</p><?php endif; ?>
    </div>
  </div>
</section>
