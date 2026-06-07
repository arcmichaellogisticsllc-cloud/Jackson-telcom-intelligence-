<?php
$why = $why ?? 'This screen turns intelligence into operator action.';
$recommended = $recommended ?? 'Review the highest-priority records and decide what should move today.';
$next = $next ?? 'Open the top action, record the outcome, and create a follow-up if needed.';
?>
<section class="action-first">
  <div>
    <span>Why It Matters</span>
    <p><?= htmlspecialchars($why) ?></p>
  </div>
  <div>
    <span>Recommended Action</span>
    <p><?= htmlspecialchars($recommended) ?></p>
  </div>
  <div>
    <span>Next Step</span>
    <p><?= htmlspecialchars($next) ?></p>
  </div>
</section>
