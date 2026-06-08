<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', ['error' => null]);
    }

    public function login(): void
    {
        if (!$this->canAttemptLogin()) {
            Audit::log('failed_login', 'user', null, 'Denied', 'Login throttled for ' . ($_POST['email'] ?? 'unknown'));
            $this->view('auth/login', ['error' => 'Too many failed attempts. Wait a few minutes and try again.']);
            return;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$_POST['email'] ?? '']);
        $user = $stmt->fetch();

        if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            $this->recordFailedAttempt();
            Audit::log('failed_login', 'user', null, 'Denied', 'Failed login for ' . ($_POST['email'] ?? 'unknown'));
            $this->view('auth/login', ['error' => 'Invalid login.']);
            return;
        }

        unset($_SESSION['login_attempts']);
        session_regenerate_id(true);
        Auth::login($user);
        Audit::log('login', 'user', (int)$user['id']);
        $this->redirect('/');
    }

    public function logout(): void
    {
        Audit::log('logout', 'user', Auth::user()['id'] ?? null);
        Auth::logout();
        $this->redirect('/login');
    }

    public function showResetRequest(): void
    {
        $this->view('auth/password_reset_request', ['message' => null, 'devToken' => null]);
    }

    public function requestReset(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $devToken = null;
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)')
                ->execute([(int)$user['id'], $hash, $expires, $_SERVER['REMOTE_ADDR'] ?? null]);
            Audit::log('password_reset_requested', 'user', (int)$user['id']);
            if ((getenv('APP_ENV') ?: 'local') !== 'production') {
                $devToken = $token;
                $dir = __DIR__ . '/../../storage/logs';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($dir . '/password_resets.log', date('c') . ' ' . $email . ' ' . $token . PHP_EOL, FILE_APPEND);
            }
        }
        $this->view('auth/password_reset_request', [
            'message' => 'If the account exists, a reset token has been created. Production requires a configured mailer.',
            'devToken' => $devToken,
        ]);
    }

    public function showResetForm(): void
    {
        $this->view('auth/password_reset_confirm', ['error' => null, 'token' => $_GET['token'] ?? '']);
    }

    public function resetPassword(): void
    {
        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 10) {
            $this->view('auth/password_reset_confirm', ['error' => 'Use at least 10 characters.', 'token' => $token]);
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare('SELECT prt.*, u.email FROM password_reset_tokens prt JOIN users u ON u.id = prt.user_id WHERE prt.token_hash = ? AND prt.used_at IS NULL AND prt.expires_at > CURRENT_TIMESTAMP');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            Audit::log('password_reset_failed', 'user', null, 'Denied', 'Invalid or expired token');
            $this->view('auth/password_reset_confirm', ['error' => 'Invalid or expired token.', 'token' => '']);
            return;
        }
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['user_id']]);
        $db->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$row['id']]);
        Audit::log('password_reset_completed', 'user', (int)$row['user_id']);
        $this->view('auth/login', ['error' => 'Password reset complete. Log in with the new password.']);
    }

    private function canAttemptLogin(): bool
    {
        $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first_at' => time()];
        if ((time() - (int)$attempts['first_at']) > 300) {
            $_SESSION['login_attempts'] = ['count' => 0, 'first_at' => time()];
            return true;
        }
        return (int)$attempts['count'] < 5;
    }

    private function recordFailedAttempt(): void
    {
        $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first_at' => time()];
        if ((time() - (int)$attempts['first_at']) > 300) {
            $attempts = ['count' => 0, 'first_at' => time()];
        }
        $attempts['count'] = (int)$attempts['count'] + 1;
        $_SESSION['login_attempts'] = $attempts;
    }
}
