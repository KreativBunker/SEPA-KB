<?php
declare(strict_types=1);

// Minimaler Autoloader fuer App-Klassen
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

// Composer-Autoloader fuer externe Bibliotheken (TCPDF etc.)
require_once __DIR__ . '/composer/autoload_real.php';
ComposerAutoloaderInit1ba944b1b2880695113851e981334169::getLoader();
