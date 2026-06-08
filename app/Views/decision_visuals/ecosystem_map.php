<section class="page-header command-page-header"><p class="eyebrow">How Does Work Flow?</p><h1><?= htmlspecialchars($title) ?></h1><p><?= htmlspecialchars($subtitle) ?></p></section>
<nav class="dash-tabs"><a href="/decision-visuals">Visual Hub</a><a class="active" href="/decision-visuals/ecosystem-map">National</a><a href="/decision-visuals/ecosystem-map?region=Southeast">Southeast</a><a href="/decision-visuals/ecosystem-map?region=Great%20Lakes">Great Lakes</a><a href="/decision-visuals/ecosystem-map?region=Southwest">Southwest</a></nav>
<?php $why='Ecosystem maps show how utilities, engineering firms, primes, subcontractors, capacity providers, and influencers create access to work.'; $recommended='Strengthen high-confidence edges and verify weak edges before relying on them in pursuit decisions.'; $next='Pick one weak edge and create a relationship action or communication follow-up.'; $risk='If Jackson does not understand the work flow, competitors can control access before bids appear.'; require __DIR__ . '/../components/action_first.php'; ?>
<section class="panel">
  <div class="panel-title"><h2><?= $region ? htmlspecialchars($region) . ' ' : '' ?>Ecosystem Edges</h2><a class="btn secondary" href="/network-intelligence">Open Network Intelligence</a></div>
  <div class="command-items">
    <?php foreach ($ecosystemEdges as $edge): ?>
      <div>
        <strong><?= htmlspecialchars($edge['from_name']) ?> → <?= htmlspecialchars($edge['to_name']) ?></strong>
        <span><?= htmlspecialchars($edge['relationship_type']) ?> · <?= htmlspecialchars($edge['region_name'] ?? 'National') ?> · strength <?= (int)$edge['strength_score'] ?> · confidence <?= (int)$edge['confidence_score'] ?> · verified <?= htmlspecialchars($edge['last_verified_at'] ?: 'Unknown') ?></span>
        <small><?= htmlspecialchars($edge['recommended_action']) ?></small>
      </div>
    <?php endforeach; ?>
    <?php if (!$ecosystemEdges): ?><div><strong>No ecosystem edges found</strong><span>Run seed or executive operating rebuild to populate network intelligence.</span></div><?php endif; ?>
  </div>
</section>
