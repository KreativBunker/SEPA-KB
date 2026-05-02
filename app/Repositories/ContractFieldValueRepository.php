<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ContractFieldValueRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS contract_field_values (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contract_id BIGINT UNSIGNED NOT NULL,
            field_key VARCHAR(64) NOT NULL,
            label VARCHAR(190) NOT NULL,
            field_type ENUM('text','textarea','number','date','email') NOT NULL DEFAULT 'text',
            fill_by ENUM('admin','customer') NOT NULL DEFAULT 'admin',
            required TINYINT(1) NOT NULL DEFAULT 0,
            value TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_contract_field (contract_id, field_key),
            KEY ix_contract_field_contract (contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function forContract(int $contractId): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM contract_field_values WHERE contract_id = :c ORDER BY sort_order ASC, id ASC');
        $st->execute(['c' => $contractId]);
        return $st->fetchAll() ?: [];
    }

    public function valuesMap(int $contractId): array
    {
        $rows = $this->forContract($contractId);
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r['field_key']] = (string)($r['value'] ?? '');
        }
        return $map;
    }

    public function deleteForContract(int $contractId): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('DELETE FROM contract_field_values WHERE contract_id = :c');
        $st->execute(['c' => $contractId]);
    }

    /**
     * Snapshot template fields onto a contract (used when contract is created).
     * Optional $values: associative array field_key => value to prefill.
     */
    public function snapshotFromTemplate(int $contractId, array $templateFields, array $values = []): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM contract_field_values WHERE contract_id = :c');
            $del->execute(['c' => $contractId]);

            $sql = 'INSERT INTO contract_field_values
                    (contract_id, field_key, label, field_type, fill_by, required, value, sort_order)
                    VALUES (:contract_id, :field_key, :label, :field_type, :fill_by, :required, :value, :sort_order)';
            $ins = $pdo->prepare($sql);

            $order = 0;
            foreach ($templateFields as $f) {
                $key = (string)($f['field_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $value = $values[$key] ?? ($f['default_value'] ?? null);
                $ins->execute([
                    'contract_id' => $contractId,
                    'field_key' => $key,
                    'label' => (string)($f['label'] ?? $key),
                    'field_type' => (string)($f['field_type'] ?? 'text'),
                    'fill_by' => (string)($f['fill_by'] ?? 'admin'),
                    'required' => !empty($f['required']) ? 1 : 0,
                    'value' => $value !== null ? (string)$value : null,
                    'sort_order' => $order++,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update the value for one or more fields of an existing contract.
     * $values: associative array field_key => value
     */
    public function updateValues(int $contractId, array $values): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE contract_field_values SET value = :v, updated_at = NOW() WHERE contract_id = :c AND field_key = :k');
        foreach ($values as $key => $value) {
            $st->execute([
                'v' => $value !== null ? (string)$value : null,
                'c' => $contractId,
                'k' => (string)$key,
            ]);
        }
    }
}
