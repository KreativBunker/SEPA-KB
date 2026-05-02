<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ContractRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(80) NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            status ENUM('draft','open','signed','revoked','cancelled') NOT NULL DEFAULT 'draft',
            title VARCHAR(190) NOT NULL,
            body TEXT NOT NULL,
            include_sepa TINYINT(1) NOT NULL DEFAULT 0,
            sevdesk_contact_id BIGINT UNSIGNED NULL,
            contact_name VARCHAR(190) NOT NULL DEFAULT '',
            contact_email VARCHAR(190) NOT NULL DEFAULT '',
            signer_name VARCHAR(190) NULL,
            signer_street VARCHAR(190) NULL,
            signer_zip VARCHAR(20) NULL,
            signer_city VARCHAR(120) NULL,
            signer_country VARCHAR(2) NULL DEFAULT 'DE',
            debtor_iban VARCHAR(34) NULL,
            debtor_bic VARCHAR(11) NULL,
            mandate_reference VARCHAR(35) NULL,
            payment_type ENUM('OOFF','RCUR') NULL,
            signature_path VARCHAR(255) NULL,
            pdf_path VARCHAR(255) NULL,
            sepa_pdf_path VARCHAR(255) NULL,
            cancellation_pdf_path VARCHAR(255) NULL,
            cancellation_reason TEXT NULL,
            cancellation_date DATE NULL,
            cancelled_at DATETIME NULL,
            cancelled_by BIGINT UNSIGNED NULL,
            signed_place VARCHAR(120) NULL,
            signed_date DATE NULL,
            signed_at DATETIME NULL,
            signed_ip VARCHAR(45) NULL,
            signed_user_agent VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_contracts_token (token),
            KEY ix_contracts_status (status),
            KEY ix_contracts_template (template_id),
            KEY ix_contracts_contact (sevdesk_contact_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Backfill column for existing installations
        try {
            $col = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'sepa_pdf_path'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE contracts ADD COLUMN sepa_pdf_path VARCHAR(255) NULL AFTER pdf_path");
            }
        } catch (\Throwable $e) {
            // ignore – older MySQL versions or limited privileges
        }

        try {
            $cancellationColumns = [
                'cancellation_pdf_path' => "ALTER TABLE contracts ADD COLUMN cancellation_pdf_path VARCHAR(255) NULL AFTER sepa_pdf_path",
                'cancellation_reason' => "ALTER TABLE contracts ADD COLUMN cancellation_reason TEXT NULL AFTER cancellation_pdf_path",
                'cancellation_date' => "ALTER TABLE contracts ADD COLUMN cancellation_date DATE NULL AFTER cancellation_reason",
                'cancelled_at' => "ALTER TABLE contracts ADD COLUMN cancelled_at DATETIME NULL AFTER cancellation_date",
                'cancelled_by' => "ALTER TABLE contracts ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancelled_at",
            ];
            foreach ($cancellationColumns as $name => $alter) {
                $col = $pdo->query("SHOW COLUMNS FROM contracts LIKE " . $pdo->quote($name))->fetch();
                if (!$col) {
                    $pdo->exec($alter);
                }
            }

            $statusCol = $pdo->query("SHOW COLUMNS FROM contracts LIKE 'status'")->fetch();
            if ($statusCol && isset($statusCol['Type']) && strpos((string)$statusCol['Type'], "'cancelled'") === false) {
                $pdo->exec("ALTER TABLE contracts MODIFY COLUMN status ENUM('draft','open','signed','revoked','cancelled') NOT NULL DEFAULT 'draft'");
            }
        } catch (\Throwable $e) {
            // ignore – older MySQL versions or limited privileges
        }
    }

    public function all(): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        return $pdo->query('SELECT * FROM contracts ORDER BY id DESC')->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM contracts WHERE token = :t LIMIT 1');
        $st->execute(['t' => $token]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'INSERT INTO contracts
            (token, template_id, status, title, body, include_sepa, sevdesk_contact_id,
             contact_name, contact_email, signer_name, signer_street, signer_zip, signer_city, signer_country,
             mandate_reference, created_by)
            VALUES
            (:token, :template_id, :status, :title, :body, :include_sepa, :sevdesk_contact_id,
             :contact_name, :contact_email, :signer_name, :signer_street, :signer_zip, :signer_city, :signer_country,
             :mandate_reference, :created_by)';
        $st = $pdo->prepare($sql);
        $st->execute([
            'token' => (string)$data['token'],
            'template_id' => $data['template_id'] ?? null,
            'status' => (string)($data['status'] ?? 'open'),
            'title' => (string)$data['title'],
            'body' => (string)$data['body'],
            'include_sepa' => (int)($data['include_sepa'] ?? 0),
            'sevdesk_contact_id' => $data['sevdesk_contact_id'] ?? null,
            'contact_name' => (string)($data['contact_name'] ?? ''),
            'contact_email' => (string)($data['contact_email'] ?? ''),
            'signer_name' => $data['signer_name'] ?? null,
            'signer_street' => $data['signer_street'] ?? null,
            'signer_zip' => $data['signer_zip'] ?? null,
            'signer_city' => $data['signer_city'] ?? null,
            'signer_country' => $data['signer_country'] ?? null,
            'mandate_reference' => $data['mandate_reference'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'UPDATE contracts SET
            template_id = :template_id,
            title = :title,
            body = :body,
            include_sepa = :include_sepa,
            sevdesk_contact_id = :sevdesk_contact_id,
            contact_name = :contact_name,
            contact_email = :contact_email,
            signer_name = :signer_name,
            signer_street = :signer_street,
            signer_zip = :signer_zip,
            signer_city = :signer_city,
            signer_country = :signer_country,
            mandate_reference = :mandate_reference,
            updated_at = NOW()
            WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute([
            'template_id' => $data['template_id'] ?? null,
            'title' => (string)$data['title'],
            'body' => (string)$data['body'],
            'include_sepa' => (int)($data['include_sepa'] ?? 0),
            'sevdesk_contact_id' => $data['sevdesk_contact_id'] ?? null,
            'contact_name' => (string)($data['contact_name'] ?? ''),
            'contact_email' => (string)($data['contact_email'] ?? ''),
            'signer_name' => $data['signer_name'] ?? null,
            'signer_street' => $data['signer_street'] ?? null,
            'signer_zip' => $data['signer_zip'] ?? null,
            'signer_city' => $data['signer_city'] ?? null,
            'signer_country' => $data['signer_country'] ?? null,
            'mandate_reference' => $data['mandate_reference'] ?? null,
            'id' => $id,
        ]);
    }

    public function markSigned(int $id, array $data): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'UPDATE contracts SET
            status = :status,
            signer_name = :signer_name,
            signer_street = :signer_street,
            signer_zip = :signer_zip,
            signer_city = :signer_city,
            signer_country = :signer_country,
            debtor_iban = :debtor_iban,
            debtor_bic = :debtor_bic,
            payment_type = :payment_type,
            signed_place = :signed_place,
            signed_date = :signed_date,
            signature_path = :signature_path,
            pdf_path = :pdf_path,
            sepa_pdf_path = :sepa_pdf_path,
            signed_at = :signed_at,
            signed_ip = :signed_ip,
            signed_user_agent = :signed_user_agent,
            updated_at = NOW()
            WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute([
            'status' => 'signed',
            'signer_name' => (string)($data['signer_name'] ?? ''),
            'signer_street' => (string)($data['signer_street'] ?? ''),
            'signer_zip' => (string)($data['signer_zip'] ?? ''),
            'signer_city' => (string)($data['signer_city'] ?? ''),
            'signer_country' => (string)($data['signer_country'] ?? 'DE'),
            'debtor_iban' => $data['debtor_iban'] ?? null,
            'debtor_bic' => $data['debtor_bic'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'signed_place' => (string)($data['signed_place'] ?? ''),
            'signed_date' => (string)($data['signed_date'] ?? date('Y-m-d')),
            'signature_path' => (string)($data['signature_path'] ?? ''),
            'pdf_path' => (string)($data['pdf_path'] ?? ''),
            'sepa_pdf_path' => $data['sepa_pdf_path'] ?? null,
            'signed_at' => (string)($data['signed_at'] ?? date('Y-m-d H:i:s')),
            'signed_ip' => (string)($data['signed_ip'] ?? ''),
            'signed_user_agent' => (string)($data['signed_user_agent'] ?? ''),
            'id' => $id,
        ]);
    }

    public function updateSepaPdfPath(int $id, string $pdfPath): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE contracts SET sepa_pdf_path = :p, updated_at = NOW() WHERE id = :id');
        $st->execute(['p' => $pdfPath, 'id' => $id]);
    }

    public function revoke(int $id): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE contracts SET status = "revoked", updated_at = NOW() WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    public function cancel(int $id, array $data): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'UPDATE contracts SET
            status = "cancelled",
            cancellation_reason = :reason,
            cancellation_date = :cdate,
            cancelled_at = :cat,
            cancelled_by = :cby,
            updated_at = NOW()
            WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute([
            'reason' => $data['cancellation_reason'] ?? null,
            'cdate' => (string)($data['cancellation_date'] ?? date('Y-m-d')),
            'cat' => (string)($data['cancelled_at'] ?? date('Y-m-d H:i:s')),
            'cby' => $data['cancelled_by'] ?? null,
            'id' => $id,
        ]);
    }

    public function updateCancellationPdfPath(int $id, string $pdfPath): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE contracts SET cancellation_pdf_path = :p, updated_at = NOW() WHERE id = :id');
        $st->execute(['p' => $pdfPath, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('DELETE FROM contracts WHERE id = :id');
        $st->execute(['id' => $id]);
    }

    public function updatePdfPath(int $id, string $pdfPath): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE contracts SET pdf_path = :p, updated_at = NOW() WHERE id = :id');
        $st->execute(['p' => $pdfPath, 'id' => $id]);
    }
}
