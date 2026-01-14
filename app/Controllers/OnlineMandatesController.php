<?php
declare(strict_types=1);

namespace App\Controllers;




use App\Support\Logger;
use App\Services\SimplePdf;
use App\Repositories\SettingsRepository;
use App\Repositories\OnlineMandateRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;
use App\Services\SevdeskClient;

final class OnlineMandatesController
{
    public function index(): void
    {
        $repo = new OnlineMandateRepository();
        $items = $repo->all();

        View::render('online_mandates/index', [
            'items' => $items,
            'csrf' => Csrf::token(),
        ]);
    }

    public function create(): void
    {
        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        if (is_array($contacts)) {
            usort($contacts, function ($a, $b): int {
                $an = mb_strtolower((string)($a['name'] ?? ''));
                $bn = mb_strtolower((string)($b['name'] ?? ''));
                return $an <=> $bn;
            });
        }
        View::render('online_mandates/create', [
            'contacts' => $contacts,
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits Token ungültig.');
            header('Location: ' . App::url('/online-mandates/create'));
            exit;
        }

        $contactId = (int)($_POST['sevdesk_contact_id'] ?? 0);
        $email = trim((string)($_POST['debtor_email'] ?? ''));

        // Optional: wenn leer, aus Cache übernehmen
        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        if ($email === '' && is_array($contacts)) {
            foreach ($contacts as $c) {
                if ((int)($c['id'] ?? 0) === $contactId) {
                    $cand = trim((string)($c['email'] ?? ''));
                    if ($cand !== '') {
                        $email = $cand;
                    }
                    break;
                }
            }
        }


        
        // Optional: wenn immer noch leer, direkt aus sevdesk holen (ein einzelner Request)
        if ($email === '') {
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
                    $cand = $this->extractEmail($obj);
                    if ($cand !== '') {
                        $email = $cand;
                    }
                }
            } catch (\Throwable $e) {
                // ignore, bleibt optional
            }
        }

if ($contactId <= 0) {
            Flash::add('error', 'Bitte einen sevdesk Kontakt auswählen.');
            header('Location: ' . App::url('/online-mandates/create'));
            exit;
        }

        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        $contactName = '';
        foreach ($contacts as $c) {
            if ((int)($c['id'] ?? 0) === $contactId) {
                $contactName = (string)($c['name'] ?? '');
                break;
            }
        }
        if ($contactName === '') {
            $contactName = 'Kontakt ' . $contactId;
        }

        $token = bin2hex(random_bytes(24));
        $mandateReference = $this->generateReference();

        $user = Auth::user();
        $repo = new OnlineMandateRepository();

        try {
            $id = $repo->create([
                'token' => $token,
                'status' => 'open',
                'created_by' => $user ? (int)$user['id'] : null,
                'sevdesk_contact_id' => $contactId,
                'contact_name' => $contactName,
                'mandate_reference' => $mandateReference,
                'debtor_email' => $email,
            ]);
        } catch (\Throwable $e) {
            // Retry once on rare collisions
            $token = bin2hex(random_bytes(24));
            $mandateReference = $this->generateReference();
            $id = $repo->create([
                'token' => $token,
                'status' => 'open',
                'created_by' => $user ? (int)$user['id'] : null,
                'sevdesk_contact_id' => $contactId,
                'contact_name' => $contactName,
                'mandate_reference' => $mandateReference,
                'debtor_email' => $email,
            ]);
        }

        Flash::add('success', 'Link erstellt.');
        header('Location: ' . App::url('/online-mandates/' . $id));
        exit;
    }

    
    public function contact(array $params): void
    {
        $contactId = (int)($params['id'] ?? 0);
        $email = '';

        $contacts = $_SESSION['sevdesk_contacts_cache'] ?? [];
        if (is_array($contacts)) {
            foreach ($contacts as $c) {
                if ((int)($c['id'] ?? 0) === $contactId) {
                    $email = trim((string)($c['email'] ?? ''));
                    break;
                }
            }
        }

        if ($email === '') {
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
                    $cand = $this->extractEmail($obj);
                    if ($cand !== '') {
                        $email = $cand;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['email' => $email], JSON_UNESCAPED_UNICODE);
    }

public function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new OnlineMandateRepository();
        $item = $repo->find($id);

        if (!$item) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $publicUrl = App::url('/m/' . (string)$item['token']);

        View::render('online_mandates/show', [
            'item' => $item,
            'publicUrl' => $publicUrl,
            'csrf' => Csrf::token(),
        ]);
    }

    public function revoke(array $params): void
    {
        if (!Csrf::check((string)($_POST['_csrf'] ?? ''))) {
            Flash::add('error', 'Sicherheits Token ungültig.');
            header('Location: ' . App::url('/online-mandates'));
            exit;
        }

        $id = (int)($params['id'] ?? 0);
        $repo = new OnlineMandateRepository();
        $repo->revoke($id);

        Flash::add('success', 'Link deaktiviert.');
        header('Location: ' . App::url('/online-mandates/' . $id));
        exit;
    }

    public function downloadPdf(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $repo = new OnlineMandateRepository();
        $item = $repo->find($id);

        if (!$item || (string)($item['status'] ?? '') !== 'signed') {
            http_response_code(404);
            echo 'PDF nicht gefunden.';
            return;
        }

        $pdfRel = (string)($item['pdf_path'] ?? '');
        if ($pdfRel === '') {
            $pdfRel = 'storage/uploads/mandates/online_' . (string)($item['mandate_reference'] ?? 'mandat') . '.pdf';
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
            SimplePdf::createMandatePdf([
                'creditor_name' => (string)($settings['creditor_name'] ?? ''),
                'creditor_id' => (string)($settings['creditor_id'] ?? ''),
                'creditor_street' => (string)($settings['creditor_street'] ?? ''),
                'creditor_zip' => (string)($settings['creditor_zip'] ?? ''),
                'creditor_city' => (string)($settings['creditor_city'] ?? ''),
                'creditor_country' => (string)($settings['creditor_country'] ?? ''),
                'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                'debtor_name' => (string)($item['debtor_name'] ?? ''),
                'debtor_street' => (string)($item['debtor_street'] ?? ''),
                'debtor_zip' => (string)($item['debtor_zip'] ?? ''),
                'debtor_city' => (string)($item['debtor_city'] ?? ''),
                'debtor_country' => (string)($item['debtor_country'] ?? 'DE'),
                'debtor_iban' => (string)($item['debtor_iban'] ?? ''),
                'debtor_bic' => (string)($item['debtor_bic'] ?? ''),
                'payment_type' => (string)($item['payment_type'] ?? ''),
                'signed_place' => (string)($item['signed_place'] ?? ''),
                'signed_date' => (string)($item['signed_date'] ?? ''),
                'signed_at' => (string)($item['signed_at'] ?? ''),
                'signed_ip' => (string)($item['signed_ip'] ?? ''),
                'signed_user_agent' => (string)($item['signed_user_agent'] ?? ''),
            ], $sigFile, $pdfFile);

            $repo->updatePdfPath((int)$item['id'], $pdfRel);
        } catch (\Throwable $e) {
            Logger::error('PDF Erstellung fehlgeschlagen', $e);
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
        header('Content-Disposition: attachment; filename="SEPA_Mandat_' . (string)($item['mandate_reference'] ?? 'mandat') . '.pdf"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
    }

    private function generateReference(): string
    {
        $date = date('Ymd');
        $rand = (string)random_int(1000, 9999);
        return 'OM' . $date . $rand;
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
