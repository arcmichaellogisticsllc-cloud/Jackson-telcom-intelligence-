<?php
$what = $what ?? null;
$why = $why ?? 'This screen turns intelligence into operator action.';
$recommended = $recommended ?? 'Review the highest-priority records and decide what should move today.';
$next = $next ?? 'Open the top action, record the outcome, and create a follow-up if needed.';
$risk = $risk ?? 'If no action is taken, useful intelligence can become stale, capacity can move elsewhere, and relationship momentum can decay.';
?>
<section class="action-first">
  <?php if ($what !== null): ?>
  <div>
    <span>What This Is</span>
    <p><?= htmlspecialchars($what) ?></p>
  </div>
  <div>
    <span>Why It Matters</span>
    <p><?= htmlspecialchars($why) ?></p>
  </div>
  <?php else: ?>
  <div>
    <span>Why It Matters</span>
    <p><?= htmlspecialchars($why) ?></p>
  </div>
  <div>
    <span>Recommended Action</span>
    <p><?= htmlspecialchars($recommended) ?></p>
  </div>
  <?php endif; ?>
  <div>
    <span>Next Step</span>
    <p><?= htmlspecialchars($next) ?></p>
  </div>
  <div>
    <span>Risk of Inaction</span>
    <p><?= htmlspecialchars($risk) ?></p>
  </div>
</section>
