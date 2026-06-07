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
      <?php endif; ?>
      <?php if (!empty($widget['href'])): ?><a class="btn secondary" href="<?= htmlspecialchars($widget['href']) ?>"><?= htmlspecialchars($widget['cta'] ?? 'Open') ?></a><?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
