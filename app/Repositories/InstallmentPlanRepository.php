<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class InstallmentPlanRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS installment_plans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source ENUM('invoice','manual') NOT NULL DEFAULT 'invoice',
            sevdesk_invoice_id BIGINT UNSIGNED NULL,
            invoice_number VARCHAR(60) NOT NULL DEFAULT '',
            sevdesk_contact_id BIGINT UNSIGNED NULL,
            mandate_id BIGINT UNSIGNED NULL,
            debtor_name VARCHAR(190) NOT NULL DEFAULT '',
            debtor_iban VARCHAR(34) NOT NULL DEFAULT '',
            mandate_reference VARCHAR(35) NOT NULL DEFAULT '',
            mandate_date DATE NULL,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            interval_months SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            first_collection_date DATE NOT NULL,
            remittance_template VARCHAR(140) NOT NULL DEFAULT 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}',
            scheme ENUM('CORE','B2B') NOT NULL DEFAULT 'CORE',
            status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ix_installment_plan_invoice (sevdesk_invoice_id),
            KEY ix_installment_plan_contact (sevdesk_contact_id),
            KEY ix_installment_plan_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function all(string $q = '', string $status = ''): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();

        $allowedStatus = ['active', 'completed', 'cancelled'];
        $statusFilter = in_array($status, $allowedStatus, true) ? $status : '';

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(debtor_name LIKE :q OR invoice_number LIKE :q OR mandate_reference LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($statusFilter !== '') {
            $where[] = 'status = :status';
            $params['status'] = $statusFilter;
        }

        $sql = 'SELECT * FROM installment_plans';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';

        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT * FROM installment_plans WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Prüft, ob für eine sevdesk-Rechnung bereits ein aktiver Ratenplan existiert. */
    public function hasActivePlan(int $sevdeskInvoiceId): bool
    {
        if ($sevdeskInvoiceId <= 0) {
            return false;
        }
        $this->ensureTable();
        $st = Db::pdo()->prepare("SELECT 1 FROM installment_plans WHERE sevdesk_invoice_id = :iid AND status = 'active' LIMIT 1");
        $st->execute(['iid' => $sevdeskInvoiceId]);
        return (bool)$st->fetchColumn();
    }

    public function create(array $data): int
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'INSERT INTO installment_plans
            (source,sevdesk_invoice_id,invoice_number,sevdesk_contact_id,mandate_id,debtor_name,debtor_iban,mandate_reference,mandate_date,total_amount,rate_count,interval_months,first_collection_date,remittance_template,scheme,status,notes,created_by_user_id)
            VALUES
            (:source,:sevdesk_invoice_id,:invoice_number,:sevdesk_contact_id,:mandate_id,:debtor_name,:debtor_iban,:mandate_reference,:mandate_date,:total_amount,:rate_count,:interval_months,:first_collection_date,:remittance_template,:scheme,:status,:notes,:created_by_user_id)';
        $st = $pdo->prepare($sql);
        $st->execute([
            'source' => (string)($data['source'] ?? 'invoice'),
            'sevdesk_invoice_id' => $data['sevdesk_invoice_id'] ?? null,
            'invoice_number' => (string)($data['invoice_number'] ?? ''),
            'sevdesk_contact_id' => $data['sevdesk_contact_id'] ?? null,
            'mandate_id' => $data['mandate_id'] ?? null,
            'debtor_name' => (string)($data['debtor_name'] ?? ''),
            'debtor_iban' => (string)($data['debtor_iban'] ?? ''),
            'mandate_reference' => (string)($data['mandate_reference'] ?? ''),
            'mandate_date' => $data['mandate_date'] ?? null,
            'total_amount' => (float)($data['total_amount'] ?? 0),
            'rate_count' => (int)($data['rate_count'] ?? 1),
            'interval_months' => (int)($data['interval_months'] ?? 1),
            'first_collection_date' => (string)($data['first_collection_date'] ?? date('Y-m-d')),
            'remittance_template' => (string)($data['remittance_template'] ?? 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}'),
            'scheme' => (string)($data['scheme'] ?? 'CORE'),
            'status' => (string)($data['status'] ?? 'active'),
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function setStatus(int $id, string $status): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('UPDATE installment_plans SET status = :s, updated_at = NOW() WHERE id = :id');
        $st->execute(['s' => $status, 'id' => $id]);
    }

    /** Setzt den Plan auf 'completed', sobald keine offenen (nicht stornierten) Raten mehr offen sind. */
    public function markCompletedIfAllRatesCollected(int $planId): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT COUNT(*) FROM installment_rates WHERE plan_id = :pid AND status IN ('planned','queued','failed')");
        $st->execute(['pid' => $planId]);
        $open = (int)$st->fetchColumn();
        if ($open === 0) {
            $this->setStatus($planId, 'completed');
        }
    }
}
