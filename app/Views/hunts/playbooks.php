<section class="page-header">
  <p class="eyebrow">Playbooks</p>
  <h1>Repeatable acquisition execution.</h1>
  <p>Playbooks define the steps, questions, disqualification rules, documents, and conversion goals used inside Hunts.</p>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Create Playbook</h2>
    <form method="post" action="/playbooks" class="form-grid compact">
      <label>Playbook Name <input name="playbook_name" required></label>
      <label>Type <select name="playbook_type"><?php foreach ($options['playbookTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Target Type <select name="target_type"><?php foreach ($options['targetTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Theater <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Conversion Goal <select name="conversion_goal"><?php foreach ($options['conversionGoals'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Objective <textarea name="objective"></textarea></label>
      <label class="full">Opening Script <textarea name="opening_script"></textarea></label>
      <label class="full">Qualification Questions <textarea name="qualification_questions"></textarea></label>
      <label class="full">Disqualification Rules <textarea name="disqualification_rules"></textarea></label>
      <label class="full">Required Documents <textarea name="required_documents"></textarea></label>
      <button class="btn">Create Playbook</button>
    </form>
  </div>
  <div class="panel">
    <h2>Add Playbook Step</h2>
    <form method="post" action="/playbook-steps" class="form-grid compact">
      <label>Playbook <select name="playbook_id"><?php foreach ($playbooks as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['playbook_name']) ?></option><?php endforeach; ?></select></label>
      <label>Step Number <input type="number" name="step_number" value="1"></label>
      <label>Step Name <input name="step_name" required></label>
      <label>Channel <select name="channel"><?php foreach ($options['channels'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Delay Days <input type="number" name="delay_days" value="0"></label>
      <label><input type="checkbox" name="creates_task" checked> Creates Task</label>
      <label class="full">Instructions <textarea name="instructions"></textarea></label>
      <label class="full">Expected Outcome <textarea name="expected_outcome"></textarea></label>
      <label class="full">Required Before Next Step <textarea name="required_before_next_step"></textarea></label>
      <button class="btn">Add Step</button>
    </form>
  </div>
</section>

<section class="panel">
  <div class="panel-title"><h2>Playbooks</h2><span class="status"><?= count($playbooks) ?> total</span></div>
  <div class="table-wrap"><table><thead><tr><th>Playbook</th><th>Type</th><th>Target</th><th>Theater</th><th>Goal</th><th>Steps</th><th>Opening Script</th></tr></thead><tbody>
    <?php foreach ($playbooks as $p): ?><tr><td><strong><?= htmlspecialchars($p['playbook_name']) ?></strong></td><td><?= htmlspecialchars($p['playbook_type']) ?></td><td><?= htmlspecialchars($p['target_type']) ?></td><td><?= htmlspecialchars($p['region_name'] ?? 'National') ?></td><td><?= htmlspecialchars($p['conversion_goal']) ?></td><td><?= (int)$p['step_count'] ?></td><td><?= htmlspecialchars($p['opening_script']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<section class="panel">
  <h2>Playbook Steps</h2>
  <div class="table-wrap"><table><thead><tr><th>Playbook</th><th>Step</th><th>Channel</th><th>Instructions</th><th>Expected Outcome</th></tr></thead><tbody>
    <?php foreach ($steps as $s): ?><tr><td><?= htmlspecialchars($s['playbook_name']) ?></td><td><?= (int)$s['step_number'] ?>. <?= htmlspecialchars($s['step_name']) ?></td><td><?= htmlspecialchars($s['channel']) ?></td><td><?= htmlspecialchars($s['instructions']) ?></td><td><?= htmlspecialchars($s['expected_outcome']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
