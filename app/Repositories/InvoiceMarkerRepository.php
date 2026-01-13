<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class InvoiceMarkerRepository
{
    public function exists(int $sevdeskInvoiceId): bool
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT id FROM invoice_export_markers WHERE sevdesk_invoice_id = :id LIMIT 1');
        $st->execute(['id' => $sevdeskInvoiceId]);
        return (bool)$st->fetch();
    }

    public function mark(int $sevdeskInvoiceId, int $runId): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT IGNORE INTO invoice_export_markers (sevdesk_invoice_id, first_export_run_id) VALUES (:iid,:rid)');
        $st->execute(['iid' => $sevdeskInvoiceId, 'rid' => $runId]);
    }


public function mapByInvoiceIds(array $invoiceIds): array
{
    $ids = array_values(array_unique(array_map('intval', $invoiceIds)));
    $ids = array_values(array_filter($ids, fn(int $v): bool => $v > 0));
    if (empty($ids)) {
        return [];
    }

    $pdo = Db::pdo();
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare('SELECT sevdesk_invoice_id, first_export_run_id, first_exported_at FROM invoice_export_markers WHERE sevdesk_invoice_id IN (' . $ph . ')');
    $st->execute($ids);

    $rows = $st->fetchAll();
    $map = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $iid = (int)($r['sevdesk_invoice_id'] ?? 0);
            if ($iid) {
                $map[$iid] = $r;
            }
        }
    }
    return $map;
}
}
