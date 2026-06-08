<section class="page-header command-page-header"><p class="eyebrow">What Should Leaders Focus On?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/scorecards">Scorecards</a><a href="/decision-support">Decision Support</a><a href="/operating-rhythm">Operating Rhythm</a></nav>
<?php $why='Executive scorecards compress the platform into the few scores that should influence operating rhythm decisions.'; $recommended='Put the biggest blocker from the weakest scorecard into the next operating review.'; $next='Open the supporting workspace and create a follow-up action with an owner and due date.'; $risk='Without scorecards, executives browse modules instead of making the next high-leverage decision.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="command-widget-grid cols-3">
  <?php foreach ($scorecards as $card): ?>
    <a class="command-widget" href="<?= htmlspecialchars($card['href']) ?>">
      <span class="status"><?= htmlspecialchars($card['trend']) ?></span>
      <h2><?= htmlspecialchars($card['title']) ?></h2>
      <div class="metrics"><div><span>Score</span><strong><?= (int)$card['score'] ?></strong></div></div>
      <div class="command-items">
        <div><strong>Biggest Driver</strong><span><?= htmlspecialchars($card['biggest_driver']) ?></span></div>
        <div><strong>Biggest Blocker</strong><span><?= htmlspecialchars($card['biggest_blocker']) ?></span></div>
        <div><strong>Recommended Action</strong><span><?= htmlspecialchars($card['recommended_action']) ?></span></div>
      </div>
    </a>
  <?php endforeach; ?>
</section>
