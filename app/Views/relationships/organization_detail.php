<section class="page-header">
  <p class="eyebrow">Influence Account</p>
  <h1><?= htmlspecialchars($organization['name']) ?></h1>
  <p><?= htmlspecialchars($organization['type']) ?> · <?= htmlspecialchars($organization['region_name'] ?? '') ?> · <?= htmlspecialchars($organization['city'] ?? '') ?> <?= htmlspecialchars($organization['state'] ?? '') ?></p>
</section>

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
