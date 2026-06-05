<section class="page-header">
  <p class="eyebrow">Traffic Engine</p>
  <h1>Demand generation feeds acquisition.</h1>
  <p>SEO, content, contractor searches, regional pages, and outreach campaigns create signals that feed capacity, relationships, opportunities, and decision support.</p>
</section>

<section class="metrics">
  <div><span>Keywords</span><strong><?= $metrics['keywords'] ?></strong></div>
  <div><span>Content Ideas</span><strong><?= $metrics['content'] ?></strong></div>
  <div><span>Outreach Targets</span><strong><?= $metrics['targets'] ?></strong></div>
  <div><span>Sequence Steps</span><strong><?= $metrics['sequences'] ?></strong></div>
  <div><span>Demand Generation</span><strong>Live</strong></div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Keyword Intake</h2><span class="status">Traffic Score Input</span></div>
    <form method="post" action="/traffic/keywords" class="form-grid compact">
      <label class="full">Keyword <input name="keyword" required></label>
      <label>Intent <select name="intent_type"><?php foreach ($options['intentTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>State <select name="state"><option></option><?php foreach ($options['states'] as $state): ?><option><?= htmlspecialchars($state) ?></option><?php endforeach; ?></select></label>
      <label>City <input name="city"></label>
      <label>Priority <select name="priority"><?php foreach ($options['priorities'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Current Rank <input type="number" name="current_rank"></label>
      <label>Target Rank <input type="number" name="target_rank" value="3"></label>
      <label>Status <select name="status"><?php foreach ($options['keywordStatuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Search Intent Notes <textarea name="search_intent_notes"></textarea></label>
      <button class="btn">Add Keyword</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Content Idea</h2><span class="status">Demand Generation</span></div>
    <form method="post" action="/traffic/content" class="form-grid compact">
      <label class="full">Title <input name="title" required></label>
      <label>Type <select name="content_type"><?php foreach ($options['contentTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Audience <select name="audience"><?php foreach ($options['audiences'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Channel <select name="recommended_channel"><?php foreach ($options['channels'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Target Keyword <input name="target_keyword"></label>
      <label>Status <select name="status"><?php foreach ($options['contentStatuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Notes <textarea name="notes"></textarea></label>
      <button class="btn">Add Content Idea</button>
    </form>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <div class="panel-title"><h2>Outreach Target</h2><span class="status">No sending yet</span></div>
    <form method="post" action="/traffic/outreach" class="form-grid compact">
      <label>Name <input name="name" required></label>
      <label>Organization <input name="organization"></label>
      <label>Target Type <select name="target_type"><?php foreach ($options['targetTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>State <select name="state"><option></option><?php foreach ($options['states'] as $state): ?><option><?= htmlspecialchars($state) ?></option><?php endforeach; ?></select></label>
      <label>Source <select name="source"><?php foreach ($options['sources'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Status <select name="status"><?php foreach ($options['outreachStatuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Owner <input name="owner"></label>
      <label class="full">Recommended Message <textarea name="recommended_message"></textarea></label>
      <label class="full">Next Action <textarea name="next_action"></textarea></label>
      <button class="btn">Add Outreach Target</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title"><h2>Outreach Sequence Step</h2><span class="status">Planned workflow only</span></div>
    <form method="post" action="/traffic/sequences" class="form-grid compact">
      <label>Name <input name="name" required></label>
      <label>Target Type <select name="target_type"><?php foreach ($options['targetTypes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Region <select name="region_id"><?php foreach ($regions as $region): ?><option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['name']) ?></option><?php endforeach; ?></select></label>
      <label>Purpose <select name="purpose"><?php foreach ($options['purposes'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Step <input type="number" name="step_number" min="1" value="1"></label>
      <label>Channel <select name="channel"><?php foreach ($options['sequenceChannels'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label>Delay Days <input type="number" name="delay_days" min="0" value="0"></label>
      <label>Status <select name="status"><?php foreach ($options['sequenceStatuses'] as $item): ?><option><?= htmlspecialchars($item) ?></option><?php endforeach; ?></select></label>
      <label class="full">Message Template <textarea name="message_template"></textarea></label>
      <button class="btn">Add Sequence Step</button>
    </form>
  </div>
</section>

<?php
$tables = [
  ['Keywords', $keywords, ['keyword' => 'Keyword','intent_type' => 'Intent','region_name' => 'Theater','state' => 'State','city' => 'City','priority' => 'Priority','current_rank' => 'Current','target_rank' => 'Target','status' => 'Status']],
  ['Content Ideas', $contentIdeas, ['title' => 'Title','content_type' => 'Type','region_name' => 'Theater','target_keyword' => 'Keyword','audience' => 'Audience','status' => 'Status','recommended_channel' => 'Channel']],
  ['Outreach Targets', $outreachTargets, ['name' => 'Name','organization' => 'Organization','target_type' => 'Target','region_name' => 'Theater','state' => 'State','source' => 'Source','status' => 'Status','owner' => 'Owner']],
  ['Outreach Sequences', $sequences, ['name' => 'Name','target_type' => 'Target','region_name' => 'Theater','purpose' => 'Purpose','step_number' => 'Step','channel' => 'Channel','delay_days' => 'Delay','status' => 'Status']],
];
?>

<?php foreach ($tables as [$title, $rows, $columns]): ?>
  <section class="panel">
    <div class="panel-title"><h2><?= htmlspecialchars($title) ?></h2><span class="status"><?= count($rows) ?> total</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><?php foreach ($columns as $label): ?><th><?= htmlspecialchars($label) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr><?php foreach ($columns as $key => $label): ?><td><?= htmlspecialchars((string)($row[$key] ?? '')) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>
