<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InvoiceMarkerRepository;
use App\Repositories\MandateRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class InvoicesController
{
    public function index(): void
    {
        $list = $_SESSION['invoices_cache'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        // Status aus DB: bereits abgeschlossene Lastschriften markieren
        if (!empty($list)) {
            $ids = array_values(array_filter(array_map(static fn($r): int => (int)($r['id'] ?? 0), $list)));
            $markerMap = (new InvoiceMarkerRepository())->mapByInvoiceIds($ids);
            foreach ($list as &$r) {
                $iid = (int)($r['id'] ?? 0);
                $r['completed'] = $iid && isset($markerMap[$iid]);
                $r['completed_run_id'] = $r['completed'] ? (int)($markerMap[$iid]['first_export_run_id'] ?? 0) : null;
            }
            unset($r);
        }

        $q = trim((string)($_GET['q'] ?? ''));
$pm = trim((string)($_GET['pm'] ?? ''));

if ($q !== '' || $pm !== '') {
    $lower = function(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };
    $needle = $lower($q);

    $list = array_values(array_filter($list, function (array $r) use ($needle, $q, $pm, $lower): bool {
        if ($q !== '') {
            $hay = implode(' | ', [
                (string)($r['id'] ?? ''),
                (string)($r['invoiceNumber'] ?? ''),
                (string)($r['contact_name'] ?? ''),
                (string)($r['dueDate'] ?? ''),
                (string)($r['sumGross'] ?? ''),
                (string)($r['payment_method'] ?? ''),
            ]);
            $hay = $lower($hay);
            if (!str_contains($hay, $needle)) {
                return false;
            }
        }if ($pm !== '') {
    if (ctype_digit($pm)) {
        return (int)($r['payment_method_id'] ?? 0) === (int)$pm;
    }
    // unbekannter Filterwert, nichts filtern
}
return true;
    }));
}

// Zahlungsarten für Filter Dropdown sammeln
$paymentMethods = [];
foreach ($list as $r) {
    $pid = (int)($r['payment_method_id'] ?? 0);
    $pname = trim((string)($r['payment_method'] ?? ''));
    if ($pid > 0 && $pname !== '' && $pname !== 'Unbekannt') {
        $paymentMethods[$pid] = $pname;
    }
}
asort($paymentMethods, SORT_NATURAL | SORT_FLAG_CASE);


        $selected = $_SESSION['selected_invoice_ids'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_unique(array_map('intval', $selected)));

        View::render('invoices/index', [
            'csrf' => Csrf::token(),
            'invoices' => $list,
            'selected' => $selected,
            'q' => $q,
            'pm' => $pm,
            'paymentMethods' => $paymentMethods,
            'messages' => Flash::all(),
        ]);
    }

    public function load(): void
    {
        Csrf::check();

        $client = new SevdeskClient(new SevdeskAccountRepository());

        $pmMap = $this->getPaymentMethodsMap($client);

        // Kontakte Cache Versionierung, damit alte falsche Einträge automatisch ersetzt werden
        $contactsCacheVersion = 3;
        if (!isset($_SESSION['contacts_cache_version']) || (int)$_SESSION['contacts_cache_version'] !== $contactsCacheVersion) {
            $_SESSION['contacts_cache'] = [];
            $_SESSION['contacts_cache_version'] = $contactsCacheVersion;
        }
        $contactCache = $_SESSION['contacts_cache'] ?? [];
        if (!is_array($contactCache)) {
            $contactCache = [];
        }

        // Invoices paginiert laden
        $all = [];
        $limit = 200;
        $offset = 0;
        for ($page = 0; $page < 20; $page++) { // max 4000
            $res = $client->getInvoices($limit, $offset, 'contact,paymentMethod');
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

        // Normalisieren und filtern: offen, unbezahlte, Status 200, ohne payDate
        $filtered = [];
        foreach ($all as $inv) {
            $row = $this->normalizeInvoice($inv);

            if ((int)($row['status'] ?? 0) !== 200) {
                continue;
            }
            if (!empty($row['payDate'])) {
                continue;
            }

            $filtered[] = $row;
        }

        // Fehlende Felder nachladen: dueDate und Kontakt
        foreach ($filtered as &$row) {
            $invoiceId = (int)($row['id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }

            $needDetail = empty($row['dueDate']) || empty($row['contact_id']);
            if ($needDetail) {
                try {
                    $detailRes = $client->getInvoice($invoiceId, 'contact,paymentMethod');
                    $obj = $detailRes['objects'] ?? $detailRes;
                    if (is_array($obj) && isset($obj[0])) {
                        $obj = $obj[0];
                    }
                    if (is_array($obj)) {
                        if (empty($row['dueDate'])) {
                            $due = (string)($obj['dueDate'] ?? '');
                            if ($due === '') {
                                $due = (string)($obj['invoiceDate'] ?? ($obj['deliveryDate'] ?? ''));
                            }
                            $row['dueDate'] = $due;
                        }

                        if (empty($row['contact_id'])) {
                            $c = $obj['contact'] ?? null;
                            if (is_array($c) && !empty($c['id'])) {
                                $row['contact_id'] = (int)$c['id'];
                            }
                        }

                        // Zahlungsart aus Detail übernehmen (falls vorhanden)
if (empty($row['payment_method_id']) || empty($row['payment_method'])) {
    $pmId = null;
    $pmName = '';
    $pmRef = $obj['paymentMethodId'] ?? ($obj['paymentMethod'] ?? null);
    if (is_array($pmRef)) {
        $pmId = $pmRef['id'] ?? ($pmRef['paymentMethod']['id'] ?? null);
        $pmName = (string)($pmRef['name'] ?? ($pmRef['paymentMethodName'] ?? ''));
    } else {
        if (!empty($obj['paymentMethodId'])) {
            $pmId = $obj['paymentMethodId'];
        }
    }
    if ($pmName === '' && is_array($obj['paymentMethod'] ?? null)) {
        $pmName = (string)(($obj['paymentMethod']['name'] ?? '') ?: ($obj['paymentMethod']['paymentMethodName'] ?? ''));
        if (!$pmId) {
            $pmId = $obj['paymentMethod']['id'] ?? null;
        }
    }
    if (!empty($pmId) && empty($row['payment_method_id'])) {
        $row['payment_method_id'] = (int)$pmId;
    }
    if ($pmName !== '' && empty($row['payment_method'])) {
        $row['payment_method'] = trim((string)$pmName);
    }
}

// Kontakt Name aus Detail übernehmen, falls leer
                        // Zahlungsart ergänzen
$pmId = (int)($row['payment_method_id'] ?? 0);
if (empty($row['payment_method'])) {
    if ($pmId > 0 && isset($pmMap[$pmId])) {
        $row['payment_method'] = (string)$pmMap[$pmId];
    } elseif ($pmId > 0) {
        $row['payment_method'] = 'ID ' . $pmId;
    } else {
        $row['payment_method'] = 'Unbekannt';
    }
}

if (empty($row['contact_name'])) {
                            $c = $obj['contact'] ?? null;
                            if (is_array($c)) {
                                $row['contact_name'] = $this->extractContactName($c);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // einzelne Fehler ignorieren
                }
            }

            // Vollständigen Kontakt laden, wenn Name unvollständig wirkt
            $cid = (int)($row['contact_id'] ?? 0);
            if ($cid > 0) {
                $currentName = trim((string)($row['contact_name'] ?? ''));
                $shouldFetchContact = $this->looksIncompleteName($currentName);

                if (!$shouldFetchContact && $currentName === '') {
                    $shouldFetchContact = true;
                }

                if ($shouldFetchContact) {
                    if (isset($contactCache[$cid]) && is_string($contactCache[$cid]) && trim($contactCache[$cid]) !== '') {
                        $row['contact_name'] = (string)$contactCache[$cid];
                    } else {
                        try {
                            $cRes = $client->getContact($cid, null);
                            $cObj = $cRes['objects'] ?? $cRes;
                            if (is_array($cObj) && isset($cObj[0])) {
                                $cObj = $cObj[0];
                            }
                            if (is_array($cObj)) {
                                $name = $this->extractContactName($cObj);
                                $contactCache[$cid] = $name;
                                $row['contact_name'] = $name;
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }
                } else {
                    // auch vollständige Namen in Cache übernehmen
                    if ($currentName !== '') {
                        $contactCache[$cid] = $currentName;
                    }
                }
            }

            if (empty($row['contact_name'])) {
                $row['contact_name'] = 'Unbekannt';
            }
        }
        unset($row);

        $_SESSION['contacts_cache'] = $contactCache;

        // Mandat Status anhängen
        $mandateRepo = new MandateRepository();
        foreach ($filtered as &$row) {
            $cid = (int)($row['contact_id'] ?? 0);
            $row['mandate_ok'] = false;
            $row['mandate_reference'] = null;

            if ($cid > 0) {
                $m = $mandateRepo->findByContactId($cid);
                if ($m && is_array($m)) {
                    $ok = (($m['status'] ?? '') === 'active')
                        && !empty($m['debtor_iban'])
                        && !empty($m['mandate_reference'])
                        && !empty($m['mandate_date']);
                    $row['mandate_ok'] = $ok;
                    $row['mandate_reference'] = $m['mandate_reference'] ?? null;
                }
            }
        }
        unset($row);

        // Abgeschlossen Status aus DB anhängen
        $ids = array_values(array_filter(array_map(static fn($r): int => (int)($r['id'] ?? 0), $filtered)));
        $markerMap = (new InvoiceMarkerRepository())->mapByInvoiceIds($ids);
        foreach ($filtered as &$row) {
            $iid = (int)($row['id'] ?? 0);
            $row['completed'] = $iid && isset($markerMap[$iid]);
            $row['completed_run_id'] = $row['completed'] ? (int)($markerMap[$iid]['first_export_run_id'] ?? 0) : null;
        }
        unset($row);

        // Sort: dueDate aufsteigend, sonst ID absteigend
        usort($filtered, static function (array $a, array $b): int {
            $da = (string)($a['dueDate'] ?? '');
            $db = (string)($b['dueDate'] ?? '');
            if ($da !== '' && $db !== '' && $da !== $db) {
                return strcmp($da, $db);
            }
            return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
        });

        $_SESSION['invoices_cache'] = $filtered;

        Flash::add('success', 'sevdesk Rechnungen geladen: ' . count($filtered));
        header('Location: ' . App::url('/invoices'));
        exit;
    }

    public function select(): void
    {
        Csrf::check();

        $ids = $_POST['invoice_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn(int $v): bool => $v > 0));

        // Bereits abgeschlossene Rechnungen nicht erneut auswählen
        $markerMap = (new InvoiceMarkerRepository())->mapByInvoiceIds($ids);
        $ids = array_values(array_filter($ids, static fn(int $iid): bool => !isset($markerMap[$iid])));

        $_SESSION['selected_invoice_ids'] = $ids;

        Flash::add('success', 'Auswahl gespeichert: ' . count($ids));
        header('Location: ' . App::url('/exports/create'));
        exit;
    }

    private function normalizeInvoice(array $inv): array
{
    $contactId = null;
    $contactName = '';

    $contact = $inv['contact'] ?? null;
    if (is_array($contact)) {
        $contactId = $contact['id'] ?? ($contact['contact']['id'] ?? null);
        $contactName = $this->extractContactName($contact);
    }

    if (!$contactId && is_array($inv['contactId'] ?? null)) {
        $contactId = $inv['contactId']['id'] ?? null;
    }

    // Manche Listen liefern nur customerName / contactName als String
    if ($contactName === '') {
        $contactName = trim((string)($inv['customerName'] ?? ($inv['contactName'] ?? '')));
    }

    $pmId = null;
    $pmName = '';

    $pmRef = $inv['paymentMethodId'] ?? ($inv['paymentMethod'] ?? null);
    if (is_array($pmRef)) {
        $pmId = $pmRef['id'] ?? ($pmRef['paymentMethod']['id'] ?? null);
        $pmName = trim((string)($pmRef['name'] ?? ($pmRef['paymentMethodName'] ?? '')));
    } else {
        if (!empty($inv['paymentMethodId'])) {
            $pmId = $inv['paymentMethodId'];
        }
    }

    // Wenn paymentMethod eingebettet ist, steht der Name ggf. dort
    if ($pmName === '' && is_array($inv['paymentMethod'] ?? null)) {
        $pmName = trim((string)(($inv['paymentMethod']['name'] ?? '') ?: ($inv['paymentMethod']['paymentMethodName'] ?? '')));
        if (!$pmId) {
            $pmId = $inv['paymentMethod']['id'] ?? null;
        }
    }

    $amount = $inv['sumGross'] ?? $inv['sumGrossAccounting'] ?? $inv['sumNet'] ?? $inv['sumNetAccounting'] ?? ($inv['sum'] ?? null);
    if ($amount === null) {
        $amount = $inv['amount'] ?? 0.0;
    }

    return [
        'id' => (int)($inv['id'] ?? 0),
        'invoiceNumber' => (string)($inv['invoiceNumber'] ?? ($inv['number'] ?? '')),
        'status' => (int)($inv['status'] ?? 0),
        'payDate' => $inv['payDate'] ?? null,
        'dueDate' => (string)($inv['dueDate'] ?? ($inv['invoiceDate'] ?? ($inv['deliveryDate'] ?? ''))),
        'sumGross' => (float)$amount,
        'currency' => (string)($inv['currency'] ?? 'EUR'),
        'contact_id' => $contactId ? (int)$contactId : null,
        'contact_name' => $contactName,
        'payment_method_id' => $pmId ? (int)$pmId : null,
        'payment_method' => $pmName,
    ];
}

private function extractContactName(array $contact): string
    {
        // sevdesk nutzt bei Personen häufig: surename (Vorname) + familyname (Nachname)
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

        // Manche Systeme liefern "Nachname, Vorname"
        $name = trim((string)($contact['name'] ?? ''));
        if (str_contains($name, ',')) {
            $parts = array_map('trim', explode(',', $name, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                return trim($parts[1] . ' ' . $parts[0]);
            }
        }

        // Firmen: name + name2
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

    

private function getPaymentMethodsMap(SevdeskClient $client): array
{
    $cacheVersion = 1;
    if (!isset($_SESSION['payment_methods_cache_version']) || (int)$_SESSION['payment_methods_cache_version'] !== $cacheVersion) {
        $_SESSION['payment_methods_cache'] = [];
        $_SESSION['payment_methods_cache_version'] = $cacheVersion;
    }

    $map = $_SESSION['payment_methods_cache'] ?? [];
    if (is_array($map) && !empty($map)) {
        return $map;
    }

    $map = [];
    try {
        $res = $client->getPaymentMethods(200, 0, null);
        $objs = $res['objects'] ?? [];
        if (is_array($objs)) {
            foreach ($objs as $pm) {
                if (!is_array($pm)) {
                    continue;
                }
                $id = (int)($pm['id'] ?? 0);
                $name = trim((string)($pm['name'] ?? ($pm['paymentMethodName'] ?? '')));
                if ($id > 0 && $name !== '') {
                    $map[$id] = $name;
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }

    $_SESSION['payment_methods_cache'] = $map;
    return $map;
}
private function looksIncompleteName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || $name === 'Unbekannt') {
            return true;
        }

        // wenn kein Leerzeichen vorhanden ist, ist es oft nur Nachname oder Firmenkürzel
        if (!str_contains($name, ' ') && !str_contains($name, ',')) {
            return true;
        }

        // sehr kurze Tokens sind meist unvollständig
        if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) < 3) {
            return true;
        }

        return false;
    }
}
