<?php
$recordEyebrow = 'Strategic Account Workspace';
$recordName = $account['account_name'];
$recordType = $account['account_type'];
$recordRegion = $account['region_name'] ?? 'National';
$recordOwner = $account['owner'] ?? $account['primary_owner'] ?? 'Unassigned';
$recordStatus = $account['account_status'] ?? 'Active';
$recordScore = (int)$account['strategic_score'];
$recordNextAction = $account['recommended_action'] ?? $account['next_best_action'] ?? '';
$recordActions = ['Add Note','Log Call','Draft Email','Create Follow-Up','Assign Owner','Assign Relationship Action','Mark Reviewed'];
$recordEntityType = 'strategic_account';
$recordEntityId = (int)$account['id'];
$recordRegionId = (int)($account['region_id'] ?? 0);
require __DIR__ . '/../components/record_header.php';
$tabs = ['Overview','Timeline','Contacts / People','Conversations','Opportunities / Pursuits','Capacity','Tasks / Actions','Notes','History'];
require __DIR__ . '/../components/record_tabs.php';
?>

<?php
$what = 'This is a strategic account workspace for account coverage, influence, opportunities, and capacity demand.';
$why = $account['notes'] ?? 'This account can create work, access, demand, or capacity pressure in a Jackson theater.';
$recommended = $recordNextAction ?: 'Strengthen account coverage and confirm the next relationship action.';
$next = 'Log account activity or create a relationship follow-up for the next owner action.';
$risk = 'If account coverage stays thin, competitors can control project access before Jackson is positioned.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Relationship Health</span><strong><?= (int)($account['relationship_health_score'] ?? $account['relationship_coverage_score']) ?></strong></div>
  <div><span>Opportunity Score</span><strong><?= (int)($account['opportunity_score'] ?? $account['opportunity_volume_score']) ?></strong></div>
  <div><span>Capacity Demand</span><strong><?= (int)$account['capacity_demand_score'] ?></strong></div>
  <div><span>Influence Coverage</span><strong><?= (int)$account['influence_coverage_score'] ?></strong></div>
  <div><span>Recent Signals</span><strong><?= (int)($account['recent_signal_count'] ?? 0) ?></strong></div>
</section>

<section class="grid two">
  <div class="panel" id="overview">
    <h2>Why This Account Matters</h2>
    <p><?= htmlspecialchars($account['notes'] ?? 'Strategic account intelligence profile.') ?></p>
    <p><strong>Recommended action:</strong> <?= htmlspecialchars($recordNextAction) ?></p>
    <p><strong>Market:</strong> <?= htmlspecialchars($account['market'] ?? '') ?></p>
  </div>
  <div class="panel" id="contacts-people">
    <div class="panel-title"><h2>Key People</h2><span class="status"><?= count($contacts) ?></span></div>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Title</th><th>Email / Phone</th><th>Next Action</th></tr></thead><tbody>
      <?php foreach ($contacts as $contact): ?><tr><td><a href="/contacts/detail?id=<?= (int)$contact['id'] ?>"><?= htmlspecialchars(trim($contact['first_name'] . ' ' . $contact['last_name'])) ?></a></td><td><?= htmlspecialchars($contact['title'] ?? '') ?></td><td><?= htmlspecialchars($contact['email'] ?? '') ?><br><small><?= htmlspecialchars($contact['phone'] ?? '') ?></small></td><td><?= htmlspecialchars($contact['next_action'] ?? '') ?></td></tr><?php endforeach; ?>
      <?php if (!$contacts): ?><tr><td colspan="4">No linked people found yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel" id="opportunities-pursuits">
  <div class="panel-title"><h2>Opportunities / Pursuits</h2><a class="btn secondary" href="/pursuits">Pursuit Board</a></div>
  <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Stage</th><th>Value</th><th>Next Action</th></tr></thead><tbody>
    <?php foreach ($opportunities as $opportunity): ?><tr><td><a href="/pursuits/detail?id=<?= (int)$opportunity['id'] ?>"><?= htmlspecialchars($opportunity['name']) ?></a></td><td><?= htmlspecialchars($opportunity['stage']) ?></td><td>$<?= number_format((float)$opportunity['estimated_value']) ?></td><td><?= htmlspecialchars($opportunity['next_action'] ?? '') ?></td></tr><?php endforeach; ?>
    <?php if (!$opportunities): ?><tr><td colspan="4">No linked opportunities found yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<?php require __DIR__ . '/../components/recent_conversations.php'; ?>
<?php require __DIR__ . '/../components/intelligence_timeline.php'; ?>
