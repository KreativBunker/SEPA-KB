<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class DunningRunRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS dunning_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trigger_type ENUM('cron','web','manual') NOT NULL DEFAULT 'cron',
            mode ENUM('review','auto') NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            candidates INT UNSIGNED NOT NULL DEFAULT 0,
            queued INT UNSIGNED NOT NULL DEFAULT 0,
            sent INT UNSIGNED NOT NULL DEFAULT 0,
            skipped INT UNSIGNED NOT NULL DEFAULT 0,
            errors INT UNSIGNED NOT NULL DEFAULT 0,
            log_text MEDIUMTEXT NULL,
            PRIMARY KEY (id),
            KEY ix_dunning_runs_started (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function start(string $trigger, string $mode): int
    {
        $this->ensureTable();
        $trigger = in_array($trigger, ['cron', 'web', 'manual'], true) ? $trigger : 'cron';
        $mode = $mode === 'auto' ? 'auto' : 'review';
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT INTO dunning_runs (trigger_type, mode) VALUES (:t, :m)');
        $st->execute(['t' => $trigger, 'm' => $mode]);
        return (int)$pdo->lastInsertId();
    }

    public function finish(int $id, array $counters, string $logText = ''): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('UPDATE dunning_runs SET finished_at = NOW(), candidates = :c, queued = :q, sent = :s, skipped = :sk, errors = :e, log_text = :log WHERE id = :id');
        $st->execute([
            'c' => (int)($counters['candidates'] ?? 0),
            'q' => (int)($counters['queued'] ?? 0),
            's' => (int)($counters['sent'] ?? 0),
            'sk' => (int)($counters['skipped'] ?? 0),
            'e' => (int)($counters['errors'] ?? 0),
            'log' => $logText !== '' ? $logText : null,
            'id' => $id,
        ]);
    }

    public function recent(int $limit = 20): array
    {
        $this->ensureTable();
        $limit = max(1, min(100, $limit));
        $rows = Db::pdo()->query('SELECT * FROM dunning_runs ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
