<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExportItemRepository;
use App\Repositories\ExportRunRepository;
use App\Repositories\InstallmentPlanRepository;
use App\Repositories\InstallmentRateRepository;
use App\Repositories\MandateRepository;
use DateTimeImmutable;

final class InstallmentService
{
    /**
     * Berechnet den Ratenplan: gleiche Teilbeträge, Centdifferenz auf die letzte Rate,
     * monatliche Fälligkeiten ab dem ersten Einzugsdatum.
     *
     * @return array<int,array{rate_no:int,amount:float,due_date:string,sequence_type:string}>
     */
    public function buildSchedule(float $total, int $rateCount, int $intervalMonths, string $firstDate, string $seqMode = 'rcur_only'): array
    {
        $rateCount = max(1, $rateCount);
        $intervalMonths = max(1, $intervalMonths);
        $total = round($total, 2);

        $base = floor(($total * 100) / $rateCount) / 100;

        $start = $this->parseDate($firstDate);

        $rates = [];
        for ($i = 1; $i <= $rateCount; $i++) {
            $amount = ($i === $rateCount)
                ? round($total - $base * ($rateCount - 1), 2)
                : $base;

            $rates[] = [
                'rate_no' => $i,
                'amount' => $amount,
                'due_date' => $this->addMonthsClamped($start, ($i - 1) * $intervalMonths)->format('Y-m-d'),
                'sequence_type' => $this->sequenceTypeFor($i, $rateCount, $seqMode),
            ];
        }

        return $rates;
    }

