<?php
$doctrineData = $doctrineData ?? ['doctrines' => [], 'health' => [], 'weak' => [], 'strong' => [], 'quarterly' => []];
$healthRows = $doctrineData['health'] ?? [];
$primaryHealth = $healthRows[0] ?? null;
?>
<section class="doctrine-panel">
  <div class="panel-title">
    <div>
      <p class="eyebrow">Executive Doctrine</p>
      <h2>Jackson operating rules</h2>
    </div>
    <?php if ($primaryHealth): ?>
      <span class="score <?= strtolower($primaryHealth['doctrine_category']) ?>"><?= (int)$primaryHealth['doctrine_compliance_score'] ?></span>
    <?php endif; ?>
  </div>
  <div class="doctrine-strip">
    <?php foreach ($doctrineData['doctrines'] as $rule): ?>
      <article>
        <span>Rule <?= (int)$rule['doctrine_order'] ?></span>
        <strong><?= htmlspecialchars($rule['doctrine_name']) ?></strong>
        <p><?= htmlspecialchars($rule['doctrine_description']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($primaryHealth): ?>
    <div class="doctrine-health">
      <div><span>Strongest Alignment</span><strong><?= htmlspecialchars($primaryHealth['strongest_alignment']) ?></strong></div>
      <div><span>Weakest Alignment</span><strong><?= htmlspecialchars($primaryHealth['weakest_alignment']) ?></strong></div>
      <div><span>Top Improvement</span><strong><?= htmlspecialchars($primaryHealth['top_improvement_area']) ?></strong></div>
    </div>
  <?php endif; ?>
</section>
