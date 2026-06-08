<section class="page-header command-page-header">
  <p class="eyebrow">Operational Maturity Engine</p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($subtitle) ?> This page enforces daily execution, weekly review, monthly strategy, and quarterly dominance cadence.</p>
</section>

<nav class="dash-tabs">
  <a class="<?= $regionId === null ? 'active' : '' ?>" href="/operating-rhythm">National</a>
  <a href="/operating-rhythm/southeast">Southeast</a>
  <a href="/operating-rhythm/great-lakes">Great Lakes</a>
  <a href="/operating-rhythm/southwest">Southwest</a>
  <a href="/daily-brief">Daily Brief</a>
  <a href="/executive-briefs">Executive Brief</a>
  <form method="post" action="/operating-rhythm/rebuild"><input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>"><button class="btn secondary">Rebuild Rhythm</button></form>
</nav>

<?php
$why = 'Operating rhythms turn intelligence into repeatable daily, weekly, monthly, and quarterly executive discipline.';
$recommended = 'Complete overdue and due reviews first, then create follow-up actions for blockers and movement signals.';
$next = 'Start a review, record decisions, identify blockers, and assign follow-up actions before leaving this page.';
$risk = 'If cadence slips, decisions become stale, accountability fades, and market pressure can move faster than Jackson.';
require __DIR__ . '/../components/action_first.php';
?>

