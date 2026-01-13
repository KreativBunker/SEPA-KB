<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Support\App;
use App\Support\Auth;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            header('Location: ' . App::url('/login'));
            exit;
        }
    }
}
