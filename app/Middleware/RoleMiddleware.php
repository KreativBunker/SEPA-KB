<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\Auth;

final class RoleMiddleware
{
    public function __construct(private array $roles)
    {
    }

    public function handle(): void
    {
        $role = Auth::role();
        if ($role === '') {
            http_response_code(403);
            echo "Nicht erlaubt.";
            exit;
        }
        if (!in_array($role, $this->roles, true)) {
            http_response_code(403);
            echo "Nicht erlaubt.";
            exit;
        }
    }
}
