<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class MandateRepository
{
    public function all(string $q = ''): array
    {
        $pdo = Db::pdo();
        if ($q !== '') {
            $st = $pdo->prepare('SELECT * FROM mandates WHERE debtor_name LIKE :q OR mandate_reference LIKE :q OR sevdesk_contact_id LIKE :q ORDER BY id DESC');
            $st->execute(['q' => '%' . $q . '%']);
            return $st->fetchAll();
        }
        return $pdo->query('SELECT * FROM mandates ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM mandates WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function findByContactId(int $contactId): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM mandates WHERE sevdesk_contact_id = :cid LIMIT 1');
        $st->execute(['cid' => $contactId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Db::pdo();
        $sql = 'INSERT INTO mandates
            (sevdesk_contact_id,debtor_name,debtor_iban,debtor_bic,mandate_reference,mandate_date,scheme,sequence_mode,status,notes,attachment_path)
            VALUES
            (:sevdesk_contact_id,:debtor_name,:debtor_iban,:debtor_bic,:mandate_reference,:mandate_date,:scheme,:sequence_mode,:status,:notes,:attachment_path)';
        $st = $pdo->prepare($sql);
        $st->execute($this->normalizeData($data));
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Db::pdo();
        $sql = 'UPDATE mandates SET
            sevdesk_contact_id = :sevdesk_contact_id,
            debtor_name = :debtor_name,
            debtor_iban = :debtor_iban,
            debtor_bic = :debtor_bic,
            mandate_reference = :mandate_reference,
            mandate_date = :mandate_date,
            scheme = :scheme,
            sequence_mode = :sequence_mode,
            status = :status,
            notes = :notes,
            attachment_path = :attachment_path,
            updated_at = NOW()
            WHERE id = :id';
        $data = $this->normalizeData($data);
        $data['id'] = $id;
        $st = $pdo->prepare($sql);
        $st->execute($data);
    }

    
    public function upsertByContactId(int $sevdeskContactId, array $data): int
    {
        $existing = $this->findByContactId($sevdeskContactId);
        if ($existing) {
            $this->update((int)$existing['id'], array_merge($existing, $data));
            return (int)$existing['id'];
        }

        $data['sevdesk_contact_id'] = $sevdeskContactId;
        $this->create($data);
        $created = $this->findByContactId($sevdeskContactId);
        return (int)($created['id'] ?? 0);
    }

public function setStatus(int $id, string $status): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE mandates SET status = :s, updated_at = NOW() WHERE id = :id');
        $st->execute(['s' => $status, 'id' => $id]);
    }

    public function markUsed(int $id, string $seq): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE mandates SET last_sequence_type = :seq, last_export_at = NOW(), updated_at = NOW() WHERE id = :id');
        $st->execute(['seq' => $seq, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $pdo = \App\Services\Db::pdo();
        $st = $pdo->prepare("DELETE FROM mandates WHERE id = :id");
        $st->execute([':id' => $id]);
    }

    private function normalizeData(array $data): array
    {
        $keys = [
            'sevdesk_contact_id',
            'debtor_name',
            'debtor_iban',
            'debtor_bic',
            'mandate_reference',
            'mandate_date',
            'scheme',
            'sequence_mode',
            'status',
            'notes',
            'attachment_path',
        ];

        $normalized = array_intersect_key($data, array_flip($keys));
        foreach ($keys as $key) {
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = null;
            }
        }

        return $normalized;
    }

}
