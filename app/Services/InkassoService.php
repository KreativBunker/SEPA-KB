<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\DateFormatter;

/**
 * Stellt alle Daten für eine Inkasso-Übergabe zusammen:
 * Rechnung, Schuldner inkl. Adresse, Mahnungen und die zugehörigen PDFs.
 */
final class InkassoService
{
    public function __construct(private SevdeskClient $client)
    {
    }

    public function buildHandover(int $invoiceId): array
    {
        $invoice = $this->unwrap($this->client->getInvoice($invoiceId, 'contact'));
        if (!is_array($invoice) || (int)($invoice['id'] ?? 0) <= 0) {
            throw new \RuntimeException('Rechnung ' . $invoiceId . ' wurde in sevdesk nicht gefunden.');
        }

        $invoiceNumber = (string)($invoice['invoiceNumber'] ?? $invoiceId);
        $amountOriginal = (float)($invoice['sumGross'] ?? 0);
        $currency = (string)($invoice['currency'] ?? 'EUR');

        // Schuldner inkl. Adresse
        $contactId = null;
        $contact = $invoice['contact'] ?? null;
        if (is_array($contact) && !empty($contact['id'])) {
            $contactId = (int)$contact['id'];
        }
        $debtor = [
            'id' => $contactId,
            'name' => is_array($contact) ? $this->extractContactName($contact) : 'Unbekannt',
            'street' => '',
            'zip' => '',
            'city' => '',
        ];
        if ($contactId) {
            try {
                $cObj = $this->unwrap($this->client->getContact($contactId, 'addresses'));
                if (is_array($cObj)) {
                    $debtor['name'] = $this->extractContactName($cObj);
                    $addr = $this->extractAddress($cObj);
                    $debtor = array_merge($debtor, $addr);
                }
            } catch (\Throwable $e) {
                // Adresse ist optional, Übergabe nicht daran scheitern lassen
            }
        }

        // Mahnungen zur Rechnung
        $dunnings = [];
        try {
            $res = $this->client->getDunnings($invoiceId);
            $objs = $res['objects'] ?? [];
            if (is_array($objs)) {
                $seen = [];
                foreach ($objs as $d) {
                    if (!is_array($d)) {
                        continue;
                    }
                    $did = (int)($d['id'] ?? 0);
                    // Ursprungsrechnung und Duplikate herausfiltern
                    if ($did <= 0 || $did === $invoiceId || isset($seen[$did])) {
                        continue;
                    }
                    if (($d['invoiceType'] ?? 'MA') !== 'MA') {
                        continue;
                    }
                    $seen[$did] = true;
                    $dunnings[] = $d;
                }
            }
        } catch (\Throwable $e) {
            // Endpoint nicht verfügbar: ohne Mahnliste fortfahren
        }
        usort($dunnings, static function (array $a, array $b): int {
            return strcmp((string)($a['invoiceDate'] ?? ''), (string)($b['invoiceDate'] ?? ''));
        });

        // Gesamtforderung: bevorzugt autoritativ aus sevdesk, sonst aus Mahnungen abgeleitet
        $amountTotal = null;
        try {
            $res = $this->client->getInvoiceAndReminderAmount($invoiceId);
            $obj = $res['objects'] ?? $res;
            if (is_array($obj)) {
                $inv = $obj['invoiceAmount'] ?? null;
                $rem = $obj['reminderAmount'] ?? null;
                if (is_numeric($inv)) {
                    $amountTotal = (float)$inv + (is_numeric($rem) ? (float)$rem : 0.0);
                }
            }
        } catch (\Throwable $e) {
            // Fallback unten
        }
        if ($amountTotal === null) {
            $amountTotal = $amountOriginal;
            if (!empty($dunnings)) {
                $last = $dunnings[count($dunnings) - 1];
                $amountTotal = max($amountTotal, (float)($last['sumGross'] ?? 0));
            }
        }

        // PDFs: Original + alle Mahnungen
        $attachments = [];
        $pdfErrors = [];
        try {
            $pdf = $this->client->getInvoicePdf($invoiceId);
            $attachments[] = [
                'filename' => 'Rechnung_' . $this->safeFilename($invoiceNumber) . '.pdf',
                'content' => $pdf['content'],
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable $e) {
            $pdfErrors[] = 'Rechnung ' . $invoiceNumber . ': ' . $e->getMessage();
        }

        $level = 0;
        foreach ($dunnings as $d) {
            $level++;
            $dNumber = (string)($d['invoiceNumber'] ?? $d['id']);
            try {
                $pdf = $this->client->getInvoicePdf((int)$d['id']);
                $attachments[] = [
                    'filename' => 'Mahnung_' . $level . '_' . $this->safeFilename($dNumber) . '.pdf',
                    'content' => $pdf['content'],
                    'mime' => 'application/pdf',
                ];
            } catch (\Throwable $e) {
                $pdfErrors[] = 'Mahnung ' . $dNumber . ': ' . $e->getMessage();
            }
        }

        if (empty($attachments)) {
            throw new \RuntimeException('Es konnte kein PDF aus sevdesk geladen werden: ' . implode(' / ', $pdfErrors));
        }

        return [
            'invoice_id' => (int)$invoice['id'],
            'invoice_number' => $invoiceNumber,
            'invoice_date' => (string)($invoice['invoiceDate'] ?? ''),
            'due_date' => (string)($invoice['dueDate'] ?? ''),
            'amount_original' => $amountOriginal,
            'amount_total' => $amountTotal,
            'currency' => $currency,
            'dunning_level' => count($dunnings),
            'dunnings' => $dunnings,
            'debtor' => $debtor,
            'attachments' => $attachments,
            'pdf_errors' => $pdfErrors,
        ];
    }

    public function composeEmailText(array $h): string
    {
        $fmtMoney = static fn(float $v): string => number_format($v, 2, ',', '.');
        $fmtDate = static function (string $d): string {
            $d = trim($d);
            if ($d === '') {
                return '-';
            }
            $display = DateFormatter::toDisplay($d);
            return $display !== '' ? $display : $d;
        };

        $debtor = $h['debtor'] ?? [];
        $addressParts = array_filter([
            trim((string)($debtor['street'] ?? '')),
            trim(trim((string)($debtor['zip'] ?? '')) . ' ' . trim((string)($debtor['city'] ?? ''))),
        ]);

        $lines = [];
        $lines[] = 'Sehr geehrte Damen und Herren,';
        $lines[] = '';
        $lines[] = 'hiermit übergeben wir Ihnen die folgende offene Forderung zur weiteren Bearbeitung (Inkasso).';
        $lines[] = '';
        $lines[] = 'Schuldner:';
        $lines[] = '  Name: ' . (string)($debtor['name'] ?? 'Unbekannt');
        if (!empty($addressParts)) {
            $lines[] = '  Anschrift: ' . implode(', ', $addressParts);
        }
        $lines[] = '';
        $lines[] = 'Forderung:';
        $lines[] = '  Rechnungsnummer: ' . (string)($h['invoice_number'] ?? '');
        $lines[] = '  Rechnungsdatum: ' . $fmtDate((string)($h['invoice_date'] ?? ''));
        $lines[] = '  Fällig am: ' . $fmtDate((string)($h['due_date'] ?? ''));
        $lines[] = '  Rechnungsbetrag: ' . $fmtMoney((float)($h['amount_original'] ?? 0)) . ' ' . (string)($h['currency'] ?? 'EUR');
        $lines[] = '  Gesamtforderung inkl. Mahnungen: ' . $fmtMoney((float)($h['amount_total'] ?? 0)) . ' ' . (string)($h['currency'] ?? 'EUR');
        $lines[] = '  Mahnstufe: ' . (int)($h['dunning_level'] ?? 0);
        $lines[] = '';

        $dunnings = $h['dunnings'] ?? [];
        if (!empty($dunnings)) {
            $lines[] = 'Mahnverlauf:';
            $level = 0;
            foreach ($dunnings as $d) {
                $level++;
                $lines[] = '  ' . $level . '. Mahnung vom ' . $fmtDate((string)($d['invoiceDate'] ?? ''))
                    . ' (Nr. ' . (string)($d['invoiceNumber'] ?? '-') . ', Betrag '
                    . $fmtMoney((float)($d['sumGross'] ?? 0)) . ' ' . (string)($h['currency'] ?? 'EUR') . ')';
            }
            $lines[] = '';
        }

        $lines[] = 'Die Rechnung sowie alle Mahnungen finden Sie als PDF im Anhang.';
        if (!empty($h['pdf_errors'])) {
            $lines[] = 'Hinweis: Folgende Belege konnten nicht angehängt werden: ' . implode(' / ', $h['pdf_errors']);
        }
        $lines[] = '';
        $lines[] = 'Mit freundlichen Grüßen';

        return implode("\n", $lines);
    }

    private function unwrap(array $res): ?array
    {
        $obj = $res['objects'] ?? $res;
        if (is_array($obj) && isset($obj[0])) {
            $obj = $obj[0];
        }
        return is_array($obj) ? $obj : null;
    }

    private function extractAddress(array $contact): array
    {
        $addr = ['street' => '', 'zip' => '', 'city' => ''];

        $addresses = $contact['addresses'] ?? null;
        if (is_array($addresses)) {
            foreach ($addresses as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $street = trim((string)($a['street'] ?? ''));
                $zip = trim((string)($a['zip'] ?? ''));
                $city = trim((string)($a['city'] ?? ''));
                if ($street !== '' || $zip !== '' || $city !== '') {
                    return ['street' => $street, 'zip' => $zip, 'city' => $city];
                }
            }
        }

        return $addr;
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name) ?? '';
        return trim($name, '-') ?: 'beleg';
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
