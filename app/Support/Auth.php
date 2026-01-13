<?php
declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function login(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'email' => (string)$user['email'],
            'role' => (string)$user['role'],
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }

    public static function role(): string
    {
        return (string)($_SESSION['user']['role'] ?? '');
    }
}
