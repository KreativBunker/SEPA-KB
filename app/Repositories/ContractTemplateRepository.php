<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ContractTemplateRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS contract_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            include_sepa TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function all(): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        return $pdo->query('SELECT * FROM contract_templates ORDER BY id DESC')->fetchAll() ?: [];
    }

    public function allActive(): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        return $pdo->query('SELECT * FROM contract_templates WHERE is_active = 1 ORDER BY title ASC')->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM contract_templates WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'INSERT INTO contract_templates (title, body, include_sepa, is_active, created_by)
                VALUES (:title, :body, :include_sepa, :is_active, :created_by)';
        $st = $pdo->prepare($sql);
        $st->execute([
            'title' => (string)$data['title'],
            'body' => (string)$data['body'],
            'include_sepa' => (int)($data['include_sepa'] ?? 0),
            'is_active' => (int)($data['is_active'] ?? 1),
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'UPDATE contract_templates SET
            title = :title,
            body = :body,
            include_sepa = :include_sepa,
            is_active = :is_active,
            updated_at = NOW()
            WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute([
            'title' => (string)$data['title'],
            'body' => (string)$data['body'],
            'include_sepa' => (int)($data['include_sepa'] ?? 0),
            'is_active' => (int)($data['is_active'] ?? 1),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('DELETE FROM contract_templates WHERE id = :id');
        $st->execute(['id' => $id]);
    }
}
