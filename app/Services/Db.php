<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\App;
use PDO;
use PDOException;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $host = (string)App::config('db_host');
        $port = (int)App::config('db_port', 3306);
        $db = (string)App::config('db_name');
        $user = (string)App::config('db_user');
        $pass = (string)App::config('db_pass');
        $charset = (string)App::config('db_charset', 'utf8mb4');

        if ($host === '' || $db === '' || $user === '') {
            throw new PDOException('DB config fehlt');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pdo = $pdo;
        return $pdo;
    }

    public static function connectForSetup(string $host, int $port, string $user, string $pass, string $charset = 'utf8mb4'): PDO
    {
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
