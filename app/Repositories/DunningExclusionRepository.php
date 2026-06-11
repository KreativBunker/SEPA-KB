<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class DunningExclusionRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS dunning_exclusions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope ENUM('invoice','contact') NOT NULL,
            sevdesk_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL DEFAULT '',
            note VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dunning_excl (scope, sevdesk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function add(string $scope, int $sevdeskId, string $label = '', ?string $note = null, ?int $userId = null): bool
    {
        if (!in_array($scope, ['invoice', 'contact'], true) || $sevdeskId <= 0) {
            return false;
        }
        $this->ensureTable();
        $st = Db::pdo()->prepare('INSERT IGNORE INTO dunning_exclusions (scope, sevdesk_id, label, note, created_by) VALUES (:scope, :sid, :label, :note, :uid)');
        $st->execute([
            'scope' => $scope,
            'sid' => $sevdeskId,
            'label' => $label,
            'note' => $note,
            'uid' => $userId,
        ]);
        return $st->rowCount() > 0;
    }

    public function remove(int $id): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('DELETE FROM dunning_exclusions WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    public function all(): array
    {
        $this->ensureTable();
        $rows = Db::pdo()->query('SELECT * FROM dunning_exclusions ORDER BY scope ASC, id DESC')->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, true> sevdesk Rechnungs-IDs als Key-Map */
    public function excludedInvoiceIds(): array
    {
        return $this->idMap('invoice');
    }

    /** @return array<int, true> sevdesk Kontakt-IDs als Key-Map */
    public function excludedContactIds(): array
    {
        return $this->idMap('contact');
    }

    private function idMap(string $scope): array
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT sevdesk_id FROM dunning_exclusions WHERE scope = :scope');
        $st->execute(['scope' => $scope]);
        $map = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $map[(int)$r['sevdesk_id']] = true;
        }
        return $map;
    }
}
