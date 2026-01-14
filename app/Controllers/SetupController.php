<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;
use App\Services\Db;

final class SetupController
{
    public function show(): void
    {
        // If fully installed, go to login
        if (App::isInstalled()) {
            header('Location: ' . App::url('/login'));
            exit;
        }

        View::render('setup', [
            'csrf' => Csrf::token(),
            'defaults' => [
                'db_host' => 'localhost',
                'db_port' => 3306,
                'db_charset' => 'utf8mb4',
                'base_url' => $this->guessBaseUrl(),
                'sevdesk_base_url' => 'https://my.sevdesk.de/api/v1',
                'default_days_until_collection' => 5,
                'batch_booking' => 1,
                'sanitize_text' => 1,
                'include_bic' => 0,
            ],
            'messages' => Flash::all(),
        ]);
    }

    public function install(): void
    {
        Csrf::check();

        $dbHost = trim((string)($_POST['db_host'] ?? ''));
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim((string)($_POST['db_name'] ?? ''));
        $dbUser = trim((string)($_POST['db_user'] ?? ''));
        $dbPass = (string)($_POST['db_pass'] ?? '');
        $dbCharset = trim((string)($_POST['db_charset'] ?? 'utf8mb4'));

        $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');

        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminPassword = (string)($_POST['admin_password'] ?? '');

        $creditorName = trim((string)($_POST['creditor_name'] ?? ''));
        $creditorId = trim((string)($_POST['creditor_id'] ?? ''));
        $creditorIban = trim((string)($_POST['creditor_iban'] ?? ''));
        $creditorBic = trim((string)($_POST['creditor_bic'] ?? ''));
        $creditorStreet = trim((string)($_POST['creditor_street'] ?? ''));
        $creditorZip = trim((string)($_POST['creditor_zip'] ?? ''));
        $creditorCity = trim((string)($_POST['creditor_city'] ?? ''));
        $creditorCountry = trim((string)($_POST['creditor_country'] ?? ''));
        $initiatingParty = trim((string)($_POST['initiating_party_name'] ?? ''));

        $defaultDays = (int)($_POST['default_days_until_collection'] ?? 5);
        $batchBooking = (int)($_POST['batch_booking'] ?? 1);
        $sanitizeText = (int)($_POST['sanitize_text'] ?? 1);
        $includeBic = (int)($_POST['include_bic'] ?? 0);

        if ($dbHost === '' || $dbName === '' || $dbUser === '' || $baseUrl === '' || $adminEmail === '' || $adminPassword === '') {
            Flash::add('error', 'Bitte fülle alle Pflichtfelder aus.');
            header('Location: ' . $this->guessBaseUrl() . '/setup');
            exit;
        }

        try {
            $pdo = Db::connectForSetup($dbHost, $dbPort, $dbUser, $dbPass, $dbCharset);

            // Create DB if possible
            $safeDb = str_replace('`', '', $dbName);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safeDb . '` CHARACTER SET ' . $dbCharset . ' COLLATE ' . $dbCharset . '_unicode_ci');
            $pdo->exec('USE `' . $safeDb . '`');

            $sql = file_get_contents(App::basePath('migrations/001_init.sql'));
            if ($sql === false) {
                throw new \RuntimeException('Migration Datei fehlt');
            }

            foreach ($this->splitSql($sql) as $stmt) {
                $pdo->exec($stmt);
            }

            // Insert settings (idempotent)
            $st = $pdo->prepare('INSERT INTO settings (id, creditor_name, creditor_id, creditor_iban, creditor_bic, creditor_street, creditor_zip, creditor_city, creditor_country, initiating_party_name, default_scheme, default_days_until_collection, batch_booking, sanitize_text, include_bic)
                VALUES (1,:n,:cid,:iban,:bic,:street,:zip,:city,:country,:ip,"CORE",:days,:bb,:san,:inc)
                ON DUPLICATE KEY UPDATE creditor_name=VALUES(creditor_name), creditor_id=VALUES(creditor_id), creditor_iban=VALUES(creditor_iban), creditor_bic=VALUES(creditor_bic),
                    creditor_street=VALUES(creditor_street), creditor_zip=VALUES(creditor_zip), creditor_city=VALUES(creditor_city), creditor_country=VALUES(creditor_country), initiating_party_name=VALUES(initiating_party_name),
                    default_days_until_collection=VALUES(default_days_until_collection), batch_booking=VALUES(batch_booking), sanitize_text=VALUES(sanitize_text), include_bic=VALUES(include_bic)');
            $st->execute([
                'n' => $creditorName ?: 'Bitte eintragen',
                'cid' => $creditorId ?: 'DE00ZZZ00000000000',
                'iban' => $creditorIban ?: 'DE00000000000000000000',
                'bic' => $creditorBic ?: null,
                'street' => $creditorStreet ?: null,
                'zip' => $creditorZip ?: null,
                'city' => $creditorCity ?: null,
                'country' => $creditorCountry ?: null,
                'ip' => $initiatingParty ?: null,
                'days' => max(0, $defaultDays),
                'bb' => $batchBooking ? 1 : 0,
                'san' => $sanitizeText ? 1 : 0,
                'inc' => $includeBic ? 1 : 0,
            ]);

            // Create or update admin (idempotent)
            $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (email, password_hash, role, is_active)
                VALUES (:e,:h,"admin",1)
                ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = "admin", is_active = 1');
            $st->execute(['e' => $adminEmail, 'h' => $hash]);

            // Generate app key and store config in installed.lock as JSON
            $appKeyRaw = random_bytes(32);
            $appKey = 'base64:' . base64_encode($appKeyRaw);

            $lockConfig = [
                'base_url' => $baseUrl,
                'app_key' => $appKey,
                'app_debug' => false,

                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'db_charset' => $dbCharset,
            ];

            $lockPath = App::basePath('config/installed.lock');
            $lockJson = json_encode($lockConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($lockJson === false || file_put_contents($lockPath, $lockJson) === false) {
                throw new \RuntimeException('Konnte installed.lock nicht schreiben');
            }

            // Keep config/app.php minimal so updates do not break installation
            $configPath = App::basePath('config/app.php');
            if (!is_file($configPath) || trim((string)file_get_contents($configPath)) === '') {
                $content = "<?php\nreturn [\n    'app_debug' => false,\n];\n";
                @file_put_contents($configPath, $content);
            }

            Flash::add('success', 'Installation abgeschlossen. Bitte einloggen.');
            header('Location: ' . $baseUrl . '/login');
            exit;

        } catch (\Throwable $e) {
            Flash::add('error', 'Setup Fehler: ' . $e->getMessage());
            header('Location: ' . $this->guessBaseUrl() . '/setup');
            exit;
        }
    }

    private function guessBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('/public/index.php', '', $script), '/');
        $dir = ($dir === '' || $dir === '/public') ? '' : $dir;
        return $scheme . '://' . $host . $dir;
    }

    private function splitSql(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*\n/', $sql);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }
}
