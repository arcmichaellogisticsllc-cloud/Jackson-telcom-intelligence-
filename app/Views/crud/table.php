<?php
$listEyebrow = 'Clean List View';
$listTitle = ucfirst(str_replace('_', ' ', $resource));
$listStatuses = match ($resource) {
    'contacts' => ['Cold','Developing','Warm','Strong'],
    'organizations' => ['Active','Needs Review','Inactive'],
    'subcontractors' => ['Prospect','Researching','Qualified','Documents Requested','Compliance Review','Approved','Preferred','Strategic Partner','Inactive','Rejected'],
    'opportunities' => ['Intelligence','Qualified','Pursuit','Proposal','Negotiation','Awarded','Lost'],
    default => ['Open','Active','Completed','Dismissed'],
};
require __DIR__ . '/../components/list_toolbar.php';
$recordHref = function (array $row) use ($resource, $recordType): string {
    return match ($resource) {
        'contacts' => '/contacts/detail?id=' . (int)$row['id'],
        'organizations' => '/organizations/detail?id=' . (int)$row['id'],
        'subcontractors' => '/subcontractor-acquisition/detail?id=' . (int)$row['id'],
        'opportunities' => '/pursuits/detail?id=' . (int)$row['id'],
        default => '/record?type=' . rawurlencode((string)($recordType ?? rtrim($resource, 's'))) . '&id=' . (int)$row['id'],
    };
};
$recordName = function (array $row) use ($resource): string {
    return match ($resource) {
        'contacts' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Contact #' . ($row['id'] ?? ''),
        'organizations' => (string)($row['name'] ?? 'Organization #' . ($row['id'] ?? '')),
        'subcontractors' => (string)($row['company_name'] ?: ($row['organization_name'] ?? 'Subcontractor #' . ($row['id'] ?? ''))),
        'opportunities' => (string)($row['name'] ?? 'Opportunity #' . ($row['id'] ?? '')),
        default => (string)($row['name'] ?? $row['title'] ?? 'Record #' . ($row['id'] ?? '')),
    };
};
$recordTypeLabel = function (array $row) use ($resource): string {
    return match ($resource) {
        'contacts' => (string)($row['title'] ?? 'Contact'),
        'organizations' => (string)($row['type'] ?? 'Organization'),
        'subcontractors' => (string)($row['services_offered'] ?? 'Subcontractor'),
        'opportunities' => (string)($row['opportunity_type'] ?? 'Opportunity'),
        default => ucfirst(rtrim($resource, 's')),
    };
};
$recordOwner = function (array $row) use ($resource): string {
    return match ($resource) {
        'contacts' => (string)($row['relationship_owner'] ?? 'Unassigned'),
        'subcontractors' => (string)($row['owner_name'] ?? 'Unassigned'),
        'opportunities' => (string)($row['owner'] ?? 'Unassigned'),
        default => (string)($row['owner'] ?? 'Unassigned'),
    };
};
$recordStatus = function (array $row) use ($resource): string {
    return match ($resource) {
        'contacts' => (string)($row['relationship_strength'] ?? 'Unknown'),
        'subcontractors' => (string)($row['approval_stage'] ?? 'Prospect'),
        'opportunities' => (string)($row['stage'] ?? 'Intelligence'),
        default => (string)($row['status'] ?? 'Active'),
    };
};
$recordScore = function (array $row) use ($resource): string {
    return match ($resource) {
        'subcontractors' => (string)($row['performance_score'] ?? $row['available_crew_count'] ?? 0),
        'opportunities' => (string)($row['pursuit_score'] ?? $row['probability'] ?? 0),
        default => (string)($row['score'] ?? ''),
    };
};
$recordNextAction = function (array $row) use ($resource): string {
    return match ($resource) {
        'contacts' => (string)($row['next_action'] ?? 'Create relationship follow-up.'),
        'opportunities' => (string)($row['next_action'] ?? 'Review pursuit decision.'),
        'subcontractors' => 'Review qualification, compliance, and capacity readiness.',
        'organizations' => (string)($row['notes'] ?? 'Map contacts and assign next relationship action.'),
        default => (string)($row['next_action'] ?? 'Review record.'),
    };
};
$lastActivity = fn(array $row): string => substr((string)($row['updated_at'] ?? $row['created_at'] ?? ''), 0, 10);
?>
<section class="panel">
  <div class="panel-title"><h2>Operator Work Queue</h2><span class="status"><?= count($rows) ?> shown</span></div>
  <div class="table-wrap"><table class="operator-table"><thead><tr><th>Record</th><th>Region</th><th>Owner</th><th>Status</th><th>Score</th><th>Last Activity</th><th>Next Action</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr>
      <td><a href="<?= htmlspecialchars($recordHref($row)) ?>"><strong><?= htmlspecialchars($recordName($row)) ?></strong></a><br><small><?= htmlspecialchars($recordTypeLabel($row)) ?></small></td>
      <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td>
      <td><?= htmlspecialchars($recordOwner($row)) ?></td>
      <td><span class="status"><?= htmlspecialchars($recordStatus($row)) ?></span></td>
      <td><?= htmlspecialchars($recordScore($row)) ?></td>
      <td><?= htmlspecialchars($lastActivity($row) ?: 'No activity') ?></td>
      <td><?= htmlspecialchars($recordNextAction($row)) ?></td>
      <td class="row-actions">
        <a class="link-button" href="<?= htmlspecialchars($recordHref($row)) ?>">Open</a>
        <a class="link-button" href="/record?type=<?= htmlspecialchars($recordType ?? rtrim($resource, 's')) ?>&id=<?= (int)$row['id'] ?>">Timeline</a>
        <form method="post" action="/delete" onsubmit="return confirm('Delete this record?')"><input type="hidden" name="resource" value="<?= htmlspecialchars($resource) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="link-button">Delete</button></form>
      </td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8"><?php $emptyTitle = 'No real records match this view'; $emptyBody = 'Clear filters, add a real record, or run a reviewed connector to populate this work queue.'; $emptyActionHref = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; $emptyActionLabel = 'Clear Filters'; require __DIR__ . '/../components/empty_state.php'; ?></td></tr><?php endif; ?>
  </tbody></table></div>
</section>
