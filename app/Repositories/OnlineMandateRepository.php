<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class OnlineMandateRepository
{
    private const EXTRA_COLUMNS = [
        'payment_type' => "ENUM('OOFF','RCUR') NULL",
        'signed_ip' => 'VARCHAR(45) NULL',
        'signed_user_agent' => 'VARCHAR(255) NULL',
    ];

    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS online_mandates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(80) NOT NULL,
            status ENUM('open','signed','revoked') NOT NULL DEFAULT 'open',
            created_by BIGINT UNSIGNED NULL,
            sevdesk_contact_id BIGINT UNSIGNED NOT NULL,
            contact_name VARCHAR(190) NOT NULL,
            mandate_reference VARCHAR(35) NOT NULL,
            debtor_name VARCHAR(190) NULL,
            debtor_street VARCHAR(190) NULL,
            debtor_zip VARCHAR(20) NULL,
            debtor_city VARCHAR(120) NULL,
            debtor_country VARCHAR(2) NULL,
            debtor_iban VARCHAR(34) NULL,
            debtor_bic VARCHAR(11) NULL,
            debtor_email VARCHAR(190) NULL,
            payment_type ENUM('OOFF','RCUR') NULL,
            signed_place VARCHAR(120) NULL,
            signed_date DATE NULL,
            signature_path VARCHAR(255) NULL,
            pdf_path VARCHAR(255) NULL,
            signed_at DATETIME NULL,
            signed_ip VARCHAR(45) NULL,
            signed_user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_online_mandates_token (token),
            UNIQUE KEY uq_online_mandates_reference (mandate_reference),
            KEY ix_online_mandates_status (status),
            KEY ix_online_mandates_contact (sevdesk_contact_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $rows = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'online_mandates'")->fetchAll();
        if (!is_array($rows)) {
            return;
        }
        $existing = array_map(static fn ($row) => (string)($row['COLUMN_NAME'] ?? ''), $rows);
        foreach (self::EXTRA_COLUMNS as $column => $definition) {
            if (in_array($column, $existing, true)) {
                continue;
            }
            $pdo->exec("ALTER TABLE online_mandates ADD COLUMN {$column} {$definition}");
        }
    }

    public function create(array $data): int
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'INSERT INTO online_mandates
            (token,status,created_by,sevdesk_contact_id,contact_name,mandate_reference,debtor_email)
            VALUES
            (:token,:status,:created_by,:sevdesk_contact_id,:contact_name,:mandate_reference,:debtor_email)';
        $st = $pdo->prepare($sql);
        $st->execute([
            'token' => (string)$data['token'],
            'status' => (string)($data['status'] ?? 'open'),
            'created_by' => $data['created_by'],
            'sevdesk_contact_id' => (int)$data['sevdesk_contact_id'],
            'contact_name' => (string)$data['contact_name'],
            'mandate_reference' => (string)$data['mandate_reference'],
            'debtor_email' => (string)($data['debtor_email'] ?? ''),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public function all(): array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        return $pdo->query('SELECT * FROM online_mandates ORDER BY id DESC')->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM online_mandates WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM online_mandates WHERE token = :t LIMIT 1');
        $st->execute(['t' => $token]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function markSigned(int $id, array $data): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $sql = 'UPDATE online_mandates SET
            status = :status,
            debtor_name = :debtor_name,
            debtor_street = :debtor_street,
            debtor_zip = :debtor_zip,
            debtor_city = :debtor_city,
            debtor_country = :debtor_country,
            debtor_iban = :debtor_iban,
            debtor_bic = :debtor_bic,
            payment_type = :payment_type,
            signed_place = :signed_place,
            signed_date = :signed_date,
            signature_path = :signature_path,
            pdf_path = :pdf_path,
            signed_at = :signed_at,
            signed_ip = :signed_ip,
            signed_user_agent = :signed_user_agent,
            updated_at = NOW()
            WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute([
            'status' => 'signed',
            'debtor_name' => (string)$data['debtor_name'],
            'debtor_street' => (string)$data['debtor_street'],
            'debtor_zip' => (string)$data['debtor_zip'],
            'debtor_city' => (string)$data['debtor_city'],
            'debtor_country' => (string)($data['debtor_country'] ?? 'DE'),
            'debtor_iban' => (string)$data['debtor_iban'],
            'debtor_bic' => (string)($data['debtor_bic'] ?? ''),
            'payment_type' => (string)($data['payment_type'] ?? ''),
            'signed_place' => (string)($data['signed_place'] ?? ''),
            'signed_date' => (string)$data['signed_date'],
            'signature_path' => (string)$data['signature_path'],
            'pdf_path' => (string)$data['pdf_path'],
            'signed_at' => (string)($data['signed_at'] ?? date('Y-m-d H:i:s')),
            'signed_ip' => (string)($data['signed_ip'] ?? ''),
            'signed_user_agent' => (string)($data['signed_user_agent'] ?? ''),
            'id' => $id,
        ]);
    }

    
    public function updatePdfPath(int $id, string $pdfPath): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE online_mandates SET pdf_path = :p, updated_at = NOW() WHERE id = :id');
        $st->execute(['p' => $pdfPath, 'id' => $id]);
    }

public function revoke(int $id): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE online_mandates SET status = "revoked", updated_at = NOW() WHERE id = :id');
        $st->execute(['id' => $id]);
    }
}
