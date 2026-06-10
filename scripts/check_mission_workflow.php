<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;
use App\Services\OperatingWorkflowService;

session_start();
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Admin',
    'email' => 'admin@jacksontelcom.com',
    'role' => 'Admin',
    'region_id' => null,
];

$router = require __DIR__ . '/../routes/web.php';
$routes = (function () use ($router): array {
    $ref = new ReflectionClass($router);
    $prop = $ref->getProperty('routes');
    $prop->setAccessible(true);
    return $prop->getValue($router);
})();

$service = new OperatingWorkflowService();
$queues = $service->commandCenterQueues([]);
$checks = [];
$failures = 0;

$check = function (string $label, bool $ok, string $detail = '') use (&$checks, &$failures): void {
    $checks[] = ($ok ? 'PASS ' : 'FAIL ') . $label . ($detail !== '' ? ' - ' . $detail : '');
    if (!$ok) {
        $failures++;
    }
};

$mission = $queues['mission'] ?? [];
foreach (['work' => 'Acquire Work', 'capacity' => 'Acquire Capacity', 'influence' => 'Acquire Influence', 'revenue' => 'Convert To Revenue'] as $key => $title) {
    $lane = $mission[$key] ?? null;
    $check("mission lane exists: {$title}", is_array($lane) && ($lane['title'] ?? '') === $title);
    $check("mission lane has count: {$title}", isset($lane['count']) && is_numeric($lane['count']));
    $check("mission lane has items array: {$title}", isset($lane['items']) && is_array($lane['items']));
    foreach (array_slice($lane['items'] ?? [], 0, 5) as $item) {
        $check("mission item has status: {$title}", trim((string)($item['status'] ?? '')) !== '', (string)($item['title'] ?? ''));
        $check("mission item has next action: {$title}", trim((string)($item['next_action'] ?? '')) !== '', (string)($item['title'] ?? ''));
        $href = (string)($item['href'] ?? '');
        $path = parse_url($href, PHP_URL_PATH) ?: '';
        $check("mission item link route exists: {$title}", $path !== '' && isset($routes['GET'][$path]), $href);
    }
}

foreach (['capture','review','quality','onboarding','documents','actions','decisions','handoff'] as $queueName) {
    $check("workflow queue exists: {$queueName}", isset($queues[$queueName]) && is_array($queues[$queueName]));
}

$requiredGetRoutes = [
    '/',
    '/harvesters',
    '/production-readiness',
    '/onboarding/subcontractors',
    '/onboarding/subcontractors/detail',
    '/decision-support',
    '/executive-packages/detail',
    '/pursuits/detail',
    '/preconstruction/detail',
    '/syncerp-integration/detail',
];
foreach ($requiredGetRoutes as $route) {
    $check("required GET route exists: {$route}", isset($routes['GET'][$route]));
}

$requiredPostRoutes = [
    '/production-readiness/review',
    '/production-readiness/data-quality/update',
    '/onboarding/intake-link',
    '/daily-actions/complete',
    '/daily-actions/dismiss',
    '/daily-actions/follow-up',
    '/preconstruction/create',
    '/executive-packages/status',
    '/syncerp-integration/rebuild',
];
foreach ($requiredPostRoutes as $route) {
    $check("required POST route exists: {$route}", isset($routes['POST'][$route]));
}

$db = Database::connection();
$realTables = [
    'work records' => 'SELECT COUNT(*) FROM opportunities',
    'capacity records' => 'SELECT COUNT(*) FROM subcontractors',
    'influence records' => 'SELECT COUNT(*) FROM relationship_intelligence_profiles',
    'revenue conversion records' => 'SELECT COUNT(*) FROM project_packages',
];
foreach ($realTables as $label => $sql) {
    $count = (int)$db->query($sql)->fetchColumn();
    $check("{$label} clean empty state allowed", $count >= 0, (string)$count);
}

ob_start();
$router->dispatch('GET', '/');
$html = ob_get_clean();
$check('Command Center renders mission language', str_contains($html, 'Acquire Work') && str_contains($html, 'Acquire Capacity') && str_contains($html, 'Acquire Influence') && str_contains($html, 'Convert To Revenue'));
$check('Command Center renders operating loop', str_contains($html, 'Operating Loop') && str_contains($html, 'What Needs To Move Next'));

foreach ($checks as $line) {
    echo $line . PHP_EOL;
}
echo "\nMission workflow summary: " . ($failures ? 'FAIL' : 'PASS') . "\n";
exit($failures ? 1 : 0);
