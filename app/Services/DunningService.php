<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DunningActionRepository;
use App\Repositories\DunningExclusionRepository;
use App\Repositories\DunningRunRepository;
use App\Repositories\MandateRepository;
use App\Repositories\SettingsRepository;
use App\Support\DateFormatter;
use App\Support\HtmlText;
use App\Support\Logger;

/**
 * Automatisiertes Mahnwesen: ermittelt überfällige sevdesk-Rechnungen,
 * bestimmt die fällige Mahnstufe (1 = Zahlungserinnerung, 2 = 1. Mahnung,
 * 3 = 2. Mahnung), erzeugt den Mahnbeleg in sevdesk und versendet ihn
 * per E-Mail über den konfigurierten Mailer. Ab Stufe 3 verbleibt die
 * Rechnung zur manuellen Inkasso-Übergabe in der Inkasso-Ansicht.
 */
final class DunningService
{
    public const MAX_STAGE = 3;

    private const DEFAULT_SUBJECTS = [
        1 => 'Zahlungserinnerung zur Rechnung {invoice_number}',
        2 => '1. Mahnung zur Rechnung {invoice_number}',
        3 => '2. Mahnung zur Rechnung {invoice_number}',
    ];

    private const DEFAULT_BODIES = [
        1 => "Sehr geehrte/r {name},\n\nsicherlich ist es Ihrer Aufmerksamkeit entgangen, dass die Rechnung {invoice_number} über {amount}, fällig am {due_date}, noch offen ist.\n\nWir möchten Sie freundlich bitten, den Betrag bis zum {pay_until} zu begleichen. Die Zahlungserinnerung finden Sie als PDF im Anhang.\n\nSollte sich Ihre Zahlung mit dieser E-Mail überschnitten haben, betrachten Sie diese Nachricht bitte als gegenstandslos.",
        2 => "Sehr geehrte/r {name},\n\ntrotz unserer Zahlungserinnerung ist die Rechnung {invoice_number} über {amount}, fällig am {due_date}, weiterhin offen.\n\nWir fordern Sie hiermit auf, den offenen Betrag bis spätestens {pay_until} zu begleichen. Die 1. Mahnung finden Sie als PDF im Anhang.\n\nSollte sich Ihre Zahlung mit dieser E-Mail überschnitten haben, betrachten Sie diese Nachricht bitte als gegenstandslos.",
        3 => "Sehr geehrte/r {name},\n\ntrotz Zahlungserinnerung und 1. Mahnung ist die Rechnung {invoice_number} über {amount}, fällig am {due_date}, nach wie vor offen.\n\nWir fordern Sie letztmalig auf, den offenen Betrag bis spätestens {pay_until} zu begleichen. Die 2. Mahnung finden Sie als PDF im Anhang.\n\nSollte bis zu diesem Datum kein Zahlungseingang erfolgen, sehen wir uns gezwungen, die Forderung ohne weitere Ankündigung an ein Inkassobüro zu übergeben. Dadurch entstehen Ihnen erhebliche Mehrkosten.",
    ];

    /** @var array<int, ?string> Kontakt-ID => E-Mail (Cache pro Lauf) */
    private array $emailCache = [];

    /** @var array<int, true>|null IDs stornierter Ursprungsrechnungen (Cache pro Lauf) */
    private ?array $cancelledOriginIds = null;

    public function __construct(private SevdeskClient $client)
    {
    }

    public function stageLabel(int $stage): string
    {
        return match ($stage) {
            1 => 'Zahlungserinnerung',
            2 => '1. Mahnung',
            3 => '2. Mahnung',
            default => $stage . '. Stufe',
        };
    }

