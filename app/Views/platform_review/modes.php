<section class="page-header">
  <p class="eyebrow">Operator Modes</p>
  <h1>Five ways to operate without module overload.</h1>
  <p>Modes simplify the platform by matching each operator intent to one screen, one workflow, and one set of metrics.</p>
</section>

<nav class="dash-tabs">
  <a href="/operating-view">Executive Operating View</a>
  <a href="/platform-review">Platform Health</a>
  <a class="active" href="/operator-modes">Operator Modes</a>
</nav>

<section class="region-grid">
  <?php foreach ($modes as $mode): ?>
    <article class="panel">
      <p class="eyebrow"><?= htmlspecialchars($mode['primary_screen']) ?></p>
      <h2><?= htmlspecialchars($mode['mode_name']) ?></h2>
      <p><?= htmlspecialchars($mode['objective']) ?></p>
      <p><strong>Focus:</strong> <?= htmlspecialchars($mode['focus_categories']) ?></p>
      <p><strong>Metrics:</strong> <?= htmlspecialchars($mode['top_metrics']) ?></p>
      <p><?= htmlspecialchars($mode['recommended_workflow']) ?></p>
    </article>
  <?php endforeach; ?>
</section>
