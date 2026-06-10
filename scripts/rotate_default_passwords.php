<?php

require __DIR__ . '/../vendor_autoload.php';

use App\Core\Database;

$db = Database::connection();
$users = $db->query('SELECT id, email FROM users ORDER BY id')->fetchAll();
$rotated = [];

foreach ($users as $user) {
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
    $hash = (string)$stmt->fetchColumn();
    if (!password_verify('password', $hash)) {
        continue;
    }
    $password = generatePassword();
    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NULL WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
    $rotated[] = [$user['email'], $password];
}

if (!$rotated) {
    echo "PASS no users were using the seeded default password.\n";
    exit(0);
}

$dir = __DIR__ . '/../storage/secrets';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
$file = $dir . '/rotated_passwords_' . date('Ymd_His') . '.txt';
$lines = [
    'Jackson Intelligence Platform one-time passwords',
    'Generated: ' . date('c'),
    'Use once, then operators must change password at login.',
    '',
];
foreach ($rotated as [$email, $password]) {
    $lines[] = $email . ' ' . $password;
}
file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
chmod($file, 0600);

echo "PASS rotated " . count($rotated) . " default password(s).\n";
echo "PASS one-time password file: {$file}\n";

function generatePassword(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < 20; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
