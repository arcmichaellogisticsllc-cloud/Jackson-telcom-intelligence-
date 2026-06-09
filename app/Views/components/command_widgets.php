<?php
$widgets = $widgets ?? [];
$columns = $columns ?? 4;
?>
<section class="command-widget-grid cols-<?= (int)$columns ?>">
  <?php foreach ($widgets as $widget): ?>
    <article class="command-widget">
      <div class="panel-title">
        <div>
          <p class="eyebrow"><?= htmlspecialchars($widget['eyebrow'] ?? 'Command') ?></p>
          <h2><?= htmlspecialchars($widget['title'] ?? '') ?></h2>
        </div>
        <?php if (isset($widget['score'])): ?><span class="score stable"><?= (int)$widget['score'] ?></span><?php endif; ?>
      </div>
      <p><?= htmlspecialchars($widget['summary'] ?? '') ?></p>
      <?php if (!empty($widget['items'])): ?>
        <div class="command-items">
          <?php foreach (array_slice($widget['items'], 0, 5) as $item): ?>
            <div>
              <strong><?= htmlspecialchars($item['title'] ?? '') ?></strong>
              <span><?= htmlspecialchars($item['meta'] ?? '') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php
        $emptyTitle = $widget['empty_title'] ?? 'No real records yet';
        $emptyBody = $widget['empty_body'] ?? 'This command category has no reviewed operating records. Create or import real data before making decisions here.';
        $emptyActionHref = $widget['empty_href'] ?? ($widget['href'] ?? '');
        $emptyActionLabel = $widget['empty_action'] ?? 'Open Workspace';
        require __DIR__ . '/empty_state.php';
        ?>
      <?php endif; ?>
      <?php if (!empty($widget['href'])): ?><a class="btn secondary" href="<?= htmlspecialchars($widget['href']) ?>"><?= htmlspecialchars($widget['cta'] ?? 'Open') ?></a><?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
