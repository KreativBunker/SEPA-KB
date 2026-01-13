<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ExportItemRepository
{
    public function forRun(int $runId): array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM export_items WHERE export_run_id = :id ORDER BY id ASC');
        $st->execute(['id' => $runId]);
        return $st->fetchAll();
    }

    public function createMany(int $runId, array $items): void
    {
        $pdo = Db::pdo();
        $sql = 'INSERT INTO export_items
            (export_run_id,sevdesk_invoice_id,invoice_number,sevdesk_contact_id,debtor_name,debtor_iban,mandate_reference,mandate_date,sequence_type,amount,endtoend_id,remittance,status)
            VALUES
            (:export_run_id,:sevdesk_invoice_id,:invoice_number,:sevdesk_contact_id,:debtor_name,:debtor_iban,:mandate_reference,:mandate_date,:sequence_type,:amount,:endtoend_id,:remittance,:status)';
        $st = $pdo->prepare($sql);

        foreach ($items as $it) {
            $it['export_run_id'] = $runId;
            $st->execute($it);
        }
    }

    public function updateStatus(int $id, string $status, ?string $errorText = null): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE export_items SET status = :s, error_text = :e, updated_at = NOW() WHERE id = :id');
        $st->execute(['s' => $status, 'e' => $errorText, 'id' => $id]);
    }



    public function refreshMandateDataForRun(int $runId): int
    {
        // Immer das aktuellste Mandat (IBAN/Referenz/Datum/Name) nutzen, falls zwischen Auswahl und Export aktualisiert wurde.
        $pdo = Db::pdo();

        $sql = 'UPDATE export_items ei
                INNER JOIN mandates m ON m.sevdesk_contact_id = ei.sevdesk_contact_id
                SET ei.debtor_name = m.debtor_name,
                    ei.debtor_iban = m.debtor_iban,
                    ei.mandate_reference = m.mandate_reference,
                    ei.mandate_date = m.mandate_date,
                    ei.updated_at = NOW()
                WHERE ei.export_run_id = :id
                  AND m.status = "active"';

        $st = $pdo->prepare($sql);
        $st->execute(['id' => $runId]);
        return $st->rowCount();
    }

public function markCompletedForRun(int $runId): void
{
    // DB kompatibel halten, manche Installationen erlauben in export_items.status kein "completed"
    // Marker für "abgeschlossen" wird über invoice_export_markers und export_runs.status=final abgebildet
    $pdo = Db::pdo();
    $st = $pdo->prepare('UPDATE export_items SET updated_at = NOW() WHERE export_run_id = :id AND status = "ok"');
    $st->execute(['id' => $runId]);
}
}
