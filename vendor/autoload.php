<?php
declare(strict_types=1);

// Minimaler Autoloader, vendor ist bewusst enthalten, damit das Projekt ohne Composer Deploy läuft
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $baseDir = dirname(__DIR__) . '/app/';
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
