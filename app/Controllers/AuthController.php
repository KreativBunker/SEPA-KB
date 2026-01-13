<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class AuthController
{
    public function showLogin(): void
    {
        if (!App::isInstalled()) {
            header('Location: ' . App::url('/setup'));
            exit;
        }

        if (Auth::check()) {
            header('Location: ' . App::url('/'));
            exit;
        }

        View::render('login', [
            'csrf' => Csrf::token(),
            'messages' => Flash::all(),
        ]);
    }

    public function login(): void
    {
        Csrf::check();

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            Flash::add('error', 'Bitte E Mail und Passwort eingeben.');
            header('Location: ' . App::url('/login'));
            exit;
        }

        $repo = new UserRepository();
        $user = $repo->findByEmail($email);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            Flash::add('error', 'Login fehlgeschlagen.');
            header('Location: ' . App::url('/login'));
            exit;
        }

        Auth::login($user);
        $repo->updateLastLogin((int)$user['id']);

        header('Location: ' . App::url('/'));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: ' . App::url('/login'));
        exit;
    }
}
