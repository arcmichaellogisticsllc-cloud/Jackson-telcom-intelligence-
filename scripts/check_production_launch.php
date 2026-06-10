<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$strict = (getenv('JIP_STRICT_PRODUCTION') ?: '') === '1';
$failures = 0;
$warnings = 0;

$check = function (string $level, string $label, bool $ok, string $detail = '') use (&$failures, &$warnings): void {
    echo ($ok ? 'PASS ' : $level . ' ') . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL;
    if ($ok) {
        return;
    }
    if ($level === 'FAIL') {
        $failures++;
    } else {
        $warnings++;
    }
};

$db = Database::connection();
$defaultPasswordUsers = [];
foreach ($db->query('SELECT email, password_hash FROM users ORDER BY email') as $user) {
    if (password_verify('password', (string)$user['password_hash'])) {
        $defaultPasswordUsers[] = $user['email'];
    }
}
$check('FAIL', 'no users have seeded default password', !$defaultPasswordUsers, implode(', ', $defaultPasswordUsers));

$env = getenv('APP_ENV') ?: 'local';
$check($strict ? 'FAIL' : 'WARN', 'APP_ENV is production', $env === 'production', 'current=' . $env);

$httpsConfirmed = (getenv('JIP_ASSUME_HTTPS') ?: '') === '1';
$check($strict ? 'FAIL' : 'WARN', 'HTTPS/session-cookie deployment confirmed', $httpsConfirmed, 'set JIP_ASSUME_HTTPS=1 after confirming HTTPS at the web server/proxy');

$restoreMarker = __DIR__ . '/../storage/restore-tests/last_restore_verification';
$restoreRecent = is_file($restoreMarker) && (time() - filemtime($restoreMarker)) < 86400;
$check($strict ? 'FAIL' : 'WARN', 'backup restore verified in last 24 hours', $restoreRecent, 'run php scripts/verify_backup_restore.php');

$productionMarker = __DIR__ . '/../storage/production_data_mode';
$check('FAIL', 'production seed mode marker exists', is_file($productionMarker), 'run JIP_SEED_MODE=production php scripts/seed.php');

$gitDirty = null;
exec('git -C ' . escapeshellarg(__DIR__ . '/..') . ' status --porcelain 2>/dev/null', $gitOutput, $gitCode);
if ($gitCode === 0) {
    $gitDirty = count($gitOutput) > 0;
    $check($strict ? 'FAIL' : 'WARN', 'release worktree is clean', !$gitDirty, $gitDirty ? count($gitOutput) . ' changed path(s)' : '');
} else {
    $check('WARN', 'release worktree is clean', false, 'git status unavailable');
}

$writeable = is_writable(__DIR__ . '/../storage') && is_writable(__DIR__ . '/../storage/logs');
$check('FAIL', 'storage and logs are writable', $writeable);

echo PHP_EOL . 'Production launch summary: ';
if ($failures > 0) {
    echo "FAIL ({$failures} fail, {$warnings} warn)\n";
    exit(1);
}
if ($warnings > 0) {
    echo "WARN (0 fail, {$warnings} warn)\n";
    exit($strict ? 1 : 0);
}
echo "PASS\n";
