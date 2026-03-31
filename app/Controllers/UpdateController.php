<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Services\Db;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\View;

final class UpdateController
{
    /** Paths that must never be overwritten during update */
    private const PROTECTED_PATHS = [
        'config/installed.lock',
        'config/debug.lock',
        'storage/',
        'vendor/',
    ];

    public function show(): void
    {
        $error = null;
        if (!self::checkPrerequisites($error)) {
            View::render('update', [
                'csrf' => Csrf::token(),
                'error' => $error,
                'currentVersion' => null,
                'latestVersion' => null,
                'repoUrl' => '',
                'branch' => '',
                'updateAvailable' => false,
                'messages' => Flash::all(),
            ]);
            return;
        }

        $basePath = App::basePath();
        $repoUrl = trim((string)App::config('git_remote_url', ''));
        $branch = trim((string)App::config('git_branch', 'main'));
        $currentVersion = self::readLocalVersion($basePath);

        // Check latest version from GitHub API
        $latestVersion = null;
        $updateAvailable = false;
        $repo = self::parseGitHubRepo($repoUrl);

        if ($repo) {
            $latestVersion = self::fetchLatestCommit($repo, $branch);
            if ($latestVersion && $currentVersion) {
                $updateAvailable = ($currentVersion['sha'] !== $latestVersion['sha']);
            } elseif ($latestVersion && !$currentVersion) {
                $updateAvailable = true;
            }
        }

        View::render('update', [
            'csrf' => Csrf::token(),
            'error' => null,
            'currentVersion' => $currentVersion,
            'latestVersion' => $latestVersion,
            'repoUrl' => $repoUrl,
            'branch' => $branch,
            'updateAvailable' => $updateAvailable,
            'messages' => Flash::all(),
        ]);
    }

    public function run(): void
    {
        Csrf::check();

        $error = null;
        if (!self::checkPrerequisites($error)) {
            Flash::add('error', $error);
            header('Location: ' . App::url('/update'));
            exit;
        }

        set_time_limit(180);

        $basePath = App::basePath();
        $repoUrl = trim((string)App::config('git_remote_url', ''));
        $branch = trim((string)App::config('git_branch', 'main'));
        $repo = self::parseGitHubRepo($repoUrl);

        if (!$repo) {
            Flash::add('error', 'Ungueltige Repository-URL in der Konfiguration.');
            header('Location: ' . App::url('/update'));
            exit;
        }

        $user = Auth::user();
        $userId = $user ? (int)$user['id'] : 0;

        try {
            // 1. Fetch latest commit info
            $latestVersion = self::fetchLatestCommit($repo, $branch);
            if (!$latestVersion) {
                throw new \RuntimeException('Konnte die neueste Version nicht von GitHub abrufen.');
            }

            // 2. Download ZIP
            $zipPath = self::downloadZip($repo, $branch);

            // 3. Extract and copy files
            $filesUpdated = self::extractAndCopy($zipPath, $basePath);

            // 4. Save version info
            self::writeLocalVersion($basePath, $latestVersion, $branch);

            // 5. Run pending migrations
            $migrationResult = self::runPendingMigrations($basePath);

            // 6. Cleanup
            @unlink($zipPath);

            (new AuditLogRepository())->add($userId, 'system_update', 'system', 0, [
                'branch' => $branch,
                'sha' => $latestVersion['sha'],
                'message' => $latestVersion['message'],
                'files_updated' => $filesUpdated,
                'migrations' => $migrationResult,
            ]);

            $msg = "Update erfolgreich! Version: " . substr($latestVersion['sha'], 0, 7) . " ({$filesUpdated} Dateien aktualisiert)";
            if ($migrationResult !== '') {
                $msg .= ' | Migrationen: ' . $migrationResult;
            }
            Flash::add('success', $msg);

        } catch (\Throwable $e) {
            Logger::error('System update failed: ' . $e->getMessage());

            (new AuditLogRepository())->add($userId, 'system_update_failed', 'system', 0, [
                'branch' => $branch,
                'error' => $e->getMessage(),
            ]);

            Flash::add('error', 'Update fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/update'));
        exit;
    }

    // ── Prerequisites ──────────────────────────────────────────────

    private static function checkPrerequisites(?string &$error): bool
    {
        $repoUrl = trim((string)App::config('git_remote_url', ''));
        if ($repoUrl === '') {
            $error = 'Keine Repository-URL konfiguriert. Bitte "git_remote_url" in config/installed.lock setzen.';
            return false;
        }

        $token = trim((string)App::config('git_token', ''));
        if ($token === '') {
            $error = 'Kein GitHub-Token konfiguriert. Bitte "git_token" in config/installed.lock setzen.';
            return false;
        }

        $repo = self::parseGitHubRepo($repoUrl);
        if (!$repo) {
            $error = 'Die Repository-URL konnte nicht erkannt werden. Format: https://github.com/owner/repo';
            return false;
        }

        if (!class_exists('ZipArchive')) {
            $error = 'Die PHP-Erweiterung "zip" ist nicht installiert. Bitte beim Hoster aktivieren.';
            return false;
        }

        return true;
    }

    // ── GitHub API ─────────────────────────────────────────────────

