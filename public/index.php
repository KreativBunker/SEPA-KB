<?php
declare(strict_types=1);

session_start();

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    echo 'PHP 8.0 oder höher wird benötigt. Aktuell: ' . htmlspecialchars(PHP_VERSION);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

use App\Support\App;
use App\Support\Router;
use App\Support\Logger;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

App::init($basePath);

$router = new Router();

// Install guard
$installed = App::isInstalled();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Shared Hosting Fallback: /index.php/login soll wie /login behandelt werden
$path = $_SERVER['PATH_INFO'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (is_string($path) && str_starts_with($path, '/index.php')) {
    $path = substr($path, strlen('/index.php'));
}
if ($path === '' || $path === false || $path === null) {
    $path = '/';
}

if (!$installed && !str_starts_with($path, '/setup')) {
    header('Location: ' . App::url('/setup'));
    exit;
}

// Routes
require $basePath . '/config/routes.php';

try {
    $router->dispatch($method, $path);
} catch (Throwable $e) {
    http_response_code(500);
Logger::error('Unhandled exception', $e);

$debug = App::debugEnabled();
if ($debug) {
    echo "<pre>" . htmlspecialchars((string)$e) . "</pre>";
    echo "<p>Log Datei: " . htmlspecialchars(App::basePath('storage/logs/app.log')) . "</p>";
    exit;
}

echo "Ein Fehler ist aufgetreten. Debug aktivieren: Datei config/debug.lock anlegen oder app_debug in config/app.php auf true setzen.";
}
