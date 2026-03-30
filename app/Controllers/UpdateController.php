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
    public function show(): void
    {
        $error = null;
        $currentCommit = '';
        $branch = '';
        $pendingCommits = [];

        if (!self::checkPrerequisites($error)) {
            View::render('update', [
                'csrf' => Csrf::token(),
                'error' => $error,
                'currentCommit' => '',
                'branch' => '',
                'pendingCommits' => [],
                'messages' => Flash::all(),
            ]);
            return;
        }

        $basePath = App::basePath();
        $currentCommit = self::git($basePath, 'log --oneline -1');
        $branch = trim(self::git($basePath, 'rev-parse --abbrev-ref HEAD'));

        // Fetch latest from remote (non-destructive)
        self::git($basePath, 'fetch origin 2>&1');

        // Check for pending commits
        $safeBranch = escapeshellarg($branch);
        $pending = self::git($basePath, "log HEAD..origin/{$branch} --oneline 2>&1");
        $pendingCommits = array_filter(explode("\n", $pending), fn(string $line) => trim($line) !== '');

        View::render('update', [
            'csrf' => Csrf::token(),
            'error' => null,
            'currentCommit' => $currentCommit,
            'branch' => $branch,
            'pendingCommits' => $pendingCommits,
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

        set_time_limit(120);

        $basePath = App::basePath();
        $branch = trim(self::git($basePath, 'rev-parse --abbrev-ref HEAD'));
        $safeBranch = escapeshellarg($branch);

        $output = [];
        $exitCode = 1;
        exec("cd " . escapeshellarg($basePath) . " && git pull origin {$safeBranch} 2>&1", $output, $exitCode);
        $outputText = implode("\n", $output);

        $user = Auth::user();
        $userId = $user ? (int)$user['id'] : 0;

        if ($exitCode === 0) {
            // Run pending migrations
            $migrationResult = self::runPendingMigrations($basePath);

            (new AuditLogRepository())->add($userId, 'system_update', 'system', 0, [
                'branch' => $branch,
                'git_output' => $outputText,
                'migrations' => $migrationResult,
            ]);

            $msg = 'Update erfolgreich ausgefuehrt.';
            if ($migrationResult !== '') {
                $msg .= ' Migrationen: ' . $migrationResult;
            }
            Flash::add('success', $msg);
        } else {
            Logger::error('System update failed: ' . $outputText);

            (new AuditLogRepository())->add($userId, 'system_update_failed', 'system', 0, [
                'branch' => $branch,
                'git_output' => $outputText,
                'exit_code' => $exitCode,
            ]);

            Flash::add('error', 'Update fehlgeschlagen: ' . $outputText);
        }

        header('Location: ' . App::url('/update'));
        exit;
    }

    private static function checkPrerequisites(?string &$error): bool
    {
        if (!function_exists('exec')) {
            $error = 'Die PHP-Funktion exec() ist auf diesem Server nicht verfuegbar.';
            return false;
        }

        $output = [];
        $code = 1;
        exec('git --version 2>&1', $output, $code);
        if ($code !== 0) {
            $error = 'Git ist auf diesem Server nicht installiert oder nicht im PATH.';
            return false;
        }

        $basePath = App::basePath();
        if (!is_dir($basePath . '/.git')) {
            $error = 'Das Projektverzeichnis ist kein Git-Repository.';
            return false;
        }

        return true;
    }

    private static function git(string $basePath, string $cmd): string
    {
        $output = [];
        $code = 0;
        exec("cd " . escapeshellarg($basePath) . " && git {$cmd}", $output, $code);
        return implode("\n", $output);
    }

    private static function runPendingMigrations(string $basePath): string
    {
        $migrationsDir = $basePath . '/migrations';
        if (!is_dir($migrationsDir)) {
            return '';
        }

        try {
            $pdo = Db::connect();

            // Ensure schema_migrations table exists
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');

            // Seed already-applied migrations on first run
            $count = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            if ($count === 0) {
                $existing = glob($migrationsDir . '/*.sql');
                if ($existing) {
                    // Mark all current migrations as already applied (they were run during setup)
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
