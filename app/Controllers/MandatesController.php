<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\MandateRepository;
use App\Repositories\OnlineMandateRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Services\ValidationService;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class MandatesController
{
    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));

        $items = (new MandateRepository())->all($q);

        // Quelle ermitteln, damit Online und manuelle Mandate in einer Tabelle angezeigt werden können
        foreach ($items as &$it) {
            $notes = (string)($it['notes'] ?? '');
            $att = (string)($it['attachment_path'] ?? '');
            $isOnline = (stripos($notes, 'online mandat') !== false) || (strpos($att, '/online_') !== false) || (strpos($att, 'online_') !== false);
            $it['source'] = $isOnline ? 'online' : 'manual';
            $it['source_label'] = $isOnline ? 'Online' : 'Manuell';
        }
        unset($it);

        // Offene Online Links separat, aber kompakt (kein zweites großes Mandate Listing)
        $openLinks = [];
        $allOnline = (new OnlineMandateRepository())->all();
        foreach ($allOnline as $ol) {
            if ((string)($ol['status'] ?? '') !== 'open') {
                continue;
            }
            if ($q !== '') {
                $hay = (string)($ol['contact_name'] ?? '') . ' ' . (string)($ol['mandate_reference'] ?? '');
                if (stripos($hay, $q) === false) {
                    continue;
                }
            }
            $openLinks[] = $ol;
        }

        View::render('mandates/index', [
            'items' => $items,
            'openLinks' => $openLinks,
            'openLinksCount' => count($openLinks),
            'q' => $q,
            'csrf' => Csrf::token(),
            'messages' => Flash::all(),
        ]);
    }

    public function create(): void
    {
        View::render('mandates/form', [
            'csrf' => Csrf::token(),
            'item' => null,
            'messages' => Flash::all(),
        ]);
    }

    public function store(): void
    {
        Csrf::check();
        $repo = new MandateRepository();
        $val = new ValidationService();

        $data = $this->readForm();
        if (!$val->validateIban($data['debtor_iban'])) {
            Flash::add('error', 'IBAN ist ungültig.');
            header('Location: ' . App::url('/mandates/create'));
            exit;
        }

        try {
            $repo->create($data);
            Flash::add('success', 'Mandat erstellt.');
            header('Location: ' . App::url('/mandates'));
            exit;
        } catch (\Throwable $e) {
            Flash::add('error', 'Speichern fehlgeschlagen: ' . $e->getMessage());
            header('Location: ' . App::url('/mandates/create'));
            exit;
        }
    }

    public function edit(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $item = (new MandateRepository())->find($id);
        if (!$item) {
            http_response_code(404);
            echo "Nicht gefunden.";
            return;
        }

        View::render('mandates/form', [
            'csrf' => Csrf::token(),
            'item' => $item,
            'messages' => Flash::all(),
        ]);
    }

    public function update(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);

        $repo = new MandateRepository();
        $item = $repo->find($id);
        if (!$item) {
            http_response_code(404);
            echo "Nicht gefunden.";
            return;
        }

        $val = new ValidationService();
        $data = $this->readForm();
        if (!$val->validateIban($data['debtor_iban'])) {
            Flash::add('error', 'IBAN ist ungültig.');
            header('Location: ' . App::url('/mandates/' . $id . '/edit'));
            exit;
        }

        try {
            $repo->update($id, $data);
            Flash::add('success', 'Mandat gespeichert.');
        } catch (\Throwable $e) {
            Flash::add('error', 'Speichern fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/mandates/' . $id . '/edit'));
        exit;
    }

    
    public function pdf(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new MandateRepository();
        $it = $repo->find($id);

        if (!$it || empty($it['attachment_path'])) {
            http_response_code(404);
            echo 'PDF nicht gefunden.';
            return;
        }

        $file = App::basePath((string)$it['attachment_path']);
        if (!is_file($file)) {
            http_response_code(404);
            echo 'PDF nicht gefunden.';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="SEPA_Mandat_' . (string)$it['mandate_reference'] . '.pdf"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
    }

public function revoke(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);
        (new MandateRepository())->setStatus($id, 'revoked');
        Flash::add('success', 'Mandat widerrufen.');
        header('Location: ' . App::url('/mandates'));
        exit;
    }

