<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ExportItemRepository;
use App\Repositories\ExportRunRepository;
use App\Repositories\InvoiceMarkerRepository;
use App\Repositories\MandateRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Services\SepaPain008Generator;
use App\Services\ValidationService;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class ExportsController
{
    public function index(): void
    {
        $runs = (new ExportRunRepository())->all();

        View::render('exports/index', [
            'runs' => $runs,
            'messages' => Flash::all(),
        ]);
    }

    public function create(): void
    {
        $selected = $_SESSION['selected_invoice_ids'] ?? [];
        $count = is_array($selected) ? count($selected) : 0;

        $settings = (new SettingsRepository())->get();

        View::render('exports/create', [
            'csrf' => Csrf::token(),
            'selected_count' => $count,
            'settings' => $settings,
            'messages' => Flash::all(),
        ]);
    }

    public function store(): void
    {
        Csrf::check();

        $selected = $_SESSION['selected_invoice_ids'] ?? [];
        if (!is_array($selected) || empty($selected)) {
            Flash::add('error', 'Keine Rechnungen ausgewählt.');
            header('Location: ' . App::url('/invoices'));
            exit;
        }

        $title = trim((string)($_POST['title'] ?? 'Lastschrift'));
        $collectionDate = trim((string)($_POST['collection_date'] ?? ''));
        $batch = !empty($_POST['batch_booking']) ? 1 : 0;
        $schemeDefault = ($_POST['scheme_default'] ?? '') === 'B2B' ? 'B2B' : 'CORE';
        $endtoendStrategy = ($_POST['endtoend_strategy'] ?? 'invoice_number') === 'generated' ? 'generated' : 'invoice_number';
        $remTemplate = trim((string)($_POST['remittance_template'] ?? 'Rechnung {invoice_number}'));

        if ($collectionDate === '') {
            Flash::add('error', 'Bitte Ausführungstermin setzen.');
            header('Location: ' . App::url('/exports/create'));
            exit;
        }

        $user = Auth::user();
        $runId = (new ExportRunRepository())->create([
            'title' => $title,
            'collection_date' => $collectionDate,
            'pain_version' => 'pain.008.001.08',
            'batch_booking' => $batch,
            'scheme_default' => $schemeDefault,
            'endtoend_strategy' => $endtoendStrategy,
            'remittance_template' => $remTemplate,
            'status' => 'draft',
            'created_by_user_id' => (int)$user['id'],
        ]);

        // Build items from sevdesk + mandates
        $settings = (new SettingsRepository())->get();
        $mandateRepo = new MandateRepository();
        $markerRepo = new InvoiceMarkerRepository();
        $val = new ValidationService();

        $client = new SevdeskClient(new SevdeskAccountRepository());

        $items = [];
        foreach ($selected as $invoiceId) {
            $invoiceId = (int)$invoiceId;
            $invRes = $client->getInvoice($invoiceId, 'contact');
            $invObj = $invRes['objects'] ?? $invRes; // some endpoints return object directly
            if (is_array($invObj) && isset($invObj[0])) {
                $invObj = $invObj[0];
            }
            if (!is_array($invObj)) {
                continue;
            }

            $invoiceNumber = (string)($invObj['invoiceNumber'] ?? $invObj['number'] ?? $invoiceId);
            $amount = (float)($invObj['sumGross'] ?? $invObj['sumGrossAccounting'] ?? $invObj['sumNet'] ?? $invObj['sum'] ?? 0.0);

            $contact = $invObj['contact'] ?? null;
            $contactId = null;
            $contactName = null;
            if (is_array($contact)) {
                $contactId = (int)($contact['id'] ?? 0);
                $contactName = (string)($contact['name'] ?? '');
            } elseif (is_array($invObj['contact'] ?? null)) {
                $contactId = (int)($invObj['contact']['id'] ?? 0);
            }

            if (!$contactId) {
                $items[] = [
                    'sevdesk_invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'sevdesk_contact_id' => 0,
                    'debtor_name' => $contactName ?: 'Unbekannt',
                    'debtor_iban' => '',
                    'mandate_reference' => '',
                    'mandate_date' => '1970-01-01',
                    'sequence_type' => 'RCUR',
                    'amount' => $amount,
                    'endtoend_id' => $invoiceNumber,
                    'remittance' => str_replace('{invoice_number}', $invoiceNumber, $remTemplate),
                    'status' => 'error',
                ];
                continue;
            }

            $mandate = $mandateRepo->findByContactId($contactId);

            $status = 'ok';
            $error = null;

            if (!$mandate || $mandate['status'] !== 'active') {
                $status = 'error';
                $error = 'Mandat fehlt oder ist nicht aktiv';
            } else if (!$val->validateIban((string)$mandate['debtor_iban'])) {
                $status = 'error';
                $error = 'Debtor IBAN ungültig';
            } else if ($markerRepo->exists($invoiceId)) {
                $status = 'error';
                $error = 'Rechnung wurde bereits exportiert';
            } else if ($amount <= 0.0) {
                $status = 'error';
                $error = 'Betrag ist 0';
            }

            $endToEnd = $endtoendStrategy === 'generated'
                ? ('INV' . $invoiceId . 'T' . date('His'))
                : $invoiceNumber;

            if (!$val->validateEndToEndId($endToEnd)) {
                $endToEnd = substr(preg_replace('/[^A-Za-z0-9]/', '', $endToEnd), 0, 35);
            }

            $items[] = [
                'sevdesk_invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'sevdesk_contact_id' => $contactId,
                'debtor_name' => (string)($mandate['debtor_name'] ?? $contactName ?? ''),
                'debtor_iban' => (string)($mandate['debtor_iban'] ?? ''),
                'mandate_reference' => (string)($mandate['mandate_reference'] ?? ''),
                'mandate_date' => (string)($mandate['mandate_date'] ?? '1970-01-01'),
                'sequence_type' => 'RCUR',
                'amount' => $amount,
                'endtoend_id' => $endToEnd,
                'remittance' => str_replace('{invoice_number}', $invoiceNumber, $remTemplate),
                'status' => $status,
            ];

            if ($error) {
                $items[count($items) - 1]['error_text'] = $error;
            }
        }

        (new ExportItemRepository())->createMany($runId, $items);

        Flash::add('success', 'Export Lauf erstellt.');
        header('Location: ' . App::url('/exports/' . $runId));
        exit;
    }

    public function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $run = (new ExportRunRepository())->find($id);
        if (!$run) {
            http_response_code(404);
            echo "Nicht gefunden.";
            return;
        }
        $itemRepo = new ExportItemRepository();
        $itemRepo->refreshMandateDataForRun($id);
        $items = $itemRepo->forRun($id);

        View::render('exports/show', [
            'csrf' => Csrf::token(),
            'run' => $run,
            'items' => $items,
            'messages' => Flash::all(),
        ]);
    }

    public function validate(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);

        $itemRepo = new ExportItemRepository();
        $itemRepo->refreshMandateDataForRun($id);
        $items = $itemRepo->forRun($id);
        $sum = 0.0;
        $ok = 0;
        foreach ($items as $it) {
            if ($it['status'] === 'ok') {
                $ok++;
                $sum += (float)$it['amount'];
            }
        }

        (new ExportRunRepository())->updateTotals($id, $ok, $sum, 'validated', 'now');

        Flash::add('success', 'Validierung abgeschlossen, ok Positionen: ' . $ok);
        header('Location: ' . App::url('/exports/' . $id));
        exit;
    }

    public function generate(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);

        $runRepo = new ExportRunRepository();
        $itemRepo = new ExportItemRepository();
        $settings = (new SettingsRepository())->get();

        $run = $runRepo->find($id);
        if (!$run) {
            http_response_code(404);
            echo "Nicht gefunden.";
            return;
        }

        $itemRepo->refreshMandateDataForRun($id);

        $itemsAll = $itemRepo->forRun($id);
        $items = [];
        foreach ($itemsAll as $it) {
            if ($it['status'] === 'ok') {
                $items[] = $it;
            }
        }

        if (empty($items)) {
            Flash::add('error', 'Keine ok Positionen vorhanden.');
            header('Location: ' . App::url('/exports/' . $id));
            exit;
        }

        try {
            $gen = new SepaPain008Generator();
            $xml = $gen->generate($settings, $run, $items);

            // Optional server-side validation against DK-TVS schema (helps to catch bank rejections)
            if (class_exists(\DOMDocument::class)) {
                $xsd = \App\Support\App::basePath('resources/xsd/pain.008.001.08_GBIC_5.xsd');
                if (is_file($xsd)) {
                    \libxml_use_internal_errors(true);
                    $d = new \DOMDocument();
                    if ($d->loadXML($xml) && !$d->schemaValidate($xsd)) {
                        $errs = \libxml_get_errors();
                        \libxml_clear_errors();

                        $msg = 'SEPA XML Schema Fehler: ';
                        if (!empty($errs)) {
                            $first = $errs[0];
                            $msg .= trim((string)$first->message) . ' (Zeile ' . (int)$first->line . ')';
                        } else {
                            $msg .= 'Unbekannter Validierungsfehler';
                        }
                        throw new \RuntimeException($msg);
                    }
                }
            }

            $fileName = 'SEPA_Lastschrift_' . date('Ymd_His') . '_run_' . $id . '.xml';
            $rel = 'storage/exports/' . $fileName;
$abs = App::basePath($rel);

// Ensure storage/exports exists (ZIP kann leere Ordner nicht zuverlässig mit ausliefern)
$dir = dirname($abs);
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
if (!is_dir($dir) || !is_writable($dir)) {
    throw new \RuntimeException('Export Ordner nicht beschreibbar: ' . $dir);
}

if (@file_put_contents($abs, $xml) === false) {
    $err = error_get_last();
    $msg = $err && isset($err['message']) ? $err['message'] : 'unbekannt';
    throw new \RuntimeException('Konnte XML nicht speichern: ' . $abs . ' (' . $msg . ')');
}


            $hash = hash('sha256', $xml);
            $runRepo->markExported($id, $rel, $hash);

            Flash::add('success', 'XML erstellt.');
        } catch (\Throwable $e) {
            Flash::add('error', 'XML Fehler: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/exports/' . $id));
        exit;
    }

    public function download(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $run = (new ExportRunRepository())->find($id);
        if (!$run || empty($run['file_path'])) {
            http_response_code(404);
            echo "Datei nicht gefunden.";
            return;
        }

        $abs = App::basePath((string)$run['file_path']);
        if (!is_file($abs)) {
            http_response_code(404);
            echo "Datei nicht gefunden.";
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        readfile($abs);
        exit;
    }

    public function finalize(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);

        $itemRepo = new ExportItemRepository();
        $itemRepo->refreshMandateDataForRun($id);
        $items = $itemRepo->forRun($id);
        $markerRepo = new InvoiceMarkerRepository();
        $mandateRepo = new MandateRepository();

        foreach ($items as $it) {
            if ($it['status'] === 'ok') {
                $markerRepo->mark((int)$it['sevdesk_invoice_id'], $id);

                // mark mandate usage for sequence tracking
                $m = $mandateRepo->findByContactId((int)$it['sevdesk_contact_id']);
                if ($m) {
                    $mandateRepo->markUsed((int)$m['id'], (string)$it['sequence_type']);
                }
            }
        }

        (new ExportItemRepository())->markCompletedForRun($id);

        (new ExportRunRepository())->finalize($id);

        
// Session Cache aktualisieren, damit die Übersicht direkt "abgeschlossen" zeigt
if (!empty($_SESSION['invoices_cache']) && is_array($_SESSION['invoices_cache'])) {
    $cache = $_SESSION['invoices_cache'];
    $done = [];
    foreach ($items as $it) {
        if (($it['status'] ?? '') === 'ok' || ($it['status'] ?? '') === 'completed') {
            $done[(int)$it['sevdesk_invoice_id']] = true;
        }
    }
    foreach ($cache as &$row) {
        $iid = (int)($row['id'] ?? 0);
        if ($iid && isset($done[$iid])) {
            $row['completed'] = true;
        }
    }
    unset($row);
    $_SESSION['invoices_cache'] = $cache;
}

Flash::add('success', 'Lauf abgeschlossen.');
        header('Location: ' . App::url('/exports/' . $id));
        exit;
    }
}
