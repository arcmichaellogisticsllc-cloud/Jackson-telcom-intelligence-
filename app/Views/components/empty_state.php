<?php
$emptyTitle = $emptyTitle ?? 'No real records yet';
$emptyBody = $emptyBody ?? 'Add real operating data, run a connector, or create the first reviewed record for this workspace.';
$emptyActionHref = $emptyActionHref ?? '';
$emptyActionLabel = $emptyActionLabel ?? '';
?>
<article class="empty-state">
  <h3><?= htmlspecialchars($emptyTitle) ?></h3>
  <p><?= htmlspecialchars($emptyBody) ?></p>
  <?php if ($emptyActionHref && $emptyActionLabel): ?>
    <a class="btn secondary" href="<?= htmlspecialchars($emptyActionHref) ?>"><?= htmlspecialchars($emptyActionLabel) ?></a>
  <?php endif; ?>
</article>
