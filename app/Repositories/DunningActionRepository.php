<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class DunningActionRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS dunning_actions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
            invoice_number VARCHAR(80) NOT NULL DEFAULT '',
            sevdesk_contact_id BIGINT UNSIGNED NULL,
            contact_name VARCHAR(190) NOT NULL DEFAULT '',
            stage TINYINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            due_date DATE NULL,
            recipient_email VARCHAR(190) NULL,
            status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            sevdesk_dunning_id BIGINT UNSIGNED NULL,
            error_text TEXT NULL,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_by BIGINT UNSIGNED NULL,
            sent_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dunning_invoice_stage (sevdesk_invoice_id, stage),
            KEY ix_dunning_status (status),
            KEY ix_dunning_contact (sevdesk_contact_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Legt eine offene Mahnaktion an. Liefert false, wenn für die Rechnung
     * bereits eine Aktion derselben Stufe existiert (Idempotenz via Unique-Key).
     */
    public function insertPending(array $data): bool
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT IGNORE INTO dunning_actions
            (sevdesk_invoice_id, invoice_number, sevdesk_contact_id, contact_name, stage, amount, currency, due_date, recipient_email)
            VALUES (:sevdesk_invoice_id, :invoice_number, :sevdesk_contact_id, :contact_name, :stage, :amount, :currency, :due_date, :recipient_email)');
        $st->execute([
            'sevdesk_invoice_id' => (int)($data['sevdesk_invoice_id'] ?? 0),
            'invoice_number' => (string)($data['invoice_number'] ?? ''),
            'sevdesk_contact_id' => $data['sevdesk_contact_id'] ?? null,
            'contact_name' => (string)($data['contact_name'] ?? ''),
            'stage' => (int)($data['stage'] ?? 0),
            'amount' => (float)($data['amount'] ?? 0),
            'currency' => (string)($data['currency'] ?? 'EUR'),
            'due_date' => $data['due_date'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
        ]);
        return $st->rowCount() > 0;
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT * FROM dunning_actions WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findPending(): array
    {
        $this->ensureTable();
        $rows = Db::pdo()->query("SELECT * FROM dunning_actions WHERE status = 'pending' ORDER BY stage DESC, due_date ASC, id ASC")->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0));
        if (empty($ids)) {
            return [];
        }
        $this->ensureTable();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = Db::pdo()->prepare('SELECT * FROM dunning_actions WHERE id IN (' . $ph . ') ORDER BY id ASC');
        $st->execute($ids);
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function markSent(int $id, ?int $sevdeskDunningId, ?string $email, ?int $userId, bool $isTest = false): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE dunning_actions SET status = 'sent', sevdesk_dunning_id = :did, recipient_email = COALESCE(:email, recipient_email), approved_by = :uid, is_test = :is_test, error_text = NULL, sent_at = NOW() WHERE id = :id");
        $st->execute([
            'did' => $sevdeskDunningId,
            'email' => $email,
            'uid' => $userId,
            'is_test' => $isTest ? 1 : 0,
            'id' => $id,
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE dunning_actions SET status = 'failed', error_text = :err WHERE id = :id");
        $st->execute(['err' => $error, 'id' => $id]);
    }

    public function markSkipped(int $id, string $reason): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE dunning_actions SET status = 'skipped', error_text = :err WHERE id = :id");
        $st->execute(['err' => $reason, 'id' => $id]);
    }

    public function resetToPending(int $id): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE dunning_actions SET status = 'pending', error_text = NULL WHERE id = :id AND status IN ('failed','skipped')");
        $st->execute(['id' => $id]);
    }

    public function updateRecipientEmail(int $id, string $email): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('UPDATE dunning_actions SET recipient_email = :email WHERE id = :id');
        $st->execute(['email' => $email, 'id' => $id]);
    }

    public function recent(int $limit = 100): array
    {
        $this->ensureTable();
        $limit = max(1, min(500, $limit));
        $rows = Db::pdo()->query("SELECT * FROM dunning_actions WHERE status <> 'pending' ORDER BY id DESC LIMIT " . $limit)->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
