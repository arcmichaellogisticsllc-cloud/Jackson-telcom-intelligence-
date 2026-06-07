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
    '/acquisition-command',
    '/acquisition-command/southeast',
    '/acquisition-command/great-lakes',
    '/acquisition-command/southwest',
    '/market-intelligence',
    '/market-intelligence/southeast',
    '/market-intelligence/great-lakes',
    '/market-intelligence/southwest',
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

$report();
exit($failed ? 1 : 0);
