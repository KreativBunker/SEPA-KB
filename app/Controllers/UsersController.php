<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class UsersController
{
    public function index(): void
    {
        $users = (new UserRepository())->all();
        View::render('users/index', [
            'csrf' => Csrf::token(),
            'users' => $users,
            'messages' => Flash::all(),
        ]);
    }

    public function create(): void
    {
        View::render('users/create', [
            'csrf' => Csrf::token(),
            'messages' => Flash::all(),
        ]);
    }

    public function store(): void
    {
        Csrf::check();

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'staff');
        if (!in_array($role, ['admin','staff','viewer'], true)) {
            $role = 'staff';
        }

        if ($email === '' || $password === '') {
            Flash::add('error', 'Bitte Email und Passwort setzen.');
            header('Location: ' . App::url('/users/create'));
            exit;
        }

        $repo = new UserRepository();
        try {
            $repo->create($email, password_hash($password, PASSWORD_DEFAULT), $role);
            Flash::add('success', 'Nutzer erstellt.');
            header('Location: ' . App::url('/users'));
            exit;
        } catch (\Throwable $e) {
            Flash::add('error', 'Fehler: ' . $e->getMessage());
            header('Location: ' . App::url('/users/create'));
            exit;
        }
    }

    public function resetPassword(array $params): void
    {
        Csrf::check();

        $id = (int)($params['id'] ?? 0);
        $pw = (string)($_POST['new_password'] ?? '');
        if ($pw === '') {
            Flash::add('error', 'Passwort fehlt.');
            header('Location: ' . App::url('/users'));
            exit;
        }

        (new UserRepository())->updatePassword($id, password_hash($pw, PASSWORD_DEFAULT));
        Flash::add('success', 'Passwort gesetzt.');
        header('Location: ' . App::url('/users'));
        exit;
    }

    public function delete(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);
        (new UserRepository())->delete($id);
        Flash::add('success', 'Nutzer gelöscht.');
        header('Location: ' . App::url('/users'));
        exit;
    }
}
