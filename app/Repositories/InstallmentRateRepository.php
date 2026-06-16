<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class InstallmentRateRepository
{
    public function ensureTable(): void
    {
        $pdo = Db::pdo();
        $pdo->exec("CREATE TABLE IF NOT EXISTS installment_rates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id BIGINT UNSIGNED NOT NULL,
            rate_no SMALLINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            due_date DATE NOT NULL,
            sequence_type ENUM('FRST','RCUR','OOFF','FNAL') NOT NULL DEFAULT 'RCUR',
            status ENUM('planned','queued','collected','failed','cancelled') NOT NULL DEFAULT 'planned',
            export_run_id BIGINT UNSIGNED NULL,
            export_item_id BIGINT UNSIGNED NULL,
            collected_at DATETIME NULL,
            error_text TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rate_plan_no (plan_id, rate_no),
            KEY ix_rate_status_due (status, due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * @param array<int,array{rate_no:int,amount:float,due_date:string,sequence_type:string}> $rates
     */
    public function createMany(int $planId, array $rates): void
    {
        $this->ensureTable();
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT INTO installment_rates
            (plan_id,rate_no,amount,due_date,sequence_type)
            VALUES (:plan_id,:rate_no,:amount,:due_date,:sequence_type)');
        foreach ($rates as $r) {
            $st->execute([
                'plan_id' => $planId,
                'rate_no' => (int)($r['rate_no'] ?? 0),
                'amount' => (float)($r['amount'] ?? 0),
                'due_date' => (string)($r['due_date'] ?? date('Y-m-d')),
                'sequence_type' => (string)($r['sequence_type'] ?? 'RCUR'),
            ]);
        }
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT * FROM installment_rates WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function forPlan(int $planId): array
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT * FROM installment_rates WHERE plan_id = :pid ORDER BY rate_no ASC');
        $st->execute(['pid' => $planId]);
        return $st->fetchAll();
    }

    /**
     * Fällige, noch nicht eingezogene Raten inkl. Plan-/Mandatsdaten.
     * Liefert nur Raten aktiver Pläne mit Status 'planned' und due_date <= cutoff.
     */
    public function dueRates(string $cutoffDate): array
    {
        $this->ensureTable();
        (new InstallmentPlanRepository())->ensureTable();
        $sql = "SELECT r.*, p.source, p.sevdesk_invoice_id, p.invoice_number, p.sevdesk_contact_id,
                       p.mandate_id, p.debtor_name, p.debtor_iban, p.mandate_reference, p.mandate_date,
                       p.scheme, p.rate_count, p.remittance_template
                FROM installment_rates r
                INNER JOIN installment_plans p ON p.id = r.plan_id
                WHERE r.status = 'planned'
                  AND p.status = 'active'
                  AND r.due_date <= :cutoff
                ORDER BY r.sequence_type ASC, r.due_date ASC, r.id ASC";
        $st = Db::pdo()->prepare($sql);
        $st->execute(['cutoff' => $cutoffDate]);
        return $st->fetchAll();
    }

    public function markQueued(int $rateId, int $runId, int $exportItemId): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE installment_rates SET status = 'queued', export_run_id = :run, export_item_id = :item, error_text = NULL, updated_at = NOW() WHERE id = :id");
        $st->execute(['run' => $runId, 'item' => $exportItemId, 'id' => $rateId]);
    }

    public function markCollectedByExportItem(int $exportItemId, int $runId): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE installment_rates SET status = 'collected', collected_at = NOW(), updated_at = NOW() WHERE export_item_id = :item AND export_run_id = :run AND status = 'queued'");
        $st->execute(['item' => $exportItemId, 'run' => $runId]);
    }

    public function markFailed(int $rateId, string $error): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE installment_rates SET status = 'failed', error_text = :err, updated_at = NOW() WHERE id = :id");
        $st->execute(['err' => $error, 'id' => $rateId]);
    }

    /** Setzt eine fehlgeschlagene Rate zurück, damit sie erneut eingeplant werden kann. */
    public function resetToPlanned(int $rateId): void
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare("UPDATE installment_rates SET status = 'planned', export_run_id = NULL, export_item_id = NULL, error_text = NULL, updated_at = NOW() WHERE id = :id AND status = 'failed'");
        $st->execute(['id' => $rateId]);
    }

    /** Liefert die plan_id zu einem export_item, sofern es eine Rate gibt (für finalize-Tracking). */
    public function planIdByExportItem(int $exportItemId): ?int
    {
        $this->ensureTable();
        $st = Db::pdo()->prepare('SELECT plan_id FROM installment_rates WHERE export_item_id = :item LIMIT 1');
        $st->execute(['item' => $exportItemId]);
        $val = $st->fetchColumn();
        return $val !== false ? (int)$val : null;
    }
}