    private function sequenceTypeFor(int $i, int $rateCount, string $seqMode): string
    {
        if ($seqMode !== 'frst_rcur_fnal') {
            return 'RCUR';
        }
        if ($rateCount === 1) {
            return 'OOFF';
        }
        if ($i === 1) {
            return 'FRST';
        }
        if ($i === $rateCount) {
            return 'FNAL';
        }
        return 'RCUR';
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $date = trim($date);
        try {
            if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return new DateTimeImmutable($date);
            }
            return new DateTimeImmutable($date !== '' ? $date : 'today');
        } catch (\Throwable $e) {
            return new DateTimeImmutable('today');
        }
    }

    /** Addiert Monate und klemmt den Tag auf den Monatsletzten (vermeidet 31.01. + 1 Monat = 03.03.). */
    private function addMonthsClamped(DateTimeImmutable $start, int $months): DateTimeImmutable
    {
        if ($months <= 0) {
            return $start;
        }
        $day = (int)$start->format('d');
        $firstOfTarget = $start->modify('first day of this month')->modify('+' . $months . ' months');
        $daysInTarget = (int)$firstOfTarget->format('t');
        $targetDay = min($day, $daysInTarget);
        return $firstOfTarget->modify('+' . ($targetDay - 1) . ' days');
    }

    /**
     * Übernimmt alle fälligen Raten (Status planned, due_date <= cutoff) in einen oder mehrere
     * SEPA-Export-Läufe (run_type=installments). Gruppiert nach Sequenztyp; innerhalb eines Laufs
     * ist jede sevdesk_invoice_id nur einmal vertreten (Unique-Constraint export_items).
     *
     * @return array{runs:int[],queued:int,errors:int,skipped:int}
     */
    public function queueDueRates(string $cutoffDate, string $collectionDate, array $settings, ?int $userId = null): array
    {
        $rateRepo = new InstallmentRateRepository();
        $itemRepo = new ExportItemRepository();
        $runRepo = new ExportRunRepository();
        $mandateRepo = new MandateRepository();
        $val = new ValidationService();

        $due = $rateRepo->dueRates($cutoffDate);

        $runs = [];          // key "seq|bucketIndex" => runId
        $usedInvoiceIds = []; // key "seq|bucketIndex" => [invoiceId => true]
        $bucketIndex = [];   // seq => current bucket index
        $queued = 0;
        $errors = 0;

        $schemeDefault = (string)($settings['default_scheme'] ?? 'CORE');

        foreach ($due as $rate) {
            $seq = (string)($rate['sequence_type'] ?? 'RCUR');
            $invoiceId = (int)($rate['sevdesk_invoice_id'] ?? 0);

            if (!isset($bucketIndex[$seq])) {
                $bucketIndex[$seq] = 0;
            }
            // Bei bereits in diesem Bucket genutzter Rechnungs-ID (inkl. 0 für manuelle Pläne) neuen Bucket öffnen
            $bKey = $seq . '|' . $bucketIndex[$seq];
            if (isset($usedInvoiceIds[$bKey][$invoiceId])) {
                $bucketIndex[$seq]++;
                $bKey = $seq . '|' . $bucketIndex[$seq];
            }

            if (!isset($runs[$bKey])) {
                $runs[$bKey] = $runRepo->create([
                    'title' => 'Ratenzahlung ' . $collectionDate . ' (' . $seq . ')',
                    'collection_date' => $collectionDate,
                    'pain_version' => 'pain.008.001.08',
                    'batch_booking' => (int)($settings['batch_booking'] ?? 0),
                    'scheme_default' => $schemeDefault,
                    'endtoend_strategy' => 'generated',
                    'remittance_template' => (string)($rate['remittance_template'] ?? 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}'),
                    'status' => 'draft',
                    'run_type' => 'installments',
                    'sequence_type' => $seq,
                    'created_by_user_id' => $userId ?? 0,
                ]);
                $usedInvoiceIds[$bKey] = [];
            }
            $runId = $runs[$bKey];
            $usedInvoiceIds[$bKey][$invoiceId] = true;

            $item = $this->buildItem($rate, $val, $mandateRepo);
            $itemId = $itemRepo->insertOne($runId, $item);
            $rateRepo->markQueued((int)$rate['id'], $runId, $itemId);

            if (($item['status'] ?? '') === 'ok') {
                $queued++;
            } else {
                $errors++;
            }
        }

        return [
            'runs' => array_values($runs),
            'queued' => $queued,
            'errors' => $errors,
            'skipped' => 0,
        ];
    }

    private function buildItem(array $rate, ValidationService $val, MandateRepository $mandateRepo): array
    {
        $invoiceId = (int)($rate['sevdesk_invoice_id'] ?? 0);
        $invoiceNumber = (string)($rate['invoice_number'] ?? '');
        $rateNo = (int)($rate['rate_no'] ?? 0);
        $rateCount = (int)($rate['rate_count'] ?? 0);
        $amount = (float)($rate['amount'] ?? 0);

        $remittance = strtr((string)($rate['remittance_template'] ?? ''), [
            '{invoice_number}' => $invoiceNumber,
            '{rate_no}' => (string)$rateNo,
            '{rate_count}' => (string)$rateCount,
        ]);
        if (trim($remittance) === '') {
            $remittance = 'Rate ' . $rateNo . '/' . $rateCount;
        }

        $endToEnd = $invoiceId > 0
            ? ('INV' . $invoiceId . 'R' . $rateNo)
            : ('PLN' . (int)($rate['plan_id'] ?? 0) . 'R' . $rateNo);

        // aktuelle Mandatsdaten bevorzugen, Snapshot aus dem Plan als Fallback
        $debtorName = (string)($rate['debtor_name'] ?? '');
        $debtorIban = (string)($rate['debtor_iban'] ?? '');
        $mandateRef = (string)($rate['mandate_reference'] ?? '');
        $mandateDate = (string)($rate['mandate_date'] ?? '');

        $status = 'ok';
        $error = null;

        $mandateActive = true;
        if (!empty($rate['mandate_id'])) {
            $m = $mandateRepo->find((int)$rate['mandate_id']);
            if ($m) {
                $mandateActive = ($m['status'] ?? '') === 'active';
                $debtorName = (string)($m['debtor_name'] ?? $debtorName);
                $debtorIban = (string)($m['debtor_iban'] ?? $debtorIban);
                $mandateRef = (string)($m['mandate_reference'] ?? $mandateRef);
                $mandateDate = (string)($m['mandate_date'] ?? $mandateDate);
            }
        }

        if (!$mandateActive) {
            $status = 'error';
            $error = 'Mandat ist nicht aktiv';
        } elseif ($mandateRef === '') {
            $status = 'error';
            $error = 'Mandatsreferenz fehlt';
        } elseif (!$val->validateIban($debtorIban)) {
            $status = 'error';
            $error = 'Debtor IBAN ungültig';
        } elseif ($amount <= 0.0) {
            $status = 'error';
            $error = 'Betrag ist 0';
        }

        return [
            'sevdesk_invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'sevdesk_contact_id' => (int)($rate['sevdesk_contact_id'] ?? 0),
            'debtor_name' => $debtorName !== '' ? $debtorName : 'Unbekannt',
            'debtor_iban' => $debtorIban,
            'mandate_reference' => $mandateRef,
            'mandate_date' => $mandateDate !== '' ? $mandateDate : '1970-01-01',
            'sequence_type' => (string)($rate['sequence_type'] ?? 'RCUR'),
            'amount' => $amount,
            'endtoend_id' => $endToEnd,
            'remittance' => $remittance,
            'status' => $status,
            'error_text' => $error,
        ];
    }
}
