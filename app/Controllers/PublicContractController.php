<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ContractRepository;
use App\Repositories\MandateRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Services\SimplePdf;
use App\Services\ValidationService;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\View;

final class PublicContractController
{
    public function show(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new ContractRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'open') {
            http_response_code(404);
            echo 'Link nicht gültig.';
            return;
        }

        $settings = (new SettingsRepository())->get();

        // Resolve placeholders in contract body
        $body = (string)($item['body'] ?? '');
        $placeholders = [
            '{{mandant_name}}' => (string)($item['signer_name'] ?? ''),
            '{{mandant_strasse}}' => (string)($item['signer_street'] ?? ''),
            '{{mandant_plz}}' => (string)($item['signer_zip'] ?? ''),
            '{{mandant_ort}}' => (string)($item['signer_city'] ?? ''),
            '{{mandant_land}}' => (string)($item['signer_country'] ?? 'DE'),
            '{{firma}}' => (string)($settings['creditor_name'] ?? ''),
            '{{firma_strasse}}' => (string)($settings['creditor_street'] ?? ''),
            '{{firma_plz}}' => (string)($settings['creditor_zip'] ?? ''),
            '{{firma_ort}}' => (string)($settings['creditor_city'] ?? ''),
            '{{firma_land}}' => (string)($settings['creditor_country'] ?? ''),
            '{{firma_iban}}' => (string)($settings['creditor_iban'] ?? ''),
            '{{firma_bic}}' => (string)($settings['creditor_bic'] ?? ''),
            '{{glaeubiger_id}}' => (string)($settings['creditor_id'] ?? ''),
            '{{datum}}' => date('d.m.Y'),
        ];
        foreach ($placeholders as $key => $value) {
            if ($value !== '') {
                $body = str_replace($key, htmlspecialchars($value), $body);
            }
        }
        $item['body'] = $body;

