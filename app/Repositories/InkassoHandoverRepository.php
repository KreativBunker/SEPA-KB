<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class InkassoHandoverRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS inkasso_handovers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sevdesk_invoice_id BIGINT UNSIGNED NOT NULL,
            invoice_number VARCHAR(80) NOT NULL DEFAULT '',
            sevdesk_contact_id BIGINT UNSIGNED NULL,
            contact_name VARCHAR(190) NOT NULL DEFAULT '',
            amount_original DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            dunning_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
            due_date DATE NULL,
            recipient_email VARCHAR(190) NOT NULL,
            attachments_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY ix_inkasso_invoice (sevdesk_invoice_id),
            KEY ix_inkasso_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function add(array $data): int
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT INTO inkasso_handovers
            (sevdesk_invoice_id, invoice_number, sevdesk_contact_id, contact_name, amount_original, amount_total, dunning_level, due_date, recipient_email, attachments_count, sent_by)
            VALUES (:sevdesk_invoice_id, :invoice_number, :sevdesk_contact_id, :contact_name, :amount_original, :amount_total, :dunning_level, :due_date, :recipient_email, :attachments_count, :sent_by)');
        $st->execute([
            'sevdesk_invoice_id' => (int)($data['sevdesk_invoice_id'] ?? 0),
            'invoice_number' => (string)($data['invoice_number'] ?? ''),
            'sevdesk_contact_id' => $data['sevdesk_contact_id'] ?? null,
            'contact_name' => (string)($data['contact_name'] ?? ''),
            'amount_original' => (float)($data['amount_original'] ?? 0),
            'amount_total' => (float)($data['amount_total'] ?? 0),
            'dunning_level' => (int)($data['dunning_level'] ?? 0),
            'due_date' => $data['due_date'] ?? null,
            'recipient_email' => (string)($data['recipient_email'] ?? ''),
            'attachments_count' => (int)($data['attachments_count'] ?? 0),
            'sent_by' => $data['sent_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function latestByInvoiceId(int $sevdeskInvoiceId): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM inkasso_handovers WHERE sevdesk_invoice_id = :id ORDER BY sent_at DESC, id DESC LIMIT 1');
        $st->execute(['id' => $sevdeskInvoiceId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function mapByInvoiceIds(array $invoiceIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $invoiceIds)));
        $ids = array_values(array_filter($ids, fn(int $v): bool => $v > 0));
        if (empty($ids)) {
            return [];
        }

        $this->ensureTable();
        $pdo = Db::pdo();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare('SELECT sevdesk_invoice_id, recipient_email, dunning_level, sent_at FROM inkasso_handovers WHERE sevdesk_invoice_id IN (' . $ph . ') ORDER BY sent_at DESC, id DESC');
        $st->execute($ids);

        $rows = $st->fetchAll();
        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $iid = (int)($r['sevdesk_invoice_id'] ?? 0);
                if ($iid && !isset($map[$iid])) {
                    $map[$iid] = $r;
                }
            }
        }
        return $map;
    }
}
