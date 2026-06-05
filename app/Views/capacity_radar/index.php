<section class="page-header">
  <p class="eyebrow">Capacity Radar</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p>Can we execute? Capacity Radar tracks internal Jackson capacity and external subcontractor, vendor, equipment, and specialty-provider capacity before pursuit decisions are made.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= !$region ? 'active' : '' ?>" href="/capacity-radar">National</a>
  <a class="<?= ($region['name'] ?? '') === 'Southeast' ? 'active' : '' ?>" href="/capacity-radar/southeast">Southeast</a>
  <a class="<?= ($region['name'] ?? '') === 'Great Lakes' ? 'active' : '' ?>" href="/capacity-radar/great-lakes">Great Lakes</a>
  <a class="<?= ($region['name'] ?? '') === 'Southwest' ? 'active' : '' ?>" href="/capacity-radar/southwest">Southwest</a>
</nav>

<section class="metrics">
  <div><span>Critical Gaps</span><strong><?= count(array_filter($gaps, fn($gap) => $gap['severity'] === 'Critical')) ?></strong></div>
  <div><span>High Gaps</span><strong><?= count(array_filter($gaps, fn($gap) => $gap['severity'] === 'High')) ?></strong></div>
  <div><span>Predictive Gaps</span><strong><?= count(array_filter($gaps, fn($gap) => $gap['predictive_30_gap'] > 0 || $gap['predictive_60_gap'] > 0)) ?></strong></div>
  <div><span>Capacity Providers</span><strong><?= count($trustedProviders) ?></strong></div>
  <div><span>Blocking Pursuits</span><strong><?= count(array_filter($gaps, fn($gap) => in_array($gap['severity'], ['Critical','High'], true))) ?></strong></div>
</section>

