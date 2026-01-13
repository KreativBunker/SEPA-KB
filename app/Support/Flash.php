<?php
declare(strict_types=1);

namespace App\Support;

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function all(): array
    {
        $msgs = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $msgs;
    }
}
