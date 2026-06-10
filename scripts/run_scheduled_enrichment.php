<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Services\ScheduledEnrichmentService;

$options = [
    'dry_run' => false,
    'due' => false,
    'backfill' => false,
    'cadence' => null,
    'source_id' => null,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif ($arg === '--due') {
        $options['due'] = true;
    } elseif ($arg === '--backfill') {
        $options['backfill'] = true;
    } elseif (str_starts_with($arg, '--cadence=')) {
        $cadence = strtolower(substr($arg, 10));
        if (!in_array($cadence, ['daily', 'weekly', 'monthly', 'quarterly'], true)) {
            fwrite(STDERR, "Invalid cadence. Use daily, weekly, monthly, or quarterly.\n");
            exit(1);
        }
        $options['cadence'] = $cadence;
    } elseif (str_starts_with($arg, '--source=')) {
        $sourceId = (int)substr($arg, 9);
        if ($sourceId <= 0) {
            fwrite(STDERR, "Invalid source id.\n");
            exit(1);
        }
        $options['source_id'] = $sourceId;
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        usage();
        exit(1);
    }
}

if (!$options['source_id'] && !$options['cadence']) {
    $options['due'] = true;
}

$service = new ScheduledEnrichmentService();
$results = $service->run($options);

echo ($options['dry_run'] ? "DRY RUN " : "") . "Scheduled enrichment\n";
echo "Mode: " . ($options['source_id'] ? 'source=' . $options['source_id'] : ($options['cadence'] ? 'cadence=' . $options['cadence'] : 'due')) . PHP_EOL;
echo "Sources: " . count($results) . PHP_EOL;

if (!$results) {
    echo "No enrichment sources matched. Run migrations and production seed if this is a new database.\n";
    exit(0);
}

$totals = ['manual_tasks' => 0, 'review_items' => 0, 'data_quality_issues' => 0];
foreach ($results as $result) {
    $line = sprintf(
        '- #%d %s: %s',
        (int)($result['source_id'] ?? 0),
        (string)($result['source_name'] ?? 'Unknown source'),
        (string)($result['status'] ?? 'Unknown')
    );
    if (!empty($result['would_create_manual_task'])) {
        $line .= ' | would create manual task';
    }
    if (!empty($result['would_create_data_quality_issue'])) {
        $line .= ' | would create data quality issue';
    }
    if (isset($result['manual_tasks'])) {
        $line .= ' | manual_tasks=' . (int)$result['manual_tasks'];
        $totals['manual_tasks'] += (int)$result['manual_tasks'];
    }
    if (isset($result['review_items'])) {
        $line .= ' | review_items=' . (int)$result['review_items'];
        $totals['review_items'] += (int)$result['review_items'];
    }
    if (isset($result['data_quality_issues'])) {
        $line .= ' | data_quality_issues=' . (int)$result['data_quality_issues'];
        $totals['data_quality_issues'] += (int)$result['data_quality_issues'];
    }
    if (!empty($result['next_run_at'])) {
        $line .= ' | next=' . $result['next_run_at'];
    }
    if (!empty($result['error'])) {
        $line .= ' | error=' . $result['error'];
    }
    echo $line . PHP_EOL;
}

if (!$options['dry_run']) {
    echo "Created/reused: manual_tasks={$totals['manual_tasks']} review_items={$totals['review_items']} data_quality_issues={$totals['data_quality_issues']}\n";
    echo "Live web fetch adapters are intentionally not used locally. Review tasks keep enrichment gated until source evidence is verified.\n";
}

function usage(): void
{
    echo "Usage: php scripts/run_scheduled_enrichment.php [--due] [--source=<id>] [--cadence=daily|weekly|monthly|quarterly] [--dry-run] [--backfill]\n";
}
