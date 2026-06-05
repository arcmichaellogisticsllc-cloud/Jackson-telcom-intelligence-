<?php

namespace App\Controllers;

use App\Core\Auth;
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
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$_POST['email'] ?? '']);
        $user = $stmt->fetch();

        if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            $this->view('auth/login', ['error' => 'Invalid login. Use seeded credentials from the README.']);
            return;
        }

        Auth::login($user);
        $this->redirect('/');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}