<?php if (!$region): ?>
<section class="panel">
  <div class="panel-title"><h2>Capacity by Region</h2><form method="post" action="/capacity-radar/rebuild"><input type="hidden" name="return_to" value="/capacity-radar"><button class="btn secondary">Recalculate Radar</button></form></div>
  <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Providers</th><th>Available Now</th><th>30 Days</th><th>60 Days</th></tr></thead><tbody>
    <?php foreach ($regionSummary as $row): ?><tr><td><?= htmlspecialchars($row['region_name']) ?></td><td><?= (int)$row['providers'] ?></td><td><?= (int)$row['available_now'] ?></td><td><?= (int)$row['available_30_days'] ?></td><td><?= (int)$row['available_60_days'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Discipline Status</h2><span class="status">Deployable Capacity</span></div>
    <div class="table-wrap"><table><thead><tr><th>Discipline</th><th>Total</th><th>Now</th><th>30 Days</th><th>60 Days</th></tr></thead><tbody>
      <?php foreach ($disciplineSummary as $row): ?><tr><td><?= htmlspecialchars($row['discipline']) ?></td><td><?= (int)$row['total_crews'] ?></td><td><?= (int)$row['available_now'] ?></td><td><?= (int)$row['available_30_days'] ?></td><td><?= (int)$row['available_60_days'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Equipment Summary</h2><span class="status">Count Only</span></div>
    <div class="gap-list">
      <?php foreach ($equipmentSummary as $item): ?><div><span><?= htmlspecialchars($item['equipment_type']) ?></span><strong><?= (int)$item['count'] ?></strong></div><?php endforeach; ?>
      <?php if (!$equipmentSummary): ?><p>No equipment capacity recorded.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Capacity Gaps</h2><span class="status">Reactive and Predictive</span></div>
  <div class="table-wrap"><table><thead><tr><th>Severity</th><th>Theater</th><th>Market</th><th>Discipline</th><th>Now</th><th>30-Day</th><th>60-Day</th><th>Can We Execute?</th></tr></thead><tbody>
    <?php foreach ($gaps as $gap): ?><tr>
      <td><span class="priority <?= strtolower($gap['severity']) ?>"><?= htmlspecialchars($gap['severity']) ?></span></td>
      <td><?= htmlspecialchars($gap['region_name']) ?></td>
      <td><?= htmlspecialchars($gap['market']) ?></td>
      <td><strong><?= htmlspecialchars($gap['discipline']) ?></strong><br><small><?= htmlspecialchars($gap['strategic_notes']) ?></small></td>
      <td><?= (int)$gap['current_available'] ?> / <?= (int)$gap['target_now'] ?><br><small>Gap <?= (int)$gap['reactive_gap'] ?></small></td>
      <td><?= (int)$gap['available_30_days'] ?> / <?= (int)$gap['target_30_days'] ?><br><small>Gap <?= (int)$gap['predictive_30_gap'] ?></small></td>
      <td><?= (int)$gap['available_60_days'] ?> / <?= (int)$gap['target_60_days'] ?><br><small>Gap <?= (int)$gap['predictive_60_gap'] ?></small></td>
      <td><?= in_array($gap['severity'], ['Critical','High'], true) ? 'Blocking Pursuits' : 'Executable / Monitor' ?></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Preferred Network by Discipline</h2><span class="status">Prospect to Strategic Partner</span></div>
  <div class="table-wrap"><table><thead><tr><th>Theater</th><th>Discipline</th><th>Network Level</th><th>Subcontractors</th><th>Available Crews</th></tr></thead><tbody>
    <?php foreach ($networkSummary as $row): ?><tr><td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($row['discipline']) ?></td><td><?= htmlspecialchars($row['network_level']) ?></td><td><?= (int)$row['subcontractors'] ?></td><td><?= (int)$row['available_crews'] ?></td></tr><?php endforeach; ?>
    <?php if (!$networkSummary): ?><tr><td colspan="5">No subcontractor network capacity linked to Capacity Radar yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Top Trusted Capacity Providers</h2><span class="status">Trust Score</span></div>
    <div class="table-wrap"><table><thead><tr><th>Provider</th><th>Type</th><th>Theater</th><th>Readiness</th><th>Trust</th><th>Available</th></tr></thead><tbody>
      <?php foreach ($trustedProviders as $provider): ?><tr><td><strong><?= htmlspecialchars($provider['profile_name']) ?></strong><br><small><?= htmlspecialchars($provider['status']) ?></small></td><td><?= htmlspecialchars($provider['profile_type']) ?></td><td><?= htmlspecialchars($provider['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($provider['primary_mobilization_readiness']) ?><br><small><?= (int)$provider['max_travel_radius_miles'] ?> mi</small></td><td><?= (int)$provider['trust_score'] ?><br><small><?= htmlspecialchars($provider['trust_category']) ?></small></td><td><?= (int)$provider['available_now'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Recommended Recruiting Actions</h2><a class="btn secondary" href="/recommendations">All Actions</a></div>
    <div class="action-stack">
      <?php foreach (array_slice(array_filter($gaps, fn($gap) => $gap['reactive_gap'] > 0 || $gap['predictive_30_gap'] > 0 || $gap['predictive_60_gap'] > 0), 0, 8) as $gap): ?>
        <article><span class="priority <?= strtolower($gap['severity']) ?>"><?= htmlspecialchars($gap['severity']) ?></span><h3>Recruit <?= max($gap['reactive_gap'], $gap['predictive_30_gap'], $gap['predictive_60_gap']) ?> <?= htmlspecialchars($gap['discipline']) ?> crews in <?= htmlspecialchars($gap['region_name']) ?></h3><p>Target <?= htmlspecialchars($gap['market']) ?> capacity before pursuit commitments.</p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Signal / Target / Hunt Integration</h2><span class="status">Capacity Inputs</span></div>
    <h3>Escalate or Hunt Signals</h3>
    <div class="table-wrap"><table><tbody><?php foreach ($relatedSignals as $signal): ?><tr><td><span class="priority <?= strtolower($signal['classification'] ?? 'watch') ?>"><?= htmlspecialchars($signal['classification'] ?? 'Watch') ?></span></td><td><a href="/record?type=signal&id=<?= (int)$signal['id'] ?>"><?= htmlspecialchars($signal['title']) ?></a><br><small><?= htmlspecialchars($signal['region_name'] ?? '') ?> · suggest CapacityProfile review</small></td></tr><?php endforeach; ?></tbody></table></div>
    <h3>Capacity Targets</h3>
    <div class="table-wrap"><table><tbody><?php foreach ($relatedTargets as $target): ?><tr><td><?= htmlspecialchars($target['target_type']) ?></td><td><a href="/targets/detail?id=<?= (int)$target['id'] ?>"><?= htmlspecialchars($target['target_name']) ?></a><br><small>Potential capacity contribution score <?= (int)$target['capacity_value_score'] ?></small></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
  <div class="panel">
    <div class="panel-title"><h2>Capacity Hunts</h2><a class="btn secondary" href="/hunts">Hunt Command</a></div>
    <div class="table-wrap"><table><thead><tr><th>Hunt</th><th>Theater</th><th>Goal</th><th>Targets</th></tr></thead><tbody><?php foreach ($relatedHunts as $hunt): ?><tr><td><?= htmlspecialchars($hunt['hunt_name']) ?></td><td><?= htmlspecialchars($hunt['region_name'] ?? 'National') ?></td><td><?= (int)$hunt['target_count_goal'] ?></td><td><?= (int)$hunt['target_count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>
</section>
