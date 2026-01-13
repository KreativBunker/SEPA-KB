<?php
declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf'];
    }

    /**
     * Wenn ohne Parameter aufgerufen, verhält es sich wie vorher: bei ungültigem Token wird 400 ausgegeben und abgebrochen.
     * Wenn ein Token übergeben wird, wird nur true/false zurückgegeben (kein exit).
     */
    public static function check(?string $token = null): bool
    {
        $exitOnFail = ($token === null);

        if ($token === null) {
            $token = $_POST['_csrf'] ?? '';
        }

        $sess = (string)($_SESSION['_csrf'] ?? '');
        $ok = is_string($token) && $token !== '' && $sess !== '' && hash_equals($sess, $token);

        if (!$ok && $exitOnFail) {
            http_response_code(400);
            echo "Ungültige Anfrage.";
            exit;
        }

        return $ok;
    }
}