    /**
     * Lädt alle offenen, überfälligen Rechnungen (Status 200, ohne payDate,
     * keine Mahnbelege selbst) inkl. aktueller Mahnstufe und Zahlungsart.
     * Wird sowohl vom Mahnlauf als auch von der Inkasso-Ansicht verwendet.
     */
    public function loadOverdueInvoices(): array
    {
        // 1. Offene Rechnungen paginiert laden
        $all = [];
        $limit = 200;
        $offset = 0;
        for ($page = 0; $page < 20; $page++) { // max 4000
            $res = $this->client->getInvoices($limit, $offset, 'contact,paymentMethod');
            $objs = $res['objects'] ?? [];
            if (!is_array($objs) || empty($objs)) {
                break;
            }
            foreach ($objs as $inv) {
                if (is_array($inv)) {
                    $all[] = $inv;
                }
            }
            if (count($objs) < $limit) {
                break;
            }
            $offset += $limit;
        }

        // 2. Alle Mahnungen (invoiceType=MA) laden und nach Ursprungsrechnung
        //    gruppieren – vermeidet N+1 API-Calls.
        $dunningsByOrigin = [];
        $offset = 0;
        for ($page = 0; $page < 20; $page++) {
            $res = $this->client->getDunningInvoices($limit, $offset);
            $objs = $res['objects'] ?? [];
            if (!is_array($objs) || empty($objs)) {
                break;
            }
            foreach ($objs as $ma) {
                if (!is_array($ma) || ($ma['invoiceType'] ?? 'MA') !== 'MA') {
                    continue;
                }
                $originId = 0;
                $origin = $ma['origin'] ?? null;
                if (is_array($origin) && !empty($origin['id'])) {
                    $originId = (int)$origin['id'];
                }
                if ($originId > 0) {
                    $dunningsByOrigin[$originId][] = $ma;
                }
            }
            if (count($objs) < $limit) {
                break;
            }
            $offset += $limit;
        }

        // 2b. Stornorechnungen laden – über Stornierung ausgeglichene Rechnungen
        //     werden nicht (weiter) gemahnt.
        $cancelled = $this->cancelledOriginIds();

        // 3. Filtern: offene, unbezahlte, überfällige Rechnungen
        $today = date('Y-m-d');
        $rows = [];
        foreach ($all as $inv) {
            $invoiceType = $inv['invoiceType'] ?? 'RE';
            if ($invoiceType === 'MA' || $invoiceType === 'SR') {
                continue;
            }
            if ((int)($inv['status'] ?? 0) !== 200) {
                continue;
            }
            if (!empty($inv['payDate'])) {
                continue;
            }

            $id = (int)($inv['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            // Über eine Stornorechnung ausgeglichene Rechnung gilt als erledigt
            if (isset($cancelled[$id])) {
                continue;
            }

            $dueDate = substr(InkassoService::dueDateOf($inv), 0, 10);
            $dunnings = $dunningsByOrigin[$id] ?? [];

            $overdue = $dueDate !== '' && $dueDate < $today;
            if (!$overdue && empty($dunnings)) {
                continue;
            }

            usort($dunnings, static function (array $a, array $b): int {
                return strcmp((string)($a['invoiceDate'] ?? ''), (string)($b['invoiceDate'] ?? ''));
            });

            $sumGross = InkassoService::invoiceAmount($inv);
            $totalClaim = $sumGross;
            $lastDunningDate = '';
            if (!empty($dunnings)) {
                $last = $dunnings[count($dunnings) - 1];
                $totalClaim = max($totalClaim, InkassoService::invoiceAmount($last));
                $lastDunningDate = substr((string)($last['invoiceDate'] ?? ''), 0, 10);
            }

            $daysOverdue = 0;
            if ($overdue) {
                $ts = strtotime($dueDate);
                if ($ts !== false) {
                    $daysOverdue = max(0, (int)floor((time() - $ts) / 86400));
                }
            }

            $contactId = null;
            $contactName = '';
            $contact = $inv['contact'] ?? null;
            if (is_array($contact)) {
                $contactId = !empty($contact['id']) ? (int)$contact['id'] : null;
                $contactName = $this->extractContactName($contact);
            }
            if ($contactName === '' || $contactName === 'Unbekannt') {
                $fallback = trim((string)($inv['customerName'] ?? ($inv['contactName'] ?? '')));
                if ($fallback !== '') {
                    $contactName = $fallback;
                }
            }

            // Zahlungsart (für SEPA-Lastschrift-Ausschluss)
            $pmId = null;
            $pmName = '';
            $pmRef = $inv['paymentMethodId'] ?? ($inv['paymentMethod'] ?? null);
            if (is_array($pmRef)) {
                $pmId = $pmRef['id'] ?? ($pmRef['paymentMethod']['id'] ?? null);
                $pmName = trim((string)($pmRef['name'] ?? ($pmRef['paymentMethodName'] ?? '')));
            } elseif (!empty($inv['paymentMethodId'])) {
                $pmId = $inv['paymentMethodId'];
            }
            if ($pmName === '' && is_array($inv['paymentMethod'] ?? null)) {
                $pmName = trim((string)(($inv['paymentMethod']['name'] ?? '') ?: ($inv['paymentMethod']['paymentMethodName'] ?? '')));
                if (!$pmId) {
                    $pmId = $inv['paymentMethod']['id'] ?? null;
                }
            }

            $rows[] = [
                'id' => $id,
                'invoiceNumber' => (string)($inv['invoiceNumber'] ?? ''),
                'contact_id' => $contactId,
                'contact_name' => $contactName !== '' ? $contactName : 'Unbekannt',
                'dueDate' => $dueDate,
                'days_overdue' => $daysOverdue,
                'sumGross' => $sumGross,
                'total_claim' => $totalClaim,
                'currency' => (string)($inv['currency'] ?? 'EUR'),
                'dunning_level' => count($dunnings),
                'last_dunning_date' => $lastDunningDate,
                'payment_method_id' => $pmId ? (int)$pmId : null,
                'payment_method' => $pmName,
            ];
        }

        // Sort: höchste Mahnstufe zuerst, dann am längsten überfällig
        usort($rows, static function (array $a, array $b): int {
            $cmp = (int)($b['dunning_level'] ?? 0) <=> (int)($a['dunning_level'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int)($b['days_overdue'] ?? 0) <=> (int)($a['days_overdue'] ?? 0);
        });

        return $rows;
    }

    /**
     * Lädt alle Stornorechnungen (invoiceType=SR) und liefert die Menge der
     * IDs der stornierten Ursprungsrechnungen. Eine über eine Stornorechnung
     * ausgeglichene ("quasi bezahlte") Rechnung darf nicht mehr gemahnt werden,
     * auch wenn sevdesk ihren Status nicht zuverlässig auf "bezahlt" setzt.
     *
     * @return array<int, true>
     */
    public function cancelledOriginIds(): array
    {
        if ($this->cancelledOriginIds !== null) {
            return $this->cancelledOriginIds;
        }

        $cancelled = [];
        $limit = 200;
        $offset = 0;
        for ($page = 0; $page < 20; $page++) { // max 4000
            try {
                $res = $this->client->getCancellationInvoices($limit, $offset);
            } catch (\Throwable $e) {
                // Endpoint nicht verfügbar: ohne Storno-Erkennung fortfahren
                Logger::error('Mahnwesen: Stornorechnungen konnten nicht geladen werden', $e);
                break;
            }
            $objs = $res['objects'] ?? [];
            if (!is_array($objs) || empty($objs)) {
                break;
            }
            foreach ($objs as $sr) {
                if (!is_array($sr) || ($sr['invoiceType'] ?? 'SR') !== 'SR') {
                    continue;
                }
                $origin = $sr['origin'] ?? null;
                if (is_array($origin) && !empty($origin['id'])) {
                    $cancelled[(int)$origin['id']] = true;
                }
            }
            if (count($objs) < $limit) {
                break;
            }
            $offset += $limit;
        }

        $this->cancelledOriginIds = $cancelled;
        return $cancelled;
    }

    /**
     * Diagnostiziert für eine konkrete Rechnung (Nummer oder sevdesk-ID),
     * wie sie in sevdesk vorliegt und ob sie über eine Stornorechnung als
     * erledigt erkannt wird. Rein lesend – verändert nichts in sevdesk.
     *
     * @return array{
     *   found: bool,
     *   needle: string,
     *   invoice: array<string,mixed>|null,
     *   sr_total: int,
     *   matched: array<int, array{id:int, invoiceNumber:string, invoiceDate:string}>,
     *   recognized: bool,
     *   sample_sr_fields: array<string, scalar|null>
     * }
     */
    public function diagnoseInvoice(string $needle): array
    {
        $needle = trim($needle);
        $result = [
            'found' => false,
            'needle' => $needle,
            'invoice' => null,
            'sr_total' => 0,
            'matched' => [],
            'recognized' => false,
            'sample_sr_fields' => [],
        ];
        if ($needle === '') {
            return $result;
        }

        // 1. Rechnung anhand Nummer oder ID finden
        $target = null;
        if (ctype_digit($needle)) {
            $target = $this->unwrap($this->client->getInvoice((int)$needle, 'contact'));
            if (!is_array($target) || (int)($target['id'] ?? 0) <= 0) {
                $target = null;
            }
        }
        if ($target === null) {
            $limit = 200;
            $offset = 0;
            for ($page = 0; $page < 30; $page++) {
                $res = $this->client->getInvoices($limit, $offset, 'contact');
                $objs = $res['objects'] ?? [];
                if (!is_array($objs) || empty($objs)) {
                    break;
                }
                foreach ($objs as $inv) {
                    if (is_array($inv) && (string)($inv['invoiceNumber'] ?? '') === $needle) {
                        $target = $inv;
                        break 2;
                    }
                }
                if (count($objs) < $limit) {
                    break;
                }
                $offset += $limit;
            }
        }

        if (!is_array($target) || (int)($target['id'] ?? 0) <= 0) {
            return $result;
        }

        $invoiceId = (int)$target['id'];
        $result['found'] = true;
        $result['invoice'] = [
            'id' => $invoiceId,
            'invoiceNumber' => (string)($target['invoiceNumber'] ?? ''),
            'invoiceType' => (string)($target['invoiceType'] ?? ''),
            'status' => (string)($target['status'] ?? ''),
            'payDate' => $target['payDate'] ?? null,
            'sumGross' => $target['sumGross'] ?? null,
            'amount' => InkassoService::invoiceAmount($target),
            'dueDate' => InkassoService::dueDateOf($target),
        ];

        // 2. Stornorechnungen laden und nach Verknüpfung zu dieser Rechnung suchen
        $srTotal = 0;
        $matched = [];
        $sampleFields = [];
        $limit = 200;
        $offset = 0;
        for ($page = 0; $page < 20; $page++) {
            try {
                $res = $this->client->getCancellationInvoices($limit, $offset);
            } catch (\Throwable $e) {
                Logger::error('Mahnwesen: Stornorechnungen konnten nicht geladen werden (Diagnose)', $e);
                break;
            }
            $objs = $res['objects'] ?? [];
            if (!is_array($objs) || empty($objs)) {
                break;
            }
            foreach ($objs as $sr) {
                if (!is_array($sr)) {
                    continue;
                }
                $srTotal++;
                if (empty($sampleFields)) {
                    foreach ($sr as $k => $v) {
                        $sampleFields[(string)$k] = is_scalar($v) || $v === null ? $v : ('(' . gettype($v) . ')');
                    }
                }
                $origin = $sr['origin'] ?? null;
                $originId = is_array($origin) ? (int)($origin['id'] ?? 0) : 0;
                if ($originId === $invoiceId) {
                    $matched[] = [
                        'id' => (int)($sr['id'] ?? 0),
                        'invoiceNumber' => (string)($sr['invoiceNumber'] ?? ''),
                        'invoiceDate' => (string)($sr['invoiceDate'] ?? ''),
                    ];
                }
            }
            if (count($objs) < $limit) {
                break;
            }
            $offset += $limit;
        }

        $result['sr_total'] = $srTotal;
        $result['matched'] = $matched;
        $result['recognized'] = !empty($matched);
        // Feldnamen einer Beispiel-Stornorechnung nur zeigen, wenn keine
        // Verknüpfung gefunden wurde – hilft, ein abweichendes Verweis-Feld zu finden.
        if (empty($matched) && $srTotal > 0) {
            $result['sample_sr_fields'] = $sampleFields;
        }

        return $result;
    }

    /**
     * Bestimmt die nächste fällige Mahnstufe einer Rechnung oder null,
     * wenn (noch) keine Stufe fällig ist bzw. die Eskalation abgeschlossen
     * ist (Stufe 3 erreicht -> manuelle Inkasso-Übergabe).
     */
    public function determineNextStage(array $row, array $settings): ?int
    {
        $level = (int)($row['dunning_level'] ?? 0);
        if ($level >= self::MAX_STAGE) {
            return null;
        }
        $stage = $level + 1;

        $base = $level === 0
            ? substr((string)($row['dueDate'] ?? ''), 0, 10)
            : substr((string)($row['last_dunning_date'] ?? ''), 0, 10);
        if ($base === '') {
            return null;
        }

        $days = (int)($settings['dunning_days_stage' . $stage] ?? 7);
        $ts = strtotime($base . ' +' . $days . ' days');
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts) <= date('Y-m-d') ? $stage : null;
    }

    /**
     * Prüft Ausschlussgründe. Liefert den Grund als String oder null.
     *
     * @param array<int,true> $exclInvoiceIds
     * @param array<int,true> $exclContactIds
     */
    public function exclusionReason(array $row, array $settings, array $exclInvoiceIds, array $exclContactIds, MandateRepository $mandateRepo): ?string
    {
        $invoiceId = (int)($row['id'] ?? 0);
        $contactId = (int)($row['contact_id'] ?? 0);

        if ($invoiceId > 0 && isset($exclInvoiceIds[$invoiceId])) {
            return 'Rechnung steht auf der Ausschlussliste';
        }
        if ($contactId > 0 && isset($exclContactIds[$contactId])) {
            return 'Kontakt steht auf der Ausschlussliste';
        }

        // Rechnungen mit aktivem Ratenzahlungsplan werden nicht regulär gemahnt
        if ($invoiceId > 0 && (new \App\Repositories\InstallmentPlanRepository())->hasActivePlan($invoiceId)) {
            return 'Aktiver Ratenzahlungsplan vorhanden';
        }

        if (!empty($settings['dunning_skip_sepa'])) {
            $pmName = (string)($row['payment_method'] ?? '');
            $pmLower = function_exists('mb_strtolower') ? mb_strtolower($pmName) : strtolower($pmName);
            if (str_contains($pmLower, 'lastschrift')) {
                return 'Zahlungsart SEPA-Lastschrift (' . $pmName . ')';
            }
            if ($contactId > 0) {
                $m = $mandateRepo->findByContactId($contactId);
                if ($m && (($m['status'] ?? '') === 'active')) {
                    return 'Aktives SEPA-Mandat vorhanden (' . (string)($m['mandate_reference'] ?? '') . ')';
                }
            }
        }

        return null;
    }

    /**
     * Haupt-E-Mail-Adresse des sevdesk-Kontakts (CommunicationWay vom Typ EMAIL).
     */
    public function resolveContactEmail(int $contactId): ?string
    {
        if ($contactId <= 0) {
            return null;
        }
        if (array_key_exists($contactId, $this->emailCache)) {
            return $this->emailCache[$contactId];
        }

        $email = null;
        try {
            // Bevorzugt die als "Haupt" markierte Adresse, sonst die erste
            foreach ([true, false] as $mainOnly) {
                $res = $this->client->getCommunicationWays($contactId, 'EMAIL', $mainOnly);
                $objs = $res['objects'] ?? [];
                if (is_array($objs)) {
                    foreach ($objs as $cw) {
                        if (!is_array($cw)) {
                            continue;
                        }
                        $value = trim((string)($cw['value'] ?? ''));
                        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $email = $value;
                            break 2;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::error('Mahnwesen: Kontakt-E-Mail konnte nicht geladen werden (Contact ' . $contactId . ')', $e);
        }

        $this->emailCache[$contactId] = $email;
        return $email;
    }

    /**
     * Ermittelt alle fälligen Mahnaktionen und stellt sie in die Queue
     * (dunning_actions, Status pending). Bereits vorhandene Stufen werden
     * über den Unique-Key ignoriert.
     */
    public function queueDueActions(array $settings, ?callable $log = null): array
    {
        $log ??= static function (string $line): void {};

        $counters = ['candidates' => 0, 'queued' => 0, 'skipped' => 0, 'errors' => 0];

        $rows = $this->loadOverdueInvoices();
        $counters['candidates'] = count($rows);

        $exclRepo = new DunningExclusionRepository();
        $exclInvoiceIds = $exclRepo->excludedInvoiceIds();
        $exclContactIds = $exclRepo->excludedContactIds();
        $mandateRepo = new MandateRepository();
        $actionRepo = new DunningActionRepository();

        foreach ($rows as $row) {
            $stage = $this->determineNextStage($row, $settings);
            if ($stage === null) {
                continue;
            }

            $label = $row['invoiceNumber'] !== '' ? $row['invoiceNumber'] : ('#' . $row['id']);

            $reason = $this->exclusionReason($row, $settings, $exclInvoiceIds, $exclContactIds, $mandateRepo);
            if ($reason !== null) {
                $counters['skipped']++;
                $log('Übersprungen: Rechnung ' . $label . ' – ' . $reason);
                continue;
            }

            $email = $this->resolveContactEmail((int)($row['contact_id'] ?? 0));

            $inserted = $actionRepo->insertPending([
                'sevdesk_invoice_id' => (int)$row['id'],
                'invoice_number' => (string)$row['invoiceNumber'],
                'sevdesk_contact_id' => $row['contact_id'],
                'contact_name' => (string)$row['contact_name'],
                'stage' => $stage,
                'amount' => (float)$row['total_claim'],
                'currency' => (string)$row['currency'],
                'due_date' => $row['dueDate'] !== '' ? $row['dueDate'] : null,
                'recipient_email' => $email,
            ]);

            if ($inserted) {
                $counters['queued']++;
                $log('Vorgemerkt: ' . $this->stageLabel($stage) . ' für Rechnung ' . $label
                    . ($email === null ? ' (Achtung: keine E-Mail am Kontakt)' : ''));
            }
        }

        return $counters;
    }

    /**
     * Führt eine vorgemerkte Mahnaktion aus: Re-Checks gegen sevdesk,
     * Mahnbeleg erzeugen, PDF holen, E-Mail versenden, Beleg auf
     * "versendet" setzen. Liefert den Ergebnis-Status:
     * 'sent' | 'skipped' | 'failed' | 'test'.
     */
    public function executeAction(array $action, array $settings, ?int $userId = null, ?callable $log = null): string
    {
        $log ??= static function (string $line): void {};

        $actionRepo = new DunningActionRepository();
        $actionId = (int)($action['id'] ?? 0);
        $invoiceId = (int)($action['sevdesk_invoice_id'] ?? 0);
        $stage = (int)($action['stage'] ?? 0);
        $label = (string)($action['invoice_number'] ?? '') !== '' ? (string)$action['invoice_number'] : ('#' . $invoiceId);

        try {
            // a) Re-Check: Rechnung noch offen und unbezahlt?
            $invoice = $this->unwrap($this->client->getInvoice($invoiceId, 'contact'));
            if (!is_array($invoice) || (int)($invoice['id'] ?? 0) <= 0) {
                $actionRepo->markSkipped($actionId, 'Rechnung in sevdesk nicht mehr gefunden');
                $log('Übersprungen: Rechnung ' . $label . ' in sevdesk nicht gefunden.');
                return 'skipped';
            }
            if ((int)($invoice['status'] ?? 0) !== 200 || !empty($invoice['payDate'])) {
                $actionRepo->markSkipped($actionId, 'Rechnung wurde inzwischen bezahlt oder storniert');
                $log('Übersprungen: Rechnung ' . $label . ' ist inzwischen bezahlt/storniert.');
                return 'skipped';
            }
            // sevdesk setzt den Status einer stornierten Rechnung nicht zuverlässig
            // auf "bezahlt" – Storno daher zusätzlich über die Stornorechnung erkennen.
            if (isset($this->cancelledOriginIds()[$invoiceId])) {
                $actionRepo->markSkipped($actionId, 'Rechnung wurde storniert (Stornorechnung in sevdesk vorhanden)');
                $log('Übersprungen: Rechnung ' . $label . ' wurde storniert.');
                return 'skipped';
            }

            // b) Mahnstufe gegen sevdesk verifizieren (Source of truth) –
            //    verhindert Doppel-Mahnungen, auch bei manuell erstellten Belegen
            $existing = $this->countDunnings($invoiceId);
            if ($existing !== $stage - 1) {
                $actionRepo->markSkipped($actionId, 'Mahnstufe in sevdesk weicht ab (dort ' . $existing . ' Mahnbelege, erwartet ' . ($stage - 1) . ')');
                $log('Übersprungen: Rechnung ' . $label . ' – Mahnstufe in sevdesk weicht ab.');
                return 'skipped';
            }

            // c) Empfänger-E-Mail sicherstellen
            $email = trim((string)($action['recipient_email'] ?? ''));
            if ($email === '') {
                $email = (string)($this->resolveContactEmail((int)($action['sevdesk_contact_id'] ?? 0)) ?? '');
                if ($email !== '') {
                    $actionRepo->updateRecipientEmail($actionId, $email);
                }
            }
            if ($email === '') {
                $actionRepo->markFailed($actionId, 'Keine E-Mail-Adresse am sevdesk-Kontakt hinterlegt');
                $log('Fehler: Rechnung ' . $label . ' – keine E-Mail-Adresse am Kontakt.');
                return 'failed';
            }

            $placeholders = $this->placeholders($action, $settings);
            $subject = $this->renderTemplate($this->subjectTemplate($stage, $settings), $placeholders);
            [$bodyText, $bodyHtml] = $this->composeBodies($stage, $settings, $placeholders);

            // d) Testmodus: keine Schreiboperationen in sevdesk; die E-Mail
            //    landet über den Mailer ohnehin nur in storage/logs/mail.
            //    Die Aktion bleibt pending, damit der Echtversand später möglich ist.
            if (!empty($settings['smtp_test_mode'])) {
                $pdf = $this->client->getInvoicePdf($invoiceId);
                $mailer = MailerFactory::fromSettings($settings);
                $mailer->send($email, '[TEST] ' . $subject, $bodyText, [[
                    'filename' => $pdf['filename'],
                    'content' => $pdf['content'],
                    'mime' => 'application/pdf',
                ]], $bodyHtml);
                $log('[TEST] Würde ' . $this->stageLabel($stage) . ' für Rechnung ' . $label . ' an ' . $email . ' senden (E-Mail in storage/logs/mail abgelegt, kein Beleg in sevdesk erzeugt).');
                return 'test';
            }

            // e) Mahnbeleg in sevdesk erzeugen
            $reminder = $this->unwrap($this->client->createInvoiceReminder($invoiceId));
            $reminderId = is_array($reminder) ? (int)($reminder['id'] ?? 0) : 0;
            if ($reminderId <= 0) {
                throw new \RuntimeException('sevdesk hat keinen Mahnbeleg geliefert (Rechnung ' . $label . ')');
            }

            // Zahlungsziel der Mahnung setzen (heute + dunning_pay_days)
            $payDays = max(0, (int)($settings['dunning_pay_days'] ?? 7));
            try {
                $this->client->updateInvoice($reminderId, [
                    'dueDate' => date('Y-m-d', strtotime('+' . $payDays . ' days')),
                ]);
            } catch (\Throwable $e) {
                // Zahlungsziel ist optional – sevdesk-Default gilt dann weiter
                Logger::error('Mahnwesen: dueDate des Mahnbelegs ' . $reminderId . ' konnte nicht gesetzt werden', $e);
            }

            // f) PDF des Mahnbelegs holen (preventSendBy=true ändert den Status nicht)
            $pdf = $this->client->getInvoicePdf($reminderId);

            // g) E-Mail versenden – erst danach den Beleg finalisieren
            $mailer = MailerFactory::fromSettings($settings);
            $mailer->send($email, $subject, $bodyText, [[
                'filename' => $this->attachmentName($stage, $label),
                'content' => $pdf['content'],
                'mime' => 'application/pdf',
            ]], $bodyHtml);

            // h) Beleg in sevdesk als versendet markieren
            $this->client->invoiceSendBy($reminderId, 'VM');

            $actionRepo->markSent($actionId, $reminderId, $email, $userId);
            $log('Gesendet: ' . $this->stageLabel($stage) . ' für Rechnung ' . $label . ' an ' . $email . ' (sevdesk-Beleg ' . $reminderId . ').');
            return 'sent';
        } catch (\Throwable $e) {
            Logger::error('Mahnwesen: Aktion ' . $actionId . ' (Rechnung ' . $invoiceId . ', Stufe ' . $stage . ') fehlgeschlagen', $e);
            $actionRepo->markFailed($actionId, $e->getMessage()
                . ' – Hinweis: Falls der Mahnbeleg bereits in sevdesk erzeugt wurde, dort prüfen/löschen, bevor erneut versendet wird.');
            $log('Fehler: Rechnung ' . $label . ' – ' . $e->getMessage());
            return 'failed';
        }
    }

    /**
     * Kompletter Mahnlauf: Queue befüllen und – im Auto-Modus – direkt
     * ausführen. Mit $queueOnly=true (manueller Scan) wird nur vorgemerkt.
     */
    public function runCron(string $trigger, bool $queueOnly = false): array
    {
        $settings = (new SettingsRepository())->get();
        $mode = (($settings['dunning_mode'] ?? 'review') === 'auto') ? 'auto' : 'review';

        $counters = ['candidates' => 0, 'queued' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0];

        if (empty($settings['dunning_enabled']) && $trigger !== 'manual') {
            return $counters + ['ran' => false, 'mode' => $mode, 'log' => 'Mahnautomatik ist deaktiviert.'];
        }

        $pdo = Db::pdo();
        $lock = $pdo->query("SELECT GET_LOCK('sepa_kb_dunning', 0)")->fetchColumn();
        if ((int)$lock !== 1) {
            return $counters + ['ran' => false, 'mode' => $mode, 'log' => 'Übersprungen: es läuft bereits ein anderer Mahnlauf.'];
        }

        $runRepo = new DunningRunRepository();
        $runId = $runRepo->start($trigger, $mode);

        $logLines = [];
        $log = static function (string $line) use (&$logLines): void {
            $logLines[] = '[' . date('H:i:s') . '] ' . $line;
        };

        try {
            $queueCounters = $this->queueDueActions($settings, $log);
            $counters['candidates'] = $queueCounters['candidates'];
            $counters['queued'] = $queueCounters['queued'];
            $counters['skipped'] = $queueCounters['skipped'];

            if (!$queueOnly && $mode === 'auto') {
                if (!MailerFactory::isConfigured($settings)) {
                    $counters['errors']++;
                    $log('Fehler: E-Mail-Versand ist nicht konfiguriert – keine Mahnungen versendet.');
                } else {
                    $pending = (new DunningActionRepository())->findPending();
                    foreach ($pending as $action) {
                        $result = $this->executeAction($action, $settings, null, $log);
                        if ($result === 'sent') {
                            $counters['sent']++;
                        } elseif ($result === 'skipped') {
                            $counters['skipped']++;
                        } elseif ($result === 'failed') {
                            $counters['errors']++;
                        }
                        // 'test' bleibt pending und wird nicht gezählt
                    }
                }
            }
        } catch (\Throwable $e) {
            $counters['errors']++;
            $log('Lauf abgebrochen: ' . $e->getMessage());
            Logger::error('Mahnlauf fehlgeschlagen', $e);
        } finally {
            $runRepo->finish($runId, $counters, implode("\n", $logLines));
            try {
                $pdo->query("SELECT RELEASE_LOCK('sepa_kb_dunning')");
            } catch (\Throwable $e) {
                // Lock wird spätestens mit der Verbindung freigegeben
            }
        }

        return $counters + ['ran' => true, 'mode' => $mode, 'run_id' => $runId, 'log' => implode("\n", $logLines)];
    }

    public function subjectTemplate(int $stage, array $settings): string
    {
        $tpl = trim((string)($settings['dunning_subject_' . $stage] ?? ''));
        return $tpl !== '' ? $tpl : (self::DEFAULT_SUBJECTS[$stage] ?? self::DEFAULT_SUBJECTS[1]);
    }

    public function bodyTemplate(int $stage, array $settings): string
    {
        $tpl = trim(str_replace(["\r\n", "\r"], "\n", (string)($settings['dunning_body_' . $stage] ?? '')));
        return $tpl !== '' ? $tpl : (self::DEFAULT_BODIES[$stage] ?? self::DEFAULT_BODIES[1]);
    }

    public function renderTemplate(string $template, array $placeholders): string
    {
        $search = [];
        $replace = [];
        foreach ($placeholders as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = (string)$value;
        }
        return str_replace($search, $replace, $template);
    }

    /**
     * Baut den Mahntext als [Plaintext, HTML]. Vorlage und Signatur können
     * aus dem WYSIWYG-Editor als HTML kommen oder (Alt-Bestand/Defaults)
     * reiner Text sein – beides wird in beide Varianten überführt.
     *
     * @return array{0: string, 1: string}
     */
    private function composeBodies(int $stage, array $settings, array $placeholders): array
    {
        $tpl = $this->bodyTemplate($stage, $settings);

        if (HtmlText::isHtml($tpl)) {
            $htmlPlaceholders = array_map(
                static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES),
                $placeholders
            );
            $bodyHtml = $this->renderTemplate($tpl, $htmlPlaceholders);
            $bodyText = $this->renderTemplate(HtmlText::toPlain($tpl), $placeholders);
        } else {
            $bodyText = $this->renderTemplate($tpl, $placeholders);
            $bodyHtml = HtmlText::fromPlain($bodyText);
        }

        $signature = trim(str_replace(["\r\n", "\r"], "\n", (string)($settings['inkasso_signature'] ?? '')));
        if ($signature === '') {
            $signature = 'Mit freundlichen Grüßen';
        }
        $sig = HtmlText::variants($signature);

        $bodyText .= "\n\n" . $sig['text'];
        $bodyHtml .= '<br><br>' . $sig['html'];

        $bodyHtml = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1b1f24">'
            . $bodyHtml . '</div>';

        return [$bodyText, $bodyHtml];
    }

    private function placeholders(array $action, array $settings): array
    {
        $payDays = max(0, (int)($settings['dunning_pay_days'] ?? 7));
        $stage = (int)($action['stage'] ?? 1);

        $dueDisplay = '';
        $due = trim((string)($action['due_date'] ?? ''));
        if ($due !== '') {
            $dueDisplay = DateFormatter::toDisplay(substr($due, 0, 10));
        }

        return [
            'name' => (string)($action['contact_name'] ?? ''),
            'invoice_number' => (string)($action['invoice_number'] ?? ''),
            'amount' => number_format((float)($action['amount'] ?? 0), 2, ',', '.') . ' ' . (string)($action['currency'] ?? 'EUR'),
            'due_date' => $dueDisplay !== '' ? $dueDisplay : '-',
            'pay_until' => DateFormatter::toDisplay(date('Y-m-d', strtotime('+' . $payDays . ' days'))),
            'stage_label' => $this->stageLabel($stage),
        ];
    }

    /**
     * Zählt die in sevdesk vorhandenen Mahnbelege zur Rechnung
     * (Filterung wie InkassoService::buildHandover()).
     */
    private function countDunnings(int $invoiceId): int
    {
        $res = $this->client->getDunnings($invoiceId);
        $objs = $res['objects'] ?? [];
        $count = 0;
        if (is_array($objs)) {
            $seen = [];
            foreach ($objs as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $did = (int)($d['id'] ?? 0);
                if ($did <= 0 || $did === $invoiceId || isset($seen[$did])) {
                    continue;
                }
                if (($d['invoiceType'] ?? 'MA') !== 'MA') {
                    continue;
                }
                $seen[$did] = true;
                $count++;
            }
        }
        return $count;
    }

    private function attachmentName(int $stage, string $invoiceLabel): string
    {
        $prefix = $stage === 1 ? 'Zahlungserinnerung' : 'Mahnung_' . ($stage - 1);
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $invoiceLabel) ?? '';
        $safe = trim($safe, '-') ?: 'beleg';
        return $prefix . '_' . $safe . '.pdf';
    }

    private function unwrap(array $res): ?array
    {
        $obj = $res['objects'] ?? $res;
        if (is_array($obj) && isset($obj[0])) {
            $obj = $obj[0];
        }
        return is_array($obj) ? $obj : null;
    }

    private function extractContactName(array $contact): string
    {
        $given = '';
        foreach (['surename', 'givenname', 'givenName', 'firstName', 'firstname'] as $k) {
            if (!empty($contact[$k])) {
                $given = trim((string)$contact[$k]);
                break;
            }
        }

        $family = '';
        foreach (['familyname', 'familyName', 'lastName', 'lastname', 'surname'] as $k) {
            if (!empty($contact[$k])) {
                $family = trim((string)$contact[$k]);
                break;
            }
        }

        if ($given !== '' || $family !== '') {
            $full = trim($given . ' ' . $family);
            return $full !== '' ? $full : 'Unbekannt';
        }

        $name = trim((string)($contact['name'] ?? ''));
        $name2 = trim((string)($contact['name2'] ?? ($contact['name_2'] ?? '')));
        if ($name !== '' && $name2 !== '') {
            return trim($name . ' ' . $name2);
        }
        if ($name !== '') {
            return $name;
        }
        if ($name2 !== '') {
            return $name2;
        }

        return 'Unbekannt';
    }
}
