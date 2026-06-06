<section class="page-header">
  <p class="eyebrow">Relationship & Influence Intelligence</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?></p>
</section>

<section class="tabs">
  <a class="<?= !$regionName ? 'active' : '' ?>" href="/relationship-graph">National</a>
  <a class="<?= $regionName === 'Southeast' ? 'active' : '' ?>" href="/relationship-graph/southeast">Southeast</a>
  <a class="<?= $regionName === 'Great Lakes' ? 'active' : '' ?>" href="/relationship-graph/great-lakes">Great Lakes</a>
  <a class="<?= $regionName === 'Southwest' ? 'active' : '' ?>" href="/relationship-graph/southwest">Southwest</a>
</section>

<section class="metrics">
  <div><span>Critical Relationships</span><strong><?= (int)$metrics['critical'] ?></strong></div>
  <div><span>Strategic Relationships</span><strong><?= (int)$metrics['strategic'] ?></strong></div>
  <div><span>Project Managers</span><strong><?= (int)$metrics['project_managers'] ?></strong></div>
  <div><span>Open Risks</span><strong><?= (int)$metrics['open_risks'] ?></strong></div>
  <div><span>Open Actions</span><strong><?= (int)$metrics['open_actions'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Critical Influence Assets</h2><span class="status">Project, prime, utility, capacity access</span></div>
    <div class="action-stack">
      <?php foreach (array_slice($critical, 0, 8) as $profile): ?>
        <article>
          <span class="priority <?= strtolower($profile['relationship_priority']) ?>"><?= htmlspecialchars($profile['relationship_priority']) ?></span>
          <h3><a href="/contacts/detail?id=<?= (int)$profile['contact_id'] ?>"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></a></h3>
          <p><?= htmlspecialchars($profile['relationship_summary'] ?? '') ?></p>
          <small><?= htmlspecialchars($profile['organization_name'] ?? '') ?> · <?= htmlspecialchars($profile['region_name'] ?? 'National') ?> · Influence Value <?= (int)$profile['relationship_value_score'] ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$critical): ?><p>No critical relationships in this view.</p><?php endif; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Project Managers to Contact</h2><span class="status">Work access priority</span></div>
    <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Organization</th><th>Region</th><th>Next Best Action</th></tr></thead><tbody>
      <?php foreach (array_slice($projectManagers, 0, 10) as $profile): ?><tr>
        <td><a href="/contacts/detail?id=<?= (int)$profile['contact_id'] ?>"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></a></td>
        <td><?= htmlspecialchars($profile['organization_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($profile['region_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($profile['next_best_action'] ?? '') ?></td>
      </tr><?php endforeach; ?>
      <?php if (!$projectManagers): ?><tr><td colspan="4">No project manager relationships found.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Relationship Risks</h2><span class="status">Reduce access fragility</span></div>
    <div class="table-wrap"><table><thead><tr><th>Risk</th><th>Contact</th><th>Organization</th><th>Mitigation</th></tr></thead><tbody>
      <?php foreach ($risks as $risk): ?><tr>
        <td><span class="priority <?= strtolower($risk['severity']) ?>"><?= htmlspecialchars($risk['severity']) ?></span><br><?= htmlspecialchars($risk['risk_type']) ?></td>
        <td><?= htmlspecialchars(trim(($risk['first_name'] ?? '') . ' ' . ($risk['last_name'] ?? ''))) ?></td>
        <td><?= htmlspecialchars($risk['organization_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($risk['recommended_mitigation'] ?? '') ?></td>
      </tr><?php endforeach; ?>
      <?php if (!$risks): ?><tr><td colspan="4">No open relationship risks in this view.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Next Relationship Actions</h2><span class="status">Do this next</span></div>
    <div class="action-stack">
      <?php foreach ($actions as $action): ?>
        <article>
          <span class="priority high"><?= htmlspecialchars($action['action_type']) ?></span>
          <h3><?= htmlspecialchars(trim(($action['first_name'] ?? '') . ' ' . ($action['last_name'] ?? ''))) ?> · <?= htmlspecialchars($action['organization_name'] ?? '') ?></h3>
          <p><?= htmlspecialchars($action['recommended_script'] ?? '') ?></p>
          <small><?= htmlspecialchars($action['owner']) ?> · Due <?= htmlspecialchars($action['due_date'] ?? '') ?> · Influence Value <?= (int)$action['relationship_value_score'] ?></small>
          <form method="post" action="/relationship-actions/complete" class="inline-form">
            <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/relationship-graph') ?>">
            <input type="hidden" name="status" value="Completed">
            <input type="hidden" name="outcome" value="Completed from Relationship Graph.">
            <button class="link-button">Mark Complete</button>
          </form>
        </article>
      <?php endforeach; ?>
      <?php if (!$actions): ?><p>No open relationship actions in this view.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Relationships by Objective</h2><span class="status">Purpose, not contacts</span></div>
    <div class="table-wrap"><table><thead><tr><th>Relationship Objective</th><th>Count</th></tr></thead><tbody>
      <?php foreach ($objectiveRows as $row): ?><tr><td><?= htmlspecialchars($row['objective_type']) ?></td><td><?= (int)$row['count'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Aggressive Relationship Creation</h2><span class="status">Signals to convert</span></div>
    <div class="table-wrap"><table><thead><tr><th>Source</th><th>Contact</th><th>Organization</th><th>Next Action</th><th></th></tr></thead><tbody>
      <?php foreach ($signals as $signal): ?><tr>
        <td><?= htmlspecialchars($signal['source']) ?><br><small><?= htmlspecialchars($signal['region_name'] ?? '') ?> · <?= (int)$signal['confidence_score'] ?> confidence</small></td>
        <td><?= htmlspecialchars($signal['contact_name'] ?? '') ?><br><small><?= htmlspecialchars($signal['title'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($signal['organization_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($signal['recommended_next_action'] ?? '') ?></td>
        <td><?php if ($signal['status'] !== 'Contact Created'): ?><form method="post" action="/relationship-signals/convert"><input type="hidden" name="id" value="<?= (int)$signal['id'] ?>"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/relationship-graph') ?>"><button class="link-button">Create Contact</button></form><?php else: ?>Created<?php endif; ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Influence Network</h2><span class="status"><?= count($profiles) ?> relationships</span></div>
  <div class="table-wrap"><table><thead><tr><th>Contact</th><th>Organization</th><th>Role</th><th>Objective Value</th><th>Status</th><th>Next Best Action</th></tr></thead><tbody>
    <?php foreach ($profiles as $profile): ?><tr>
      <td><a href="/contacts/detail?id=<?= (int)$profile['contact_id'] ?>"><?= htmlspecialchars(trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></a></td>
      <td><a href="/organizations/detail?id=<?= (int)$profile['organization_id'] ?>"><?= htmlspecialchars($profile['organization_name'] ?? '') ?></a><br><small><?= htmlspecialchars($profile['organization_type'] ?? '') ?> · <?= htmlspecialchars($profile['region_name'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($profile['influence_role'] ?? 'Unknown') ?><br><small><?= htmlspecialchars($profile['influence_scope'] ?? '') ?></small></td>
      <td><strong><?= (int)$profile['relationship_value_score'] ?></strong><br><small><?= htmlspecialchars($profile['relationship_priority']) ?></small></td>
      <td><?= htmlspecialchars($profile['relationship_status']) ?></td>
      <td><?= htmlspecialchars($profile['next_best_action'] ?? '') ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