        View::render('public/contract_sign', [
            'item' => $item,
            'settings' => $settings,
            'csrf' => Csrf::token(),
            'old' => $this->getOld($token),
        ]);
    }

    public function sign(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new ContractRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'open') {
            http_response_code(404);
            echo 'Link nicht gültig.';
            return;
        }

        $signerName = trim((string)($_POST['signer_name'] ?? ''));
        $signerStreet = trim((string)($_POST['signer_street'] ?? ''));
        $signerZip = trim((string)($_POST['signer_zip'] ?? ''));
        $signerCity = trim((string)($_POST['signer_city'] ?? ''));
        $rawCountry = trim((string)($_POST['signer_country'] ?? ''));
        $signerCountry = strtoupper($rawCountry !== '' ? $rawCountry : 'DE');
        $signedPlace = trim((string)($_POST['signed_place'] ?? ''));
        $rawSignedDate = trim((string)($_POST['signed_date'] ?? ''));
        $signedDate = $rawSignedDate !== '' ? $rawSignedDate : date('Y-m-d');
        $signature = (string)($_POST['signature_data'] ?? '');
        $signedAt = date('Y-m-d H:i:s');
        $signedIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $signedUserAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

        // SEPA fields
        $includeSepa = (int)($item['include_sepa'] ?? 0);
        $rawIban = '';
        $debtorIban = '';
        $debtorBic = '';
        $paymentType = '';
        if ($includeSepa) {
            $rawIban = trim((string)($_POST['debtor_iban'] ?? ''));
            $debtorIban = strtoupper($rawIban);
            $debtorIban = preg_replace('/[^A-Z0-9]/', '', $debtorIban) ?: '';
            $rawBic = trim((string)($_POST['debtor_bic'] ?? ''));
            $debtorBic = strtoupper($rawBic);
            $debtorBic = preg_replace('/[^A-Z0-9]/', '', $debtorBic) ?: '';
            $rawPaymentType = trim((string)($_POST['payment_type'] ?? ''));
            $paymentType = in_array($rawPaymentType, ['OOFF', 'RCUR'], true) ? $rawPaymentType : '';
        }

        // Save old values for redisplay on error
        $this->setOld($token, [
            'signer_name' => $signerName,
            'signer_street' => $signerStreet,
            'signer_zip' => $signerZip,
            'signer_city' => $signerCity,
            'signer_country' => $signerCountry,
            'signed_place' => $signedPlace,
            'signed_date' => $rawSignedDate,
            'debtor_iban' => $this->formatIbanDisplay($rawIban),
            'debtor_bic' => $rawBic ?? '',
            'payment_type' => $paymentType,
        ]);

        // Validation
        if ($signerName === '' || $signerStreet === '' || $signerZip === '' || $signerCity === '' || $signedPlace === '' || $signedDate === '') {
            Flash::add('error', 'Bitte alle Pflichtfelder ausfüllen.');
            header('Location: ' . App::url('/c/' . $token));
            exit;
        }

        if ($includeSepa) {
            if ($debtorIban === '' || $paymentType === '') {
                Flash::add('error', 'Bitte IBAN und Zahlungsart ausfüllen.');
                header('Location: ' . App::url('/c/' . $token));
                exit;
            }
            $val = new ValidationService();
            if (!$val->validateIban($debtorIban)) {
                Flash::add('error', 'IBAN ist ungültig.');
                header('Location: ' . App::url('/c/' . $token));
                exit;
            }
        }

        if ($signature === '' || (!str_starts_with($signature, 'data:image/jpeg;base64,') && !str_starts_with($signature, 'data:image/png;base64,'))) {
            Flash::add('error', 'Bitte unterschreiben.');
            header('Location: ' . App::url('/c/' . $token));
            exit;
        }

        // Save signature image
        $sigDirRel = 'storage/uploads/signatures';
        $sigDir = App::basePath($sigDirRel);
        if (!is_dir($sigDir)) {
            @mkdir($sigDir, 0775, true);
        }
        $ext = str_starts_with($signature, 'data:image/jpeg;base64,') ? '.jpg' : '.png';
        $sigRel = $sigDirRel . '/contract_' . $token . $ext;
        $sigFile = App::basePath($sigRel);

        $prefix = str_starts_with($signature, 'data:image/jpeg;base64,') ? 'data:image/jpeg;base64,' : 'data:image/png;base64,';
        $b64 = substr($signature, strlen($prefix));
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            Flash::add('error', 'Unterschrift konnte nicht verarbeitet werden.');
            header('Location: ' . App::url('/c/' . $token));
            exit;
        }
        file_put_contents($sigFile, $bin);

        // Create PDF
        $pdfDirRel = 'storage/uploads/contracts';
        $pdfDir = App::basePath($pdfDirRel);
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0775, true);
        }
        $pdfRel = $pdfDirRel . '/contract_' . (int)$item['id'] . '_' . date('Ymd_His') . '.pdf';
        $pdfFile = App::basePath($pdfRel);

        $settings = (new SettingsRepository())->get();

        try {
            SimplePdf::createContractPdf([
                'title' => (string)($item['title'] ?? ''),
                'body' => (string)($item['body'] ?? ''),
                'creditor_name' => (string)($settings['creditor_name'] ?? ''),
                'creditor_id' => (string)($settings['creditor_id'] ?? ''),
                'creditor_street' => (string)($settings['creditor_street'] ?? ''),
                'creditor_zip' => (string)($settings['creditor_zip'] ?? ''),
                'creditor_city' => (string)($settings['creditor_city'] ?? ''),
                'creditor_country' => (string)($settings['creditor_country'] ?? ''),
                'creditor_iban' => (string)($settings['creditor_iban'] ?? ''),
                'creditor_bic' => (string)($settings['creditor_bic'] ?? ''),
                'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                'signer_name' => $signerName,
                'signer_street' => $signerStreet,
                'signer_zip' => $signerZip,
                'signer_city' => $signerCity,
                'signer_country' => $signerCountry,
                'signed_place' => $signedPlace,
                'signed_date' => $signedDate,
                'signed_at' => $signedAt,
                'signed_ip' => $signedIp,
                'signed_user_agent' => $signedUserAgent,
            ], $sigFile, $pdfFile);
        } catch (\Throwable $e) {
            Logger::error('Contract PDF Erstellung fehlgeschlagen', $e);
            $pdfRel = '';
        }

        // SEPA mandate: separate PDF document with same signature
        $sepaPdfRel = null;
        if ($includeSepa) {
            $sepaPdfRel = $pdfDirRel . '/sepa_' . (int)$item['id'] . '_' . date('Ymd_His') . '.pdf';
            $sepaPdfFile = App::basePath($sepaPdfRel);
            try {
                SimplePdf::createMandatePdf([
                    'creditor_name' => (string)($settings['creditor_name'] ?? ''),
                    'creditor_id' => (string)($settings['creditor_id'] ?? ''),
                    'creditor_street' => (string)($settings['creditor_street'] ?? ''),
                    'creditor_zip' => (string)($settings['creditor_zip'] ?? ''),
                    'creditor_city' => (string)($settings['creditor_city'] ?? ''),
                    'creditor_country' => (string)($settings['creditor_country'] ?? ''),
                    'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                    'debtor_name' => $signerName,
                    'debtor_street' => $signerStreet,
                    'debtor_zip' => $signerZip,
                    'debtor_city' => $signerCity,
                    'debtor_country' => $signerCountry,
                    'debtor_iban' => $debtorIban,
                    'debtor_bic' => $debtorBic,
                    'payment_type' => $paymentType,
                    'signed_place' => $signedPlace,
                    'signed_date' => $signedDate,
                    'signed_at' => $signedAt,
                    'signed_ip' => $signedIp,
                    'signed_user_agent' => $signedUserAgent,
                ], $sigFile, $sepaPdfFile);
            } catch (\Throwable $e) {
                Logger::error('SEPA PDF Erstellung fehlgeschlagen', $e);
                $sepaPdfRel = null;
            }
        }

        // Update contract record
        $repo->markSigned((int)$item['id'], [
            'signer_name' => $signerName,
            'signer_street' => $signerStreet,
            'signer_zip' => $signerZip,
            'signer_city' => $signerCity,
            'signer_country' => $signerCountry,
            'debtor_iban' => $includeSepa ? $debtorIban : null,
            'debtor_bic' => $includeSepa && $debtorBic !== '' ? $debtorBic : null,
            'payment_type' => $includeSepa ? $paymentType : null,
            'signed_place' => $signedPlace,
            'signed_date' => $signedDate,
            'signature_path' => $sigRel,
            'pdf_path' => $pdfRel,
            'sepa_pdf_path' => $sepaPdfRel,
            'signed_at' => $signedAt,
            'signed_ip' => $signedIp,
            'signed_user_agent' => $signedUserAgent,
        ]);

        // SEPA integration: create mandate + update sevdesk bank data
        if ($includeSepa && ($item['sevdesk_contact_id'] ?? null)) {
            $contactId = (int)$item['sevdesk_contact_id'];
            if ($contactId > 0) {
                try {
                    $mandRepo = new MandateRepository();
                    $mandRepo->upsertByContactId($contactId, [
                        'debtor_name' => $signerName,
                        'debtor_iban' => $debtorIban,
                        'debtor_bic' => $debtorBic !== '' ? $debtorBic : null,
                        'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                        'mandate_date' => $signedDate,
                        'scheme' => 'CORE',
                        'sequence_mode' => 'auto',
                        'status' => 'active',
                        'notes' => 'Vertrag Online Mandat',
                        'attachment_path' => $pdfRel,
                    ]);
                } catch (\Throwable $e) {
                    Logger::error('Mandat Upsert fehlgeschlagen', $e);
                }

                try {
                    $client = new SevdeskClient(new SevdeskAccountRepository());
                    $client->updateContactBankData($contactId, $debtorIban, $debtorBic !== '' ? $debtorBic : null);
                } catch (\Throwable $e) {
                    Logger::error('sevdesk Bankdaten Update fehlgeschlagen', $e);
                }
            }
        }

        Flash::add('success', 'Vertrag wurde unterschrieben.');
        $this->clearOld($token);
        header('Location: ' . App::url('/c/' . $token . '/done'));
        exit;
    }

    public function done(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new ContractRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'signed') {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        View::render('public/contract_done', [
            'item' => $item,
        ]);
    }

    public function pdf(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new ContractRepository();
        $item = $repo->findByToken($token);

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
                'signed_place' => (string)($item['signed_place'] ?? ''),
                'signed_date' => (string)($item['signed_date'] ?? ''),
                'signed_at' => (string)($item['signed_at'] ?? ''),
                'signed_ip' => (string)($item['signed_ip'] ?? ''),
                'signed_user_agent' => (string)($item['signed_user_agent'] ?? ''),
            ], $sigFile, $pdfFile);

            $repo->updatePdfPath((int)$item['id'], $pdfRel);
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

    public function sepaPdf(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new ContractRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)($item['status'] ?? '') !== 'signed' || !(int)($item['include_sepa'] ?? 0)) {
            http_response_code(404);
            echo 'SEPA-Mandat nicht gefunden.';
            return;
        }

        $sigRel = (string)($item['signature_path'] ?? '');
        $sigFile = $sigRel !== '' ? App::basePath($sigRel) : '';
        if ($sigFile === '' || !is_file($sigFile)) {
            http_response_code(500);
            echo 'Signatur fehlt, PDF kann nicht erstellt werden.';
            return;
        }

        $pdfRel = (string)($item['sepa_pdf_path'] ?? '');
        if ($pdfRel === '') {
            $pdfRel = 'storage/uploads/contracts/sepa_' . (int)$item['id'] . '.pdf';
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
                'debtor_name' => (string)($item['signer_name'] ?? ''),
                'debtor_street' => (string)($item['signer_street'] ?? ''),
                'debtor_zip' => (string)($item['signer_zip'] ?? ''),
                'debtor_city' => (string)($item['signer_city'] ?? ''),
                'debtor_country' => (string)($item['signer_country'] ?? 'DE'),
                'debtor_iban' => (string)($item['debtor_iban'] ?? ''),
                'debtor_bic' => (string)($item['debtor_bic'] ?? ''),
                'payment_type' => (string)($item['payment_type'] ?? ''),
                'signed_place' => (string)($item['signed_place'] ?? ''),
                'signed_date' => (string)($item['signed_date'] ?? ''),
                'signed_at' => (string)($item['signed_at'] ?? ''),
                'signed_ip' => (string)($item['signed_ip'] ?? ''),
                'signed_user_agent' => (string)($item['signed_user_agent'] ?? ''),
            ], $sigFile, $pdfFile);

            $repo->updateSepaPdfPath((int)$item['id'], $pdfRel);
        } catch (\Throwable $e) {
            Logger::error('SEPA PDF Erstellung fehlgeschlagen', $e);
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
        $filename = 'SEPA_Mandat_' . (int)$item['id'] . '.pdf';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
    }

    private function getOld(string $token): array
    {
        if (!isset($_SESSION['public_contract_old']) || !is_array($_SESSION['public_contract_old'])) {
            return [];
        }
        return (array)($_SESSION['public_contract_old'][$token] ?? []);
    }

    private function setOld(string $token, array $data): void
    {
        if (!isset($_SESSION['public_contract_old']) || !is_array($_SESSION['public_contract_old'])) {
            $_SESSION['public_contract_old'] = [];
        }
        $_SESSION['public_contract_old'][$token] = $data;
    }

    private function clearOld(string $token): void
    {
        if (isset($_SESSION['public_contract_old'][$token])) {
            unset($_SESSION['public_contract_old'][$token]);
        }
    }

    private function formatIbanDisplay(string $iban): string
    {
        $iban = preg_replace('/[^A-Z0-9]/', '', strtoupper($iban)) ?: '';
        if ($iban === '') {
            return '';
        }
        return trim(chunk_split($iban, 4, ' '));
    }
}
