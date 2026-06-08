<section class="page-header command-page-header">
  <p class="eyebrow">Executive Brief Engine</p>
  <h1>Generated briefs. Display only.</h1>
  <p>Daily, weekly, monthly, and quarterly strategic briefs generated from executive packages. Nothing is sent automatically.</p>
</section>

<nav class="dash-tabs">
  <a href="/executive-packages">Packages</a>
  <a class="active" href="/executive-briefs">Briefs</a>
  <a href="/strategic-review">Strategic Review</a>
  <a href="/executive-os">Executive OS</a>
</nav>

<section class="grid two">
  <?php foreach ($briefs as $brief): ?>
    <article class="panel">
      <div class="panel-title">
        <div><p class="eyebrow"><?= htmlspecialchars($brief['brief_type']) ?></p><h2><?= htmlspecialchars($brief['brief_title']) ?></h2></div>
        <span class="status"><?= htmlspecialchars($brief['status']) ?></span>
      </div>
      <p><?= htmlspecialchars($brief['brief_summary']) ?></p>
      <h3>Top Actions</h3><pre class="brief-block"><?= htmlspecialchars($brief['top_actions']) ?></pre>
      <h3>Top Risks</h3><pre class="brief-block"><?= htmlspecialchars($brief['top_risks']) ?></pre>
      <h3>Top Opportunities</h3><pre class="brief-block"><?= htmlspecialchars($brief['top_opportunities']) ?></pre>
      <h3>Strategic Recommendations</h3><pre class="brief-block"><?= htmlspecialchars($brief['strategic_recommendations']) ?></pre>
    </article>
  <?php endforeach; ?>
</section>
