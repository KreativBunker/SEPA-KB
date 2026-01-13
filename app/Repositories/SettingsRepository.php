<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class SettingsRepository
{
    public function get(): array
    {
        $pdo = Db::pdo();
        $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
        return $row ?: [];
    }

    public function update(array $data): void
    {
        $pdo = Db::pdo();
        $sql = 'UPDATE settings SET
            creditor_name = :creditor_name,
            creditor_id = :creditor_id,
            creditor_iban = :creditor_iban,
            creditor_bic = :creditor_bic,
            initiating_party_name = :initiating_party_name,
            default_scheme = :default_scheme,
            default_days_until_collection = :default_days_until_collection,
            batch_booking = :batch_booking,
            sanitize_text = :sanitize_text,
            include_bic = :include_bic
            WHERE id = 1';
        $st = $pdo->prepare($sql);
        $st->execute($data);
    }

    public function insertIfMissing(array $data): void
    {
        $pdo = Db::pdo();
        $exists = $pdo->query('SELECT id FROM settings WHERE id=1')->fetch();
        if ($exists) {
            return;
        }
        $sql = 'INSERT INTO settings (id, creditor_name, creditor_id, creditor_iban, creditor_bic, initiating_party_name, default_scheme, default_days_until_collection, batch_booking, sanitize_text, include_bic)
            VALUES (1,:creditor_name,:creditor_id,:creditor_iban,:creditor_bic,:initiating_party_name,:default_scheme,:default_days_until_collection,:batch_booking,:sanitize_text,:include_bic)';
        $st = $pdo->prepare($sql);
        $st->execute($data);
    }
}
