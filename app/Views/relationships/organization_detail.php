<?php
$recordEyebrow = 'Account Workspace';
$recordName = $organization['name'];
$recordType = $organization['type'];
$recordRegion = $organization['region_name'] ?? 'National';
$recordOwner = 'Unassigned';
$recordPrimaryOwner = $organization['primary_owner'] ?? $recordOwner;
$recordSecondaryOwner = $organization['secondary_owner'] ?? '';
$recordSharedOwnerFlag = (int)($organization['shared_owner_flag'] ?? 0);
$recordOwnershipNotes = $organization['ownership_notes'] ?? '';
$recordStatus = $organization['status'] ?? 'Active';
$recordScore = count($profiles) ? max(array_map(fn($row) => (int)$row['relationship_value_score'], $profiles)) : 0;
$recordNextAction = count($profiles) ? ($profiles[0]['next_best_action'] ?? 'Review influence map and assign next relationship action.') : 'Create first relationship profile or contact.';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Owner','Mark Reviewed'];
$recordEntityType = 'organization';
$recordEntityId = (int)$organization['id'];
$recordRegionId = (int)($organization['region_id'] ?? 0);
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Contacts / People','Conversations','Opportunities / Pursuits','Tasks / Actions','Notes','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$why = 'This organization may hold work, capacity, influence, or relationships that affect Jackson growth.';
$recommended = $recordNextAction ?: 'Review the influence map and assign the next owner action.';
$next = 'Log the next conversation or create a follow-up tied to the organization.';
$risk = 'If ownership and follow-up are unclear, influence can stay concentrated or go stale.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="grid two">
  <div class="panel">
    <h2>Organization Context</h2>
    <div class="table-wrap"><table><tbody>
      <tr><th>Website</th><td><?= htmlspecialchars($organization['website'] ?? '') ?></td></tr>
      <tr><th>Phone</th><td><?= htmlspecialchars($organization['phone'] ?? '') ?></td></tr>
      <tr><th>Status</th><td><?= htmlspecialchars($organization['status'] ?? '') ?></td></tr>
      <tr><th>Notes</th><td><?= htmlspecialchars($organization['notes'] ?? '') ?></td></tr>
    </tbody></table></div>
  </div>
  <div class="panel">
    <h2>Relationship Risk</h2>
    <div class="table-wrap"><table><thead><tr><th>Risk</th><th>Contact</th><th>Mitigation</th></tr></thead><tbody>
      <?php foreach ($risks as $risk): ?><tr><td><span class="priority <?= strtolower($risk['severity']) ?>"><?= htmlspecialchars($risk['severity']) ?></span><br><?= htmlspecialchars($risk['risk_type']) ?></td><td><?= htmlspecialchars(trim(($risk['first_name'] ?? '') . ' ' . ($risk['last_name'] ?? ''))) ?></td><td><?= htmlspecialchars($risk['recommended_mitigation'] ?? '') ?></td></tr><?php endforeach; ?>
      <?php if (!$risks): ?><tr><td colspan="3">No open influence risks.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>

<section class="panel">
  <div class="panel-title"><h2>Influence Map</h2><span class="status"><?= count($profiles) ?> relationships</span></div>
  <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Role</th><th>Influence Value</th><th>Priority</th><th>Status</th><th>Next Best Action</th></tr></thead><tbody>
    <?php foreach ($profiles as $profile): ?><tr>
      <td><a href="/contacts/detail?id=<?= (int)$profile['contact_id'] ?>"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></a><br><small><?= htmlspecialchars($profile['title'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($profile['influence_role'] ?? 'Unknown') ?></td>
      <td><strong><?= (int)$profile['relationship_value_score'] ?></strong></td>
      <td><?= htmlspecialchars($profile['relationship_priority']) ?></td>
      <td><?= htmlspecialchars($profile['relationship_status']) ?></td>
      <td><?= htmlspecialchars($profile['next_best_action'] ?? '') ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$profiles): ?><tr><td colspan="6">No relationship profiles generated for this organization yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
