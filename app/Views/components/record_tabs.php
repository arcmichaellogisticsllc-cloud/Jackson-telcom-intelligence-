<?php
$tabs = $tabs ?? ['Overview','Timeline','Conversations','Tasks / Actions','Notes','History'];
?>
<nav class="record-tabs" aria-label="Record workspace sections">
  <?php foreach ($tabs as $index => $tab): ?>
    <a class="<?= $index === 0 ? 'active' : '' ?>" href="#<?= strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tab)) ?>"><?= htmlspecialchars($tab) ?></a>
  <?php endforeach; ?>
</nav>
