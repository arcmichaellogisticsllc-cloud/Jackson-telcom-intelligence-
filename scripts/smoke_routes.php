<?php

require __DIR__ . '/../vendor_autoload.php';

session_start();
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Admin',
    'email' => 'admin@jacksontelcom.com',
    'role' => 'Admin',
    'region_id' => null,
];

$router = require __DIR__ . '/../routes/web.php';
$routes = [
    '/operating-view',
    '/platform-review',
    '/operator-modes',
    '/operating-rhythm',
    '/operating-rhythm/southeast',
    '/operating-rhythm/great-lakes',
    '/operating-rhythm/southwest',
    '/production-readiness',
    '/data-quality',
    '/connector-runs',
    '/audit-logs',
    '/password-reset',
    '/password-reset/confirm',
    '/decision-visuals',
    '/decision-visuals/regional-dominance',
    '/decision-visuals/work-vs-capacity',
    '/decision-visuals/account-health',
    '/decision-visuals/ecosystem-map',
    '/decision-visuals/ecosystem-map?region=Southeast',
    '/decision-visuals/ecosystem-map?region=Great%20Lakes',
    '/decision-visuals/ecosystem-map?region=Southwest',
    '/decision-visuals/capacity-heatmap',
    '/decision-visuals/workforce-heatmap',
    '/decision-visuals/competitive-pressure',
    '/decision-visuals/forecasts',
    '/decision-visuals/opportunity-flow',
    '/decision-visuals/scorecards',
    '/workspace/work',
    '/workspace/capacity',
    '/workspace/relationships',
    '/workspace/market',
    '/workspace/growth',
    '/workspace/onboarding',
    '/workspace/operations',
    '/workspace/system',
    '/workspace/search?q=Comcast',
    '/real-intelligence',
    '/real-intelligence/dataset?dataset=strategic_accounts',
    '/real-intelligence/dataset?dataset=organizations',
    '/real-intelligence/dataset?dataset=capacity_providers',
    '/real-intelligence/dataset?dataset=opportunities',
    '/real-intelligence/detail?id=1',
    '/executive-packages',
    '/executive-packages/southeast',
    '/executive-packages/great-lakes',
    '/executive-packages/southwest',
    '/executive-packages/detail?id=1',
    '/executive-briefs',
    '/executive-os',
    '/communications',
    '/network-intelligence',
    '/ecosystem/southeast',
    '/ecosystem/great-lakes',
    '/ecosystem/southwest',
    '/forecasts',
    '/ownership',
    '/ownership-matrix',
    '/strategic-accounts',
    '/strategic-review',
    '/strategic-account-intelligence',
    '/strategic-account-intelligence/southeast',
    '/strategic-account-intelligence/great-lakes',
    '/strategic-account-intelligence/southwest',
    '/strategic-account-intelligence/detail?id=1',
    '/workforce-intelligence',
    '/competitive-intelligence',
    '/onboarding',
    '/onboarding/subcontractors',
    '/onboarding/workforce',
    '/onboarding/strategic-accounts',
    '/onboarding/markets',
    '/onboarding/documents',
    '/onboarding/reviews',
    '/onboarding/metrics',
    '/onboarding/intake?token=invalid',
    '/acquisition-command',
    '/acquisition-command/southeast',
    '/acquisition-command/great-lakes',
    '/acquisition-command/southwest',
    '/market-intelligence',
    '/market-intelligence/southeast',
    '/market-intelligence/great-lakes',
    '/market-intelligence/southwest',
    '/syncerp-integration',
    '/syncerp-integration/southeast',
    '/syncerp-integration/great-lakes',
    '/syncerp-integration/southwest',
    '/syncerp-integration/detail?id=1',
    '/syncerp-handoff-brief',
    '/',
    '/briefing',
    '/decision-support',
    '/pursuits',
    '/pursuits/southeast',
    '/pursuits/great-lakes',
    '/pursuits/southwest',
    '/pursuits/detail?id=1',
    '/preconstruction',
    '/preconstruction/southeast',
    '/preconstruction/great-lakes',
    '/preconstruction/southwest',
    '/preconstruction/detail?id=1',
    '/warehouse',
    '/warehouse/southeast',
    '/warehouse/great-lakes',
    '/warehouse/southwest',
    '/warehouse/brief',
    '/outreach',
    '/outreach/southeast',
    '/outreach/great-lakes',
    '/outreach/southwest',
    '/outreach/detail?id=1',
    '/harvesters',
    '/signals',
    '/escalations',
    '/watchlists',
    '/targets',
    '/hunting-lists',
    '/hunts',
    '/playbooks',
    '/capacity-radar',
    '/subcontractor-acquisition',
    '/relationship-graph',
    '/demand',
    '/traffic',
    '/organizations',
    '/contacts',
    '/opportunities',
    '/recommendations',
    '/activities',
    '/settings',
];

$failed = 0;
$results = [];
$reported = false;
$report = function () use (&$results, &$failed, &$reported): void {
    if ($reported) {
        return;
    }
    $reported = true;
    foreach ($results as $result) {
        echo $result . PHP_EOL;
    }
    echo "\nRoute smoke summary: " . ($failed ? 'FAIL' : 'PASS') . "\n";
};
register_shutdown_function($report);
foreach ($routes as $route) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $route;
    $_GET = [];
    parse_str((string)(parse_url($route, PHP_URL_QUERY) ?: ''), $_GET);
    http_response_code(200);
    ob_start();
    try {
        $router->dispatch('GET', $route);
        $body = ob_get_clean();
        $code = http_response_code() ?: 200;
        $ok = $code >= 200 && $code < 400 && !str_contains($body, 'Fatal error');
        $results[] = ($ok ? 'PASS' : 'FAIL') . " {$code} {$route}";
        if (!$ok) {
            $failed++;
        }
    } catch (Throwable $e) {
        ob_end_clean();
        $failed++;
        $results[] = 'FAIL 500 ' . $route . ' - ' . $e->getMessage();
    }
}

foreach (['scripts/backup_database.php','scripts/restore_database.php','scripts/validate_erp_contract.php','scripts/purge_demo_data.php'] as $script) {
    if (is_file(__DIR__ . '/../' . $script)) {
        $results[] = 'PASS script exists ' . $script;
        continue;
    }
    $failed++;
    $results[] = 'FAIL script missing ' . $script;
}

$report();
exit($failed ? 1 : 0);
