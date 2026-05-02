<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ContractRepository;
use App\Repositories\ContractTemplateRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Services\SimplePdf;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\View;

final class ContractsController
{
    public function index(): void
    {
        $repo = new ContractRepository();
        $items = $repo->all();

        View::render('contracts/index', [
            'items' => $items,
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        $templates = (new ContractTemplateRepository())->allActive();
        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];

        // Auto-load contacts from Sevdesk if cache is empty
        if (!is_array($contacts) || empty($contacts)) {
            try {
                $client = new SevdeskClient(new SevdeskAccountRepository());
                $all = $client->getAllContacts(null, 200, 5000);
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
                usort($normalized, function (array $a, array $b): int {
                    return mb_strtolower((string)($a['name'] ?? '')) <=> mb_strtolower((string)($b['name'] ?? ''));
                });
                $_SESSION['sevdesk_contacts_cache'] = $normalized;
                $contacts = $normalized;
            } catch (\Throwable $e) {
                // Sevdesk nicht konfiguriert oder nicht erreichbar
                $contacts = [];
            }
        } else {
            usort($contacts, function ($a, $b): int {
                return mb_strtolower((string)($a['name'] ?? '')) <=> mb_strtolower((string)($b['name'] ?? ''));
            });
        }

        View::render('contracts/create', [
            'templates' => $templates,
            'contacts' => $contacts,
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contracts/create'));
            exit;
        }

        $templateId = (int)($_POST['template_id'] ?? 0);
        $contactId = ($_POST['sevdesk_contact_id'] ?? '') !== '' ? (int)$_POST['sevdesk_contact_id'] : null;
        $contactName = trim((string)($_POST['contact_name'] ?? ''));
        $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
        $signerName = trim((string)($_POST['signer_name'] ?? ''));
        $signerStreet = trim((string)($_POST['signer_street'] ?? ''));
        $signerZip = trim((string)($_POST['signer_zip'] ?? ''));
        $signerCity = trim((string)($_POST['signer_city'] ?? ''));
        $rawSignerCountry = trim((string)($_POST['signer_country'] ?? ''));
        $signerCountry = $rawSignerCountry !== '' ? strtoupper($rawSignerCountry) : 'DE';
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));

        // Load template if selected
        $tplRepo = new ContractTemplateRepository();
        $template = $templateId > 0 ? $tplRepo->find($templateId) : null;

        if ($title === '' && $template) {
            $title = (string)($template['title'] ?? '');
        }
        if ($body === '' && $template) {
            $body = (string)($template['body'] ?? '');
        }
        $includeSepa = $template ? (int)($template['include_sepa'] ?? 0) : (isset($_POST['include_sepa']) ? 1 : 0);

        if ($title === '' || $body === '') {
            Flash::add('error', 'Titel und Vertragstext sind Pflichtfelder.');
            header('Location: ' . App::url('/contracts/create'));
            exit;
        }

        // Resolve contact name from cache if needed
        if ($contactName === '' && $contactId !== null) {
            $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
            if (is_array($contacts)) {
                foreach ($contacts as $c) {
                    if ((int)($c['id'] ?? 0) === $contactId) {
                        $contactName = (string)($c['name'] ?? '');
                        if ($contactEmail === '') {
                            $contactEmail = trim((string)($c['email'] ?? ''));
                        }
                        break;
                    }
                }
            }
            if ($contactName === '') {
                $contactName = 'Kontakt ' . $contactId;
            }
        }

        // Try to get email from sevdesk if still empty
        if ($contactEmail === '' && $contactId !== null && $contactId > 0) {
            try {
                $client = new SevdeskClient(new SevdeskAccountRepository());
                $res = $client->getContact($contactId, null);
                $obj = null;
                if (isset($res['objects']) && is_array($res['objects']) && !empty($res['objects']) && is_array($res['objects'][0])) {
                    $obj = $res['objects'][0];
                } elseif (is_array($res)) {
                    $obj = $res;
                }
                if (is_array($obj)) {
                    $contactEmail = $this->extractEmail($obj);
                }
            } catch (\Throwable $e) {
                // optional
            }
        }

        $token = bin2hex(random_bytes(24));
        $mandateRef = $includeSepa ? $this->generateMandateReference() : null;

        $user = Auth::user();
        $repo = new ContractRepository();

        try {
            $id = $repo->create([
                'token' => $token,
                'template_id' => $templateId > 0 ? $templateId : null,
                'status' => 'open',
                'title' => $title,
                'body' => $body,
                'include_sepa' => $includeSepa,
                'sevdesk_contact_id' => $contactId,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'signer_name' => $signerName !== '' ? $signerName : null,
                'signer_street' => $signerStreet !== '' ? $signerStreet : null,
                'signer_zip' => $signerZip !== '' ? $signerZip : null,
                'signer_city' => $signerCity !== '' ? $signerCity : null,
                'signer_country' => $signerCountry,
                'mandate_reference' => $mandateRef,
                'created_by' => $user ? (int)$user['id'] : null,
            ]);
        } catch (\Throwable $e) {
            $token = bin2hex(random_bytes(24));
            $mandateRef = $includeSepa ? $this->generateMandateReference() : null;
            $id = $repo->create([
                'token' => $token,
                'template_id' => $templateId > 0 ? $templateId : null,
                'status' => 'open',
                'title' => $title,
                'body' => $body,
                'include_sepa' => $includeSepa,
                'sevdesk_contact_id' => $contactId,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'signer_name' => $signerName !== '' ? $signerName : null,
                'signer_street' => $signerStreet !== '' ? $signerStreet : null,
                'signer_zip' => $signerZip !== '' ? $signerZip : null,
                'signer_city' => $signerCity !== '' ? $signerCity : null,
                'signer_country' => $signerCountry,
                'mandate_reference' => $mandateRef,
                'created_by' => $user ? (int)$user['id'] : null,
            ]);
        }

        Flash::add('success', 'Vertrag erstellt.');
        header('Location: ' . App::url('/contracts/' . $id));
        exit;
    }

