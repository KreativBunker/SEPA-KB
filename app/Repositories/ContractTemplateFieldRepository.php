<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ContractTemplateFieldRepository
{
    public const TYPES = ['text', 'textarea', 'number', 'date', 'email'];
    public const FILL_BY = ['admin', 'customer'];

    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS contract_template_fields (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            field_key VARCHAR(64) NOT NULL,
            label VARCHAR(190) NOT NULL,
            field_type ENUM('text','textarea','number','date','email') NOT NULL DEFAULT 'text',
            fill_by ENUM('admin','customer') NOT NULL DEFAULT 'admin',
            required TINYINT(1) NOT NULL DEFAULT 0,
            default_value TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tpl_field (template_id, field_key),
            KEY ix_tpl_field_template (template_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function forTemplate(int $templateId): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM contract_template_fields WHERE template_id = :t ORDER BY sort_order ASC, id ASC');
        $st->execute(['t' => $templateId]);
        return $st->fetchAll() ?: [];
    }

    public function deleteForTemplate(int $templateId): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('DELETE FROM contract_template_fields WHERE template_id = :t');
        $st->execute(['t' => $templateId]);
    }

    /**
     * Replace all fields for a template with the given list.
     * Each field must have: field_key, label, field_type, fill_by, required, default_value (optional)
     */
    public function replaceForTemplate(int $templateId, array $fields): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM contract_template_fields WHERE template_id = :t');
            $del->execute(['t' => $templateId]);

            $sql = 'INSERT INTO contract_template_fields
                    (template_id, field_key, label, field_type, fill_by, required, default_value, sort_order)
                    VALUES (:template_id, :field_key, :label, :field_type, :fill_by, :required, :default_value, :sort_order)';
            $ins = $pdo->prepare($sql);

            $order = 0;
            $seenKeys = [];
            foreach ($fields as $f) {
                $key = self::sanitizeKey((string)($f['field_key'] ?? ''));
                $label = trim((string)($f['label'] ?? ''));
                if ($key === '' || $label === '' || isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;

                $type = (string)($f['field_type'] ?? 'text');
                if (!in_array($type, self::TYPES, true)) {
                    $type = 'text';
                }
                $fillBy = (string)($f['fill_by'] ?? 'admin');
                if (!in_array($fillBy, self::FILL_BY, true)) {
                    $fillBy = 'admin';
                }

                $ins->execute([
                    'template_id' => $templateId,
                    'field_key' => $key,
                    'label' => $label,
                    'field_type' => $type,
                    'fill_by' => $fillBy,
                    'required' => !empty($f['required']) ? 1 : 0,
                    'default_value' => isset($f['default_value']) ? (string)$f['default_value'] : null,
                    'sort_order' => $order++,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function sanitizeKey(string $raw): string
    {
        $key = strtolower(trim($raw));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');
        if (strlen($key) > 64) {
            $key = substr($key, 0, 64);
        }
        return $key;
    }
}
