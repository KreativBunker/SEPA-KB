<?php
declare(strict_types=1);

namespace App\Support;

final class App
{
    private static string $basePath;
    private static array $config = [];

    public static function init(string $basePath): void
    {
        self::$basePath = $basePath;

        self::$config = [];

        // Base config (can be overwritten by installed.lock JSON)
        $configFile = $basePath . '/config/app.php';
        if (is_file($configFile)) {
            $cfg = require $configFile;
            if (is_array($cfg)) {
                self::$config = $cfg;
            }
        }

        // Persistent config saved by setup (updates should not overwrite this file)
        $lockFile = $basePath . '/config/installed.lock';
        if (is_file($lockFile)) {
            $raw = (string)@file_get_contents($lockFile);
            $rawTrim = trim($raw);
            if ($rawTrim !== '' && str_starts_with($rawTrim, '{')) {
                $json = json_decode($rawTrim, true);
                if (is_array($json)) {
                    // lock config wins
                    self::$config = array_merge(self::$config, $json);
                }
            }
        }
    }

    public static function basePath(string $path = ''): string
    {
        return rtrim(self::$basePath . '/' . ltrim($path, '/'), '/');
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        // Environment override, useful for hosting environments
        $envKey = strtoupper($key);
        $envMap = [
            'DB_HOST' => 'db_host',
            'DB_PORT' => 'db_port',
            'DB_NAME' => 'db_name',
            'DB_USER' => 'db_user',
            'DB_PASS' => 'db_pass',
            'DB_CHARSET' => 'db_charset',
            'BASE_URL' => 'base_url',
            'APP_KEY' => 'app_key',
            'APP_DEBUG' => 'app_debug',
        ];

        foreach ($envMap as $ek => $ck) {
            if ($ck === $key) {
                $val = getenv($ek);
                if ($val !== false && $val !== '') {
                    if ($key === 'db_port') {
                        return (int)$val;
                    }
                    if ($key === 'app_debug') {
                        return in_array(strtolower((string)$val), ['1','true','yes','on'], true);
                    }
                    return $val;
                }
            }
        }

        return self::$config[$key] ?? $default;
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function isInstalled(): bool
    {
        $lock = self::basePath('config/installed.lock');
        if (!is_file($lock)) {
            return false;
        }

        // Consider installed only if DB config is available
        $hasDb = (string)self::config('db_host', '') !== ''
            && (string)self::config('db_name', '') !== ''
            && (string)self::config('db_user', '') !== '';

        return $hasDb;
    }

    public static function url(string $path = '/'): string
    {
        $base = rtrim((string)self::config('base_url', ''), '/');
        return $base . $path;
    }

    public static function nowIso(): string
    {
        return date('Y-m-d\TH:i:s');
    }

    public static function debugEnabled(): bool
    {
        if ((bool)self::config('app_debug', false)) {
            return true;
        }
        // Debug aktivieren, indem eine leere Datei unter config/debug.lock abgelegt wird
        return is_file(self::basePath('config/debug.lock'));
    }
}