    public function edit(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $item = $repo->find($id);

        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $status = (string)($item['status'] ?? '');
        if ($status !== 'draft' && $status !== 'open') {
            Flash::add('error', 'Vertrag kann nicht bearbeitet werden, da er bereits unterschrieben oder widerrufen ist.');
            header('Location: ' . App::url('/contracts/' . $id));
            exit;
        }

        $templates = (new ContractTemplateRepository())->allActive();
        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];

        if (!is_array($contacts) || empty($contacts)) {
            try {
                $client = new SevdeskClient(new SevdeskAccountRepository());
                $all = $client->getAllContacts(null, 200, 5000);
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
                usort($normalized, function (array $a, array $b): int {
                    return mb_strtolower((string)($a['name'] ?? '')) <=> mb_strtolower((string)($b['name'] ?? ''));
                });
                $_SESSION['sevdesk_contacts_cache'] = $normalized;
                $contacts = $normalized;
            } catch (\Throwable $e) {
                $contacts = [];
            }
        } else {
            usort($contacts, function ($a, $b): int {
                return mb_strtolower((string)($a['name'] ?? '')) <=> mb_strtolower((string)($b['name'] ?? ''));
            });
        }

        View::render('contracts/edit', [
            'item' => $item,
            'templates' => $templates,
            'contacts' => $contacts,
            'csrf' => Csrf::token(),
        ]);
    }

    public function update(array $params): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contracts'));
            exit;
        }

        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $item = $repo->find($id);

        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $status = (string)($item['status'] ?? '');
        if ($status !== 'draft' && $status !== 'open') {
            Flash::add('error', 'Vertrag kann nicht bearbeitet werden, da er bereits unterschrieben oder widerrufen ist.');
            header('Location: ' . App::url('/contracts/' . $id));
            exit;
        }

        $templateId = (int)($_POST['template_id'] ?? 0);
        $contactId = ($_POST['sevdesk_contact_id'] ?? '') !== '' ? (int)$_POST['sevdesk_contact_id'] : null;
        $contactName = trim((string)($_POST['contact_name'] ?? ''));
        $contactEmail = trim((string)($_POST['contact_email'] ?? ''));
        $signerName = trim((string)($_POST['signer_name'] ?? ''));
        $signerStreet = trim((string)($_POST['signer_street'] ?? ''));
        $signerZip = trim((string)($_POST['signer_zip'] ?? ''));
        $signerCity = trim((string)($_POST['signer_city'] ?? ''));
        $rawSignerCountry = trim((string)($_POST['signer_country'] ?? ''));
        $signerCountry = $rawSignerCountry !== '' ? strtoupper($rawSignerCountry) : 'DE';
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $includeSepa = isset($_POST['include_sepa']) ? 1 : 0;

        if ($title === '' || $body === '') {
            Flash::add('error', 'Titel und Vertragstext sind Pflichtfelder.');
            header('Location: ' . App::url('/contracts/' . $id . '/edit'));
            exit;
        }

        if ($contactName === '' && $contactId !== null) {
            $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
            if (is_array($contacts)) {
                foreach ($contacts as $c) {
                    if ((int)($c['id'] ?? 0) === $contactId) {
                        $contactName = (string)($c['name'] ?? '');
                        if ($contactEmail === '') {
                            $contactEmail = trim((string)($c['email'] ?? ''));
                        }
                        break;
                    }
                }
            }
            if ($contactName === '') {
                $contactName = 'Kontakt ' . $contactId;
            }
        }

        $existingMandateRef = (string)($item['mandate_reference'] ?? '');
        if ($includeSepa) {
            $mandateRef = $existingMandateRef !== '' ? $existingMandateRef : $this->generateMandateReference();
        } else {
            $mandateRef = null;
        }

        $repo->update($id, [
            'template_id' => $templateId > 0 ? $templateId : null,
            'title' => $title,
            'body' => $body,
            'include_sepa' => $includeSepa,
            'sevdesk_contact_id' => $contactId,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'signer_name' => $signerName !== '' ? $signerName : null,
            'signer_street' => $signerStreet !== '' ? $signerStreet : null,
            'signer_zip' => $signerZip !== '' ? $signerZip : null,
            'signer_city' => $signerCity !== '' ? $signerCity : null,
            'signer_country' => $signerCountry,
            'mandate_reference' => $mandateRef,
        ]);

        Flash::add('success', 'Vertrag aktualisiert.');
        header('Location: ' . App::url('/contracts/' . $id));
        exit;
    }

    public function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $item = $repo->find($id);

        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $publicUrl = App::url('/c/' . (string)$item['token']);

        View::render('contracts/show', [
            'item' => $item,
            'publicUrl' => $publicUrl,
            'csrf' => Csrf::token(),
        ]);
    }

    public function revoke(array $params): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contracts'));
            exit;
        }

        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $repo->revoke($id);

        Flash::add('success', 'Vertrag widerrufen.');
        header('Location: ' . App::url('/contracts/' . $id));
        exit;
    }

    public function delete(array $params): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits-Token ungültig.');
            header('Location: ' . App::url('/contracts'));
            exit;
        }

        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $item = $repo->find($id);

        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $status = (string)($item['status'] ?? '');
        if ($status === 'signed') {
            Flash::add('error', 'Unterschriebene Verträge können nicht gelöscht werden.');
            header('Location: ' . App::url('/contracts/' . $id));
            exit;
        }

        $sigRel = (string)($item['signature_path'] ?? '');
        if ($sigRel !== '') {
            $sigFile = App::basePath($sigRel);
            if (is_file($sigFile)) {
                @unlink($sigFile);
            }
        }
        $pdfRel = (string)($item['pdf_path'] ?? '');
        if ($pdfRel !== '') {
            $pdfFile = App::basePath($pdfRel);
            if (is_file($pdfFile)) {
                @unlink($pdfFile);
            }
        }

        $repo->delete($id);

        Flash::add('success', 'Vertrag gelöscht.');
        header('Location: ' . App::url('/contracts'));
        exit;
    }

    public function downloadPdf(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new ContractRepository();
        $item = $repo->find($id);

        if (!$item || (string)($item['status'] ?? '') !== 'signed') {
            http_response_code(404);
            echo 'PDF nicht gefunden.';
            return;
        }

        $pdfRel = (string)($item['pdf_path'] ?? '');
        if ($pdfRel === '') {
            $pdfRel = 'storage/uploads/contracts/contract_' . (int)$item['id'] . '.pdf';
        }

        $sigRel = (string)($item['signature_path'] ?? '');
        $sigFile = $sigRel !== '' ? App::basePath($sigRel) : '';
        if ($sigFile === '' || !is_file($sigFile)) {
            http_response_code(500);
            echo 'Signatur fehlt, PDF kann nicht erstellt werden.';
            return;
        }

        $pdfFile = App::basePath($pdfRel);
        $dir = dirname($pdfFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $settings = (new SettingsRepository())->get();

        try {
            SimplePdf::createContractPdf([
                'title' => (string)($item['title'] ?? ''),
                'body' => (string)($item['body'] ?? ''),
                'include_sepa' => (int)($item['include_sepa'] ?? 0),
                'creditor_name' => (string)($settings['creditor_name'] ?? ''),
                'creditor_id' => (string)($settings['creditor_id'] ?? ''),
                'creditor_street' => (string)($settings['creditor_street'] ?? ''),
                'creditor_zip' => (string)($settings['creditor_zip'] ?? ''),
                'creditor_city' => (string)($settings['creditor_city'] ?? ''),
                'creditor_country' => (string)($settings['creditor_country'] ?? ''),
                'creditor_iban' => (string)($settings['creditor_iban'] ?? ''),
                'creditor_bic' => (string)($settings['creditor_bic'] ?? ''),
                'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                'signer_name' => (string)($item['signer_name'] ?? ''),
                'signer_street' => (string)($item['signer_street'] ?? ''),
                'signer_zip' => (string)($item['signer_zip'] ?? ''),
                'signer_city' => (string)($item['signer_city'] ?? ''),
                'signer_country' => (string)($item['signer_country'] ?? 'DE'),
                'debtor_iban' => (string)($item['debtor_iban'] ?? ''),
                'debtor_bic' => (string)($item['debtor_bic'] ?? ''),
                'payment_type' => (string)($item['payment_type'] ?? ''),
                'signed_place' => (string)($item['signed_place'] ?? ''),
                'signed_date' => (string)($item['signed_date'] ?? ''),
                'signed_at' => (string)($item['signed_at'] ?? ''),
                'signed_ip' => (string)($item['signed_ip'] ?? ''),
                'signed_user_agent' => (string)($item['signed_user_agent'] ?? ''),
            ], $sigFile, $pdfFile);

            $repo->updatePdfPath($id, $pdfRel);
        } catch (\Throwable $e) {
            Logger::error('Contract PDF Erstellung fehlgeschlagen', $e);
            http_response_code(500);
            echo 'PDF konnte nicht erstellt werden.';
            return;
        }

        if (!is_file($pdfFile)) {
            http_response_code(500);
            echo 'PDF konnte nicht erstellt werden.';
            return;
        }

        header('Content-Type: application/pdf');
        $filename = 'Vertrag_' . (int)$item['id'] . '.pdf';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
    }

    public function contact(array $params): void
    {
        $contactId = (int)($params['id'] ?? 0);
        $name = '';
        $email = '';

        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        if (is_array($contacts)) {
            foreach ($contacts as $c) {
                if ((int)($c['id'] ?? 0) === $contactId) {
                    $name = trim((string)($c['name'] ?? ''));
                    $email = trim((string)($c['email'] ?? ''));
                    break;
                }
            }
        }

        $street = '';
        $zip = '';
        $city = '';
        $country = 'DE';

        try {
            $client = new SevdeskClient(new SevdeskAccountRepository());
            $res = $client->getContact($contactId);

            $obj = null;
            if (isset($res['objects']) && is_array($res['objects']) && !empty($res['objects']) && is_array($res['objects'][0])) {
                $obj = $res['objects'][0];
            } elseif (is_array($res)) {
                $obj = $res;
            }

            if (is_array($obj)) {
                if ($name === '') {
                    $name = trim((string)($obj['name'] ?? ''));
                    if ($name === '') {
                        $parts = array_filter([
                            trim((string)($obj['surename'] ?? '')),
                            trim((string)($obj['familyname'] ?? '')),
                        ]);
                        $name = implode(' ', $parts);
                    }
                }

                if ($email === '') {
                    $email = $this->extractEmail($obj);
                }

                // Extract address from embedded addresses
                $addresses = $obj['addresses'] ?? [];
                if (is_array($addresses) && !empty($addresses)) {
                    $addr = $addresses[0];
                    if (is_array($addr)) {
                        $street = trim((string)($addr['street'] ?? ''));
                        $zip = trim((string)($addr['zip'] ?? ''));
                        $city = trim((string)($addr['city'] ?? ''));
                        $co = $addr['country'] ?? null;
                        if (is_array($co) && isset($co['code'])) {
                            $country = strtoupper(trim((string)$co['code']));
                        } elseif (is_string($co) && strlen($co) === 2) {
                            $country = strtoupper($co);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore - return what we have from cache
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'name' => $name,
            'email' => $email,
            'street' => $street,
            'zip' => $zip,
            'city' => $city,
            'country' => $country !== '' ? $country : 'DE',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function generateMandateReference(): string
    {
        $date = date('Ymd');
        $rand = (string)random_int(1000, 9999);
        return 'CV' . $date . $rand;
    }

    private function extractEmail(array $c): string
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
        return '';
    }
}