public function delete(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);

        $repo = new MandateRepository();
        $item = $repo->find($id);

        if (!$item) {
            Flash::add('error', 'Mandat nicht gefunden.');
            header('Location: ' . App::url('/mandates'));
            exit;
        }

        $att = (string)($item['attachment_path'] ?? '');
        if ($att !== '') {
            $file = App::basePath($att);
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $repo->delete($id);

        Flash::add('success', 'Mandat gelöscht.');
        header('Location: ' . App::url('/mandates'));
        exit;
    }


    
    public function importSevdesk(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        $list = is_array($contacts) ? $contacts : [];

        // Only contacts with IBAN
        $list = array_values(array_filter($list, function ($c): bool {
            if (!is_array($c)) {
                return false;
            }
            $iban = trim((string)($c['bankAccount'] ?? ''));
            return $iban !== '';
        }));

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $list = array_values(array_filter($list, function (array $c) use ($qLower): bool {
                $hay = [
                    (string)($c['id'] ?? ''),
                    (string)($c['name'] ?? ''),
                    (string)($c['customerNumber'] ?? ''),
                    (string)($c['bankAccount'] ?? ''),
                ];
                foreach ($hay as $h) {
                    if ($h !== '' && mb_stripos(mb_strtolower($h), $qLower) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        View::render('mandates/import_sevdesk', [
            'csrf' => Csrf::token(),
            'q' => $q,
            'contacts' => $list,
            'mandate_date' => date('Y-m-d'),
            'scheme' => 'CORE',
            'status' => 'active',
            'messages' => Flash::all(),
        ]);
    }

    public function loadSevdeskContacts(): void
    {
        Csrf::check();

        try {
            $client = new SevdeskClient(new SevdeskAccountRepository());
            $all = $client->getAllContacts(null, 200, 5000);

            // Normalize data for view
            $normalized = [];
            foreach ($all as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $normalized[] = [
                    'id' => (int)($c['id'] ?? 0),
                    'name' => (string)($c['name'] ?? ''),
                    'email' => $this->extractEmail($c),
                    'customerNumber' => $c['customerNumber'] ?? null,
                    'bankAccount' => $c['bankAccount'] ?? null,
                    'bankBic' => $c['bankBic'] ?? ($c['bic'] ?? null),
                ];
            }

            if (is_array($normalized)) {
                usort($normalized, function (array $a, array $b): int {
                    $an = mb_strtolower((string)($a['name'] ?? ''));
                    $bn = mb_strtolower((string)($b['name'] ?? ''));
                    return $an <=> $bn;
                });
            }

            $_SESSION['sevdesk_contacts_cache'] = $normalized;

            $withIban = array_values(array_filter($normalized, function (array $c): bool {
                return trim((string)($c['bankAccount'] ?? '')) !== '';
            }));

            Flash::add('success', 'Kontakte geladen: ' . count($normalized) . ', davon mit IBAN: ' . count($withIban));
        } catch (\Throwable $e) {
            Flash::add('error', 'sevdesk Kontakte laden fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/mandates/import-sevdesk'));
        exit;
    }

    public function runSevdeskImport(): void
    {
        Csrf::check();

        $ids = $_POST['contact_ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            Flash::add('error', 'Bitte mindestens einen Kontakt auswählen.');
            header('Location: ' . App::url('/mandates/import-sevdesk'));
            exit;
        }

        $mandateDate = trim((string)($_POST['mandate_date'] ?? date('Y-m-d')));
        $scheme = (($_POST['scheme'] ?? 'CORE') === 'B2B') ? 'B2B' : 'CORE';
        $status = (($_POST['status'] ?? 'active') === 'paused') ? 'paused' : 'active';

        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        $byId = [];
        if (is_array($contacts)) {
            foreach ($contacts as $c) {
                if (is_array($c) && isset($c['id'])) {
                    $byId[(int)$c['id']] = $c;
                }
            }
        }

        $val = new ValidationService();
        $repo = new MandateRepository();

        $imported = 0;
        $skipped = 0;

        foreach ($ids as $idRaw) {
            $id = (int)$idRaw;
            if ($id <= 0) {
                $skipped++;
                continue;
            }

            $c = $byId[$id] ?? null;
            if (!$c) {
                $skipped++;
                continue;
            }

            $iban = trim((string)($c['bankAccount'] ?? ''));
            if ($iban === '' || !$val->validateIban($iban)) {
                $skipped++;
                continue;
            }

            $customerNumber = trim((string)($c['customerNumber'] ?? ''));
            $reference = $customerNumber !== '' ? ('SEV-' . $customerNumber) : ('SEV-' . $id);

            $data = [
                'sevdesk_contact_id' => $id,
                'debtor_name' => trim((string)($c['name'] ?? '')),
                'debtor_iban' => $iban,
                'debtor_bic' => trim((string)($c['bankBic'] ?? '')) ?: null,
                'mandate_reference' => $reference,
                'mandate_date' => $mandateDate,
                'scheme' => $scheme,
                'sequence_mode' => 'auto',
                'status' => $status,
                'notes' => 'Import aus sevdesk am ' . date('Y-m-d H:i'),
                'attachment_path' => null,
            ];

            $repo->upsertByContactId($id, $data);
            $imported++;
        }

        Flash::add('success', 'Import fertig. Importiert oder aktualisiert: ' . $imported . ', übersprungen: ' . $skipped);
        header('Location: ' . App::url('/mandates'));
        exit;
    }

public function exportCsv(): void
    {
        $repo = new MandateRepository();
        $items = $repo->all('');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mandate_export.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['sevdesk_contact_id','debtor_name','debtor_iban','debtor_bic','mandate_reference','mandate_date','scheme','sequence_mode','status','notes']);
        foreach ($items as $it) {
            fputcsv($out, [
                $it['sevdesk_contact_id'],
                $it['debtor_name'],
                $it['debtor_iban'],
                $it['debtor_bic'],
                $it['mandate_reference'],
                $it['mandate_date'],
                $it['scheme'],
                $it['sequence_mode'],
                $it['status'],
                $it['notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function importCsv(): void
    {
        Csrf::check();

        if (empty($_FILES['csv']['tmp_name'])) {
            Flash::add('error', 'Bitte CSV Datei wählen.');
            header('Location: ' . App::url('/mandates'));
            exit;
        }

        $repo = new MandateRepository();
        $val = new ValidationService();

        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) {
            Flash::add('error', 'Konnte CSV nicht lesen.');
            header('Location: ' . App::url('/mandates'));
            exit;
        }

        $header = fgetcsv($fh);
        if (!$header) {
            Flash::add('error', 'CSV ist leer.');
            header('Location: ' . App::url('/mandates'));
            exit;
        }

        $map = array_flip($header);
        $count = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $cid = (int)($row[$map['sevdesk_contact_id']] ?? 0);
            $iban = trim((string)($row[$map['debtor_iban']] ?? ''));
            if ($cid <= 0 || !$val->validateIban($iban)) {
                continue;
            }
            $existing = $repo->findByContactId($cid);

            $data = [
                'sevdesk_contact_id' => $cid,
                'debtor_name' => trim((string)($row[$map['debtor_name']] ?? '')),
                'debtor_iban' => $iban,
                'debtor_bic' => trim((string)($row[$map['debtor_bic']] ?? '')) ?: null,
                'mandate_reference' => trim((string)($row[$map['mandate_reference']] ?? '')),
                'mandate_date' => trim((string)($row[$map['mandate_date']] ?? '')),
                'scheme' => (($row[$map['scheme']] ?? 'CORE') === 'B2B') ? 'B2B' : 'CORE',
                'sequence_mode' => (($row[$map['sequence_mode']] ?? 'auto') === 'manual') ? 'manual' : 'auto',
                'status' => in_array(($row[$map['status']] ?? 'active'), ['active','paused','revoked'], true) ? (string)$row[$map['status']] : 'active',
                'notes' => (string)($row[$map['notes']] ?? ''),
                'attachment_path' => null,
            ];

            if ($existing) {
                $repo->update((int)$existing['id'], $data);
            } else {
                $repo->create($data);
            }
            $count++;
        }
        fclose($fh);

        Flash::add('success', 'Importiert: ' . $count);
        header('Location: ' . App::url('/mandates'));
        exit;
    }

    private function readForm(): array
    {
        $attachment = null;
        if (!empty($_FILES['mandate_pdf']['tmp_name'])) {
            $name = basename((string)$_FILES['mandate_pdf']['name']);
            $safe = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $name);
            $targetRel = 'storage/uploads/mandates/' . date('Ymd_His') . '_' . $safe;
            $target = App::basePath($targetRel);

            if (!move_uploaded_file($_FILES['mandate_pdf']['tmp_name'], $target)) {
                $attachment = null;
            } else {
                $attachment = $targetRel;
            }
        }

        return [
            'sevdesk_contact_id' => (int)($_POST['sevdesk_contact_id'] ?? 0),
            'debtor_name' => trim((string)($_POST['debtor_name'] ?? '')),
            'debtor_iban' => trim((string)($_POST['debtor_iban'] ?? '')),
            'debtor_bic' => trim((string)($_POST['debtor_bic'] ?? '')) ?: null,
            'mandate_reference' => trim((string)($_POST['mandate_reference'] ?? '')),
            'mandate_date' => trim((string)($_POST['mandate_date'] ?? '')),
            'scheme' => (($_POST['scheme'] ?? 'CORE') === 'B2B') ? 'B2B' : 'CORE',
            'sequence_mode' => (($_POST['sequence_mode'] ?? 'auto') === 'manual') ? 'manual' : 'auto',
            'status' => in_array(($_POST['status'] ?? 'active'), ['active','paused','revoked'], true) ? (string)$_POST['status'] : 'active',
            'notes' => trim((string)($_POST['notes'] ?? '')),
            'attachment_path' => $attachment,
        ];
    }

    private function extractEmail(array $c): ?string
    {
        foreach (['email', 'emailAddress', 'mail', 'eMail'] as $k) {
            if (isset($c[$k]) && is_string($c[$k])) {
                $v = trim($c[$k]);
                if ($v !== '' && strpos($v, '@') !== false) {
                    return $v;
                }
            }
        }

        foreach (['emails', 'emailAddresses', 'communicationWays', 'communicationWay'] as $k) {
            if (!isset($c[$k]) || !is_array($c[$k])) {
                continue;
            }
            foreach ($c[$k] as $row) {
                if (is_string($row)) {
                    $v = trim($row);
                    if ($v !== '' && strpos($v, '@') !== false) {
                        return $v;
                    }
                    continue;
                }
                if (is_array($row)) {
                    foreach (['value', 'email', 'emailAddress'] as $vk) {
                        if (isset($row[$vk]) && is_string($row[$vk])) {
                            $v = trim($row[$vk]);
                            if ($v !== '' && strpos($v, '@') !== false) {
                                return $v;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

}