<section class="metrics">
  <div><span>Due Today</span><strong><?= (int)$metrics['due_today'] ?></strong></div>
  <div><span>Overdue Reviews</span><strong><?= (int)$metrics['overdue'] ?></strong></div>
  <div><span>Rhythm Score</span><strong><?= (int)$metrics['avg_score'] ?></strong></div>
  <div><span>Workforce Movers</span><strong><?= (int)$metrics['workforce_movers'] ?></strong></div>
  <div><span>Pressure Spikes</span><strong><?= (int)$metrics['pressure_spikes'] ?></strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Today’s Required Reviews</h2><span class="status"><?= count($dueToday) ?> due</span></div>
    <div class="action-stack">
      <?php foreach ($dueToday as $review): ?><article>
        <span class="priority <?= $review['status'] === 'Overdue' ? 'critical' : 'high' ?>"><?= htmlspecialchars($review['status']) ?> · <?= htmlspecialchars($review['cadence']) ?></span>
        <h3><?= htmlspecialchars($review['rhythm_name']) ?></h3>
        <p><?= htmlspecialchars($review['required_sections']) ?></p>
        <small><?= htmlspecialchars($review['owner']) ?> · <?= htmlspecialchars($review['region_name'] ?? 'National') ?> · <?= htmlspecialchars($review['review_period_start']) ?></small>
        <form method="post" action="/operating-rhythm/start" class="inline-form">
          <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
          <button class="btn secondary">Start</button>
        </form>
        <details>
          <summary class="btn">Complete Review</summary>
          <form method="post" action="/operating-rhythm/complete" class="form-grid compact">
            <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <label>Owner <input name="owner" value="<?= htmlspecialchars($review['owner']) ?>"></label>
            <label>Score <input type="number" min="0" max="100" name="score" value="85"></label>
            <label class="full">Summary <textarea name="summary" required></textarea></label>
            <label class="full">Decisions Made <textarea name="decisions_made"></textarea></label>
            <label class="full">Blockers Identified <textarea name="blockers_identified"></textarea></label>
            <label>Blocker Severity <select name="blocker_severity"><option>Medium</option><option>High</option><option>Critical</option></select></label>
            <label>Follow-Up Title <input name="follow_up_title" placeholder="Optional follow-up action"></label>
            <label>Follow-Up Due <input type="date" name="follow_up_due_date" value="<?= date('Y-m-d', strtotime('+2 days')) ?>"></label>
            <label class="full">Follow-Up Next Step <textarea name="follow_up_next_step"></textarea></label>
            <button class="btn">Save Review</button>
          </form>
        </details>
      </article><?php endforeach; ?>
      <?php if (!$dueToday): ?><p>No reviews due today for this operating mode.</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Rhythm Compliance</h2><span class="status">Accountability</span></div>
    <div class="table-wrap"><table><thead><tr><th>Owner</th><th>Theater</th><th>Cadence</th><th>Score</th><th>Completion</th><th>Overdue</th></tr></thead><tbody>
      <?php foreach ($scores as $score): ?><tr>
        <td><?= htmlspecialchars($score['owner']) ?></td>
        <td><?= htmlspecialchars($score['region_name'] ?? 'National') ?></td>
        <td><?= htmlspecialchars($score['cadence']) ?></td>
        <td><span class="score <?= (int)$score['operating_rhythm_score'] >= 75 ? 'strong' : ((int)$score['operating_rhythm_score'] >= 55 ? 'stable' : 'critical') ?>"><?= (int)$score['operating_rhythm_score'] ?></span><br><small><?= htmlspecialchars($score['category']) ?></small></td>
        <td><?= (int)$score['completion_rate'] ?>%</td>
        <td><?= (int)$score['overdue_count'] ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Workforce Movement</h2><span class="status">Recruitability</span></div>
    <div class="table-wrap"><table><thead><tr><th>Person</th><th>Movement</th><th>Theater</th><th>Confidence</th><th>Recruitability</th></tr></thead><tbody>
      <?php foreach ($workforceMovers as $row): ?><tr>
        <td><?= htmlspecialchars($row['name']) ?><br><small><?= htmlspecialchars($row['role_type']) ?></small></td>
        <td><?= htmlspecialchars($row['movement_type']) ?><br><small><?= htmlspecialchars($row['previous_company']) ?> → <?= htmlspecialchars($row['new_company']) ?></small></td>
        <td><?= htmlspecialchars($row['region_name'] ?? 'National') ?></td>
        <td><?= (int)$row['confidence_score'] ?></td>
        <td><?= (int)$row['recruitability_score'] ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Competitive Pressure Spikes</h2><span class="status">Market Risk</span></div>
    <div class="table-wrap"><table><thead><tr><th>Competitor</th><th>Market</th><th>Discipline</th><th>Score</th><th>Threat</th></tr></thead><tbody>
      <?php foreach ($pressureSpikes as $row): ?><tr>
        <td><?= htmlspecialchars($row['competitor_name']) ?></td>
        <td><?= htmlspecialchars($row['market']) ?><br><small><?= htmlspecialchars($row['strategic_account']) ?></small></td>
        <td><?= htmlspecialchars($row['discipline']) ?></td>
        <td><?= (int)$row['competitive_pressure_score'] ?></td>
        <td><span class="priority <?= strtolower($row['threat_level']) ?>"><?= htmlspecialchars($row['threat_level']) ?></span></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Forecasts</h2>
    <div class="action-stack">
      <?php foreach (array_slice(array_merge($workforceForecasts, $competitorForecasts), 0, 10) as $forecast): ?><article>
        <span class="priority medium"><?= htmlspecialchars($forecast['forecast_type']) ?> · <?= (int)$forecast['forecast_score'] ?></span>
        <h3><?= htmlspecialchars($forecast['name'] ?? $forecast['competitor_name'] ?? 'Forecast') ?></h3>
        <p><?= htmlspecialchars($forecast['reason']) ?></p>
        <small><?= htmlspecialchars($forecast['recommended_action']) ?></small>
      </article><?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Win / Loss Intelligence</h2>
    <div class="table-wrap"><table><thead><tr><th>Outcome</th><th>Opportunity</th><th>Competitor</th><th>Lesson</th></tr></thead><tbody>
      <?php foreach ($winLoss as $row): ?><tr>
        <td><span class="priority <?= $row['outcome'] === 'Lost' ? 'high' : ($row['outcome'] === 'Won' ? 'low' : 'medium') ?>"><?= htmlspecialchars($row['outcome']) ?></span></td>
        <td><?= htmlspecialchars($row['opportunity_name'] ?? '') ?><br><small><?= htmlspecialchars($row['region_name'] ?? 'National') ?></small></td>
        <td><?= htmlspecialchars($row['competitor_name'] ?? 'None') ?></td>
        <td><?= htmlspecialchars($row['lesson_learned']) ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Operational Recommendations</h2><a class="btn secondary" href="/recommendations">All Recommendations</a></div>
  <div class="action-stack">
    <?php foreach ($recommendations as $rec): ?><article>
      <span class="priority <?= strtolower($rec['priority']) ?>"><?= htmlspecialchars($rec['priority']) ?> · <?= (int)$rec['priority_score'] ?></span>
      <h3><?= htmlspecialchars($rec['title']) ?></h3>
      <p><?= htmlspecialchars($rec['recommended_next_action']) ?></p>
      <small><?= htmlspecialchars($rec['assigned_owner']) ?> · <?= htmlspecialchars($rec['region_name'] ?? 'National') ?></small>
    </article><?php endforeach; ?>
  </div>
</section>