    /**
     * Parse "owner/repo" from a GitHub URL.
     */
    private static function parseGitHubRepo(string $url): ?string
    {
        // https://github.com/owner/repo or https://github.com/owner/repo.git
        if (preg_match('#github\.com/([^/]+/[^/.\s]+)#i', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Fetch the latest commit info from GitHub API.
     * @return array{sha: string, message: string, date: string}|null
     */
    private static function fetchLatestCommit(string $repo, string $branch): ?array
    {
        $url = "https://api.github.com/repos/{$repo}/commits/" . urlencode($branch);
        $response = self::githubApiGet($url);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['sha'])) {
            return null;
        }

        return [
            'sha' => $data['sha'],
            'message' => $data['commit']['message'] ?? '',
            'date' => $data['commit']['committer']['date'] ?? '',
        ];
    }

    /**
     * Download the repository ZIP archive to a temp file.
     */
    private static function downloadZip(string $repo, string $branch): string
    {
        $url = "https://api.github.com/repos/{$repo}/zipball/" . urlencode($branch);
        $token = trim((string)App::config('git_token', ''));

        $tmpFile = tempnam(sys_get_temp_dir(), 'sepa_update_') . '.zip';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'User-Agent: SEPA-KB-Updater',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            throw new \RuntimeException('Download fehlgeschlagen: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("GitHub API Fehler (HTTP {$httpCode}). Bitte Token und Repository-URL pruefen.");
        }

        if (file_put_contents($tmpFile, $body) === false) {
            throw new \RuntimeException('Konnte ZIP-Datei nicht speichern.');
        }

        return $tmpFile;
    }

    /**
     * Make a GET request to the GitHub API.
     */
    private static function githubApiGet(string $url): ?string
    {
        $token = trim((string)App::config('git_token', ''));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'User-Agent: SEPA-KB-Updater',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        return $body;
    }

    // ── ZIP Extraction ─────────────────────────────────────────────

    /**
     * Extract ZIP and copy files to the project, skipping protected paths.
     * @return int Number of files updated
     */
    private static function extractAndCopy(string $zipPath, string $basePath): int
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('ZIP-Datei konnte nicht geoeffnet werden.');
        }

        // GitHub ZIPs have a top-level directory like "owner-repo-sha/"
        $prefix = '';
        if ($zip->numFiles > 0) {
            $firstName = $zip->getNameIndex(0);
            if ($firstName && str_contains($firstName, '/')) {
                $prefix = substr($firstName, 0, strpos($firstName, '/') + 1);
            }
        }

        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Strip the top-level prefix
            $relativePath = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
            if ($relativePath === '' || $relativePath === false) {
                continue;
            }

            // Skip directories (they end with /)
            if (str_ends_with($name, '/')) {
                continue;
            }

            // Check if path is protected
            if (self::isProtectedPath($relativePath)) {
                continue;
            }

            $targetPath = $basePath . '/' . $relativePath;

            // Create directory if needed
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Extract file
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            file_put_contents($targetPath, $content);
            $count++;
        }

        $zip->close();
        return $count;
    }

    /**
     * Check if a relative path is protected from overwriting.
     */
    private static function isProtectedPath(string $path): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if (str_ends_with($protected, '/')) {
                // Directory protection
                if (str_starts_with($path, $protected)) {
                    return true;
                }
            } else {
                // File protection
                if ($path === $protected) {
                    return true;
                }
            }
        }
        return false;
    }

    // ── Version Tracking ───────────────────────────────────────────

    private static function readLocalVersion(string $basePath): ?array
    {
        $file = $basePath . '/config/version.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private static function writeLocalVersion(string $basePath, array $version, string $branch): void
    {
        $file = $basePath . '/config/version.json';
        $data = [
            'sha' => $version['sha'],
            'message' => $version['message'],
            'date' => $version['date'],
            'branch' => $branch,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    // ── Migrations ─────────────────────────────────────────────────

    private static function runPendingMigrations(string $basePath): string
    {
        $migrationsDir = $basePath . '/migrations';
        if (!is_dir($migrationsDir)) {
            return '';
        }

        try {
            $pdo = Db::connect();

            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');

            // Seed already-applied migrations on first run
            $count = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            if ($count === 0) {
                $existing = glob($migrationsDir . '/*.sql');
                if ($existing) {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (:f)');
                    foreach ($existing as $file) {
                        $stmt->execute(['f' => basename($file)]);
                    }
                }
                return '';
            }

            // Find and run new migrations
            $applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
            $appliedMap = array_flip($applied);

            $files = glob($migrationsDir . '/*.sql');
            sort($files);

            $ran = [];
            foreach ($files as $file) {
                $name = basename($file);
                if (isset($appliedMap[$name])) {
                    continue;
                }

                $sql = file_get_contents($file);
                if ($sql === false) {
                    continue;
                }

                foreach (self::splitSql($sql) as $stmt) {
                    $pdo->exec($stmt);
                }

                $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:f)')->execute(['f' => $name]);
                $ran[] = $name;
            }

            return $ran ? implode(', ', $ran) : '';
        } catch (\Throwable $e) {
            Logger::error('Migration error: ' . $e->getMessage());
            return 'Fehler: ' . $e->getMessage();
        }
    }

    private static function splitSql(string $sql): array
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
