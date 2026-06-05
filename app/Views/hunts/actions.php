<section class="page-header">
  <p class="eyebrow">Today's Hunt Actions</p>
  <h1>What do we do next?</h1>
  <p>The working screen for Mike, Ron, Admin, and future regional owners. No outreach is sent from this screen.</p>
</section>

<nav class="dash-tabs">
  <a href="/hunt-actions">National</a>
  <a href="/hunt-actions?region=southeast">Mike: Southeast</a>
  <a href="/hunt-actions?region=great-lakes">Ron: Great Lakes</a>
  <a href="/hunt-actions?region=southwest">Southwest</a>
</nav>

<section class="panel">
  <div class="panel-title"><h2>Open Hunt Tasks</h2><span class="status"><?= count($tasks) ?> actions</span></div>
  <div class="table-wrap"><table><thead><tr><th>Target</th><th>Hunt</th><th>Current Step</th><th>Channel</th><th>Instructions</th><th>Questions</th><th>Due</th><th>Owner</th><th>Complete</th></tr></thead><tbody>
    <?php foreach ($tasks as $task): ?><tr>
      <td><strong><?= htmlspecialchars($task['target_name']) ?></strong><br><small><?= htmlspecialchars($task['target_type']) ?> · <?= htmlspecialchars($task['region_name']) ?></small></td>
      <td><?= htmlspecialchars($task['hunt_name']) ?></td>
      <td><?= htmlspecialchars($task['step_name'] ?: $task['task_title']) ?></td>
      <td><?= htmlspecialchars($task['channel'] ?: $task['task_type']) ?></td>
      <td><?= htmlspecialchars($task['instructions']) ?><br><small><?= htmlspecialchars($task['opening_script']) ?></small></td>
      <td><?= nl2br(htmlspecialchars($task['qualification_questions'] ?? '')) ?></td>
      <td><?= htmlspecialchars($task['due_date']) ?></td>
      <td><?= htmlspecialchars($task['owner']) ?></td>
      <td><form method="post" action="/hunt-tasks/complete" class="form-card"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><textarea name="outcome_notes" placeholder="Outcome notes"></textarea><button class="btn secondary">Complete</button></form></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
