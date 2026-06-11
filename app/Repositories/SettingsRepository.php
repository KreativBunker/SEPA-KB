<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class SettingsRepository
{
    private const EXTRA_COLUMNS = [
        'creditor_street' => 'VARCHAR(190) NULL',
        'creditor_zip' => 'VARCHAR(20) NULL',
        'creditor_city' => 'VARCHAR(120) NULL',
        'creditor_country' => 'VARCHAR(2) NULL',
        'smtp_host' => 'VARCHAR(190) NULL',
        'smtp_port' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 587',
        'smtp_encryption' => "VARCHAR(10) NOT NULL DEFAULT 'tls'",
        'smtp_user' => 'VARCHAR(190) NULL',
        'smtp_pass_encrypted' => 'TEXT NULL',
        'smtp_from_email' => 'VARCHAR(190) NULL',
        'smtp_from_name' => 'VARCHAR(190) NULL',
        'smtp_test_mode' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'inkasso_email' => 'VARCHAR(190) NULL',
        'mail_provider' => "VARCHAR(10) NOT NULL DEFAULT 'smtp'",
        'm365_tenant_id' => 'VARCHAR(190) NULL',
        'm365_client_id' => 'VARCHAR(190) NULL',
        'm365_client_secret_encrypted' => 'TEXT NULL',
        'inkasso_signature' => 'TEXT NULL',
    ];

    private function ensureColumns(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'")->fetchAll();
        if (!is_array($rows)) {
            return;
        }
        $existing = array_map(static fn ($row) => (string)($row['COLUMN_NAME'] ?? ''), $rows);
        foreach (self::EXTRA_COLUMNS as $column => $definition) {
            if (in_array($column, $existing, true)) {
                continue;
            }
            $pdo->exec("ALTER TABLE settings ADD COLUMN {$column} {$definition}");
        }
    }

    public function get(): array
    {
        $this->ensureColumns();
        $pdo = Db::pdo();
        $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
        return $row ?: [];
    }

    public function update(array $data): void
    {
        $this->ensureColumns();
        $pdo = Db::pdo();
        $sql = 'UPDATE settings SET
            creditor_name = :creditor_name,
            creditor_id = :creditor_id,
            creditor_iban = :creditor_iban,
            creditor_bic = :creditor_bic,
            creditor_street = :creditor_street,
            creditor_zip = :creditor_zip,
            creditor_city = :creditor_city,
            creditor_country = :creditor_country,
            initiating_party_name = :initiating_party_name,
            default_scheme = :default_scheme,
            default_days_until_collection = :default_days_until_collection,
            batch_booking = :batch_booking,
            sanitize_text = :sanitize_text,
            include_bic = :include_bic,
            smtp_host = :smtp_host,
            smtp_port = :smtp_port,
            smtp_encryption = :smtp_encryption,
            smtp_user = :smtp_user,
            smtp_pass_encrypted = :smtp_pass_encrypted,
            smtp_from_email = :smtp_from_email,
            smtp_from_name = :smtp_from_name,
            smtp_test_mode = :smtp_test_mode,
            inkasso_email = :inkasso_email,
            mail_provider = :mail_provider,
            m365_tenant_id = :m365_tenant_id,
            m365_client_id = :m365_client_id,
            m365_client_secret_encrypted = :m365_client_secret_encrypted,
            inkasso_signature = :inkasso_signature
            WHERE id = 1';
        $st = $pdo->prepare($sql);
        $st->execute($data);
    }

    public function insertIfMissing(array $data): void
    {
        $this->ensureColumns();
        $pdo = Db::pdo();
        $exists = $pdo->query('SELECT id FROM settings WHERE id=1')->fetch();
        if ($exists) {
            return;
        }
        $sql = 'INSERT INTO settings (id, creditor_name, creditor_id, creditor_iban, creditor_bic, creditor_street, creditor_zip, creditor_city, creditor_country, initiating_party_name, default_scheme, default_days_until_collection, batch_booking, sanitize_text, include_bic)
            VALUES (1,:creditor_name,:creditor_id,:creditor_iban,:creditor_bic,:creditor_street,:creditor_zip,:creditor_city,:creditor_country,:initiating_party_name,:default_scheme,:default_days_until_collection,:batch_booking,:sanitize_text,:include_bic)';
        $st = $pdo->prepare($sql);
        $st->execute($data);
    }
}
