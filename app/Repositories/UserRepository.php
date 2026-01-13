<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $st->execute(['email' => $email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        $pdo = Db::pdo();
        return $pdo->query('SELECT id,email,role,is_active,last_login_at,created_at FROM users ORDER BY id DESC')->fetchAll();
    }

    public function create(string $email, string $hash, string $role): int
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT INTO users (email,password_hash,role) VALUES (:email,:hash,:role)');
        $st->execute(['email' => $email, 'hash' => $hash, 'role' => $role]);
        return (int)$pdo->lastInsertId();
    }

    public function updateLastLogin(int $id): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $st->execute(['id' => $id, 'hash' => $hash]);
    }

    public function delete(int $id): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $st->execute(['id' => $id]);
    }
}
