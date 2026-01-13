<?php
declare(strict_types=1);

namespace App\Controllers;


use App\Support\Logger;
use App\Repositories\SevdeskAccountRepository;
use App\Services\SevdeskClient;
use App\Repositories\OnlineMandateRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\MandateRepository;
use App\Services\SimplePdf;
use App\Services\ValidationService;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class PublicMandateController
{
    public function show(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new OnlineMandateRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'open') {
            http_response_code(404);
            echo 'Link nicht gültig.';
            return;
        }

        $settings = (new SettingsRepository())->get();

        View::render('public/mandate_sign', [
            'item' => $item,
            'settings' => $settings,
            'csrf' => Csrf::token(),
            'old' => $this->getOld($token),
        ]);
    }

    public function sign(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new OnlineMandateRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'open') {
            http_response_code(404);
            echo 'Link nicht gültig.';
            return;
        }

        $debtorName = trim((string)($_POST['debtor_name'] ?? ''));
        $debtorStreet = trim((string)($_POST['debtor_street'] ?? ''));
        $debtorZip = trim((string)($_POST['debtor_zip'] ?? ''));
        $debtorCity = trim((string)($_POST['debtor_city'] ?? ''));
        $debtorCountry = strtoupper(trim((string)($_POST['debtor_country'] ?? 'DE')));
        $debtorIban = strtoupper(trim((string)($_POST['debtor_iban'] ?? '')));
        $debtorIban = preg_replace('/[^A-Z0-9]/', '', $debtorIban) ?: '';
        $debtorBic = strtoupper(trim((string)($_POST['debtor_bic'] ?? '')));
        $debtorBic = preg_replace('/[^A-Z0-9]/', '', $debtorBic) ?: '';
        $signedPlace = trim((string)($_POST['signed_place'] ?? ''));
        $signedDate = trim((string)($_POST['signed_date'] ?? date('Y-m-d')));
        $signature = (string)($_POST['signature_data'] ?? '');

        // Keep entered values when validation fails
        $this->setOld($token, [
            'debtor_name' => $debtorName,
            'debtor_street' => $debtorStreet,
            'debtor_zip' => $debtorZip,
            'debtor_city' => $debtorCity,
            'debtor_country' => $debtorCountry,
            'debtor_iban' => $this->formatIbanDisplay($debtorIban),
            'debtor_bic' => $debtorBic,
            'signed_place' => $signedPlace,
            'signed_date' => $signedDate,
        ]);


        if ($debtorName === '' || $debtorStreet === '' || $debtorZip === '' || $debtorCity === '' || $debtorIban === '' || $signedPlace === '' || $signedDate === '') {
            Flash::add('error', 'Bitte alle Pflichtfelder ausfüllen.');
            header('Location: ' . App::url('/m/' . $token));
            exit;
        }

        $val = new ValidationService();
        if (!$val->validateIban($debtorIban)) {
            Flash::add('error', 'IBAN ist ungültig.');
            header('Location: ' . App::url('/m/' . $token));
            exit;
        }

        if ($signature === '' || (!str_starts_with($signature, 'data:image/jpeg;base64,') && !str_starts_with($signature, 'data:image/png;base64,'))) {
            Flash::add('error', 'Bitte unterschreiben.');
            header('Location: ' . App::url('/m/' . $token));
            exit;
        }

        // Save signature image
        $sigDirRel = 'storage/uploads/signatures';
        $sigDir = App::basePath($sigDirRel);
        if (!is_dir($sigDir)) {
            @mkdir($sigDir, 0775, true);
        }
        $ext = str_starts_with($signature, 'data:image/jpeg;base64,') ? '.jpg' : '.png';
        $sigRel = $sigDirRel . '/' . $token . $ext;
        $sigFile = App::basePath($sigRel);

        $prefix = str_starts_with($signature, 'data:image/jpeg;base64,') ? 'data:image/jpeg;base64,' : 'data:image/png;base64,';
        $b64 = substr($signature, strlen($prefix));
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            Flash::add('error', 'Unterschrift konnte nicht verarbeitet werden.');
            header('Location: ' . App::url('/m/' . $token));
            exit;
        }
        file_put_contents($sigFile, $bin);

        // Create PDF
        $pdfDirRel = 'storage/uploads/mandates';
        $pdfDir = App::basePath($pdfDirRel);
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0775, true);
        }
        $pdfRel = $pdfDirRel . '/online_' . (string)$item['mandate_reference'] . '_' . date('Ymd_His') . '.pdf';
        $pdfFile = App::basePath($pdfRel);

        $settings = (new SettingsRepository())->get();

        try {
        SimplePdf::createMandatePdf([
                    'creditor_name' => (string)($settings['creditor_name'] ?? ''),
                    'creditor_id' => (string)($settings['creditor_id'] ?? ''),
                    'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                    'debtor_name' => $debtorName,
                    'debtor_street' => $debtorStreet,
                    'debtor_zip' => $debtorZip,
                    'debtor_city' => $debtorCity,
                    'debtor_country' => $debtorCountry,
                    'debtor_iban' => $debtorIban,
                    'debtor_bic' => $debtorBic,
                    'signed_place' => $signedPlace,
                    'signed_date' => $signedDate,
                ], $sigFile, $pdfFile);
        } catch (\Throwable $e) {
            \App\Support\Logger::error('PDF Erstellung fehlgeschlagen', $e);
            // Do not abort. Mandate can still be completed. PDF will be generated on demand (download).
            $pdfRel = '';
            Flash::add('warning', 'Mandat wurde gespeichert. Das PDF wird beim Download automatisch neu erstellt.');
        }

        // Save online mandate row
        $repo->markSigned((int)$item['id'], [
            'debtor_name' => $debtorName,
            'debtor_street' => $debtorStreet,
            'debtor_zip' => $debtorZip,
            'debtor_city' => $debtorCity,
            'debtor_country' => $debtorCountry,
            'debtor_iban' => $debtorIban,
            'debtor_bic' => $debtorBic,
            'signed_place' => $signedPlace,
            'signed_date' => $signedDate,
            'signature_path' => $sigRel,
            'pdf_path' => $pdfRel,
        ]);

        // Upsert into mandates table
        $mandRepo = new MandateRepository();
        $mandRepo->upsertByContactId((int)$item['sevdesk_contact_id'], [
            'debtor_name' => $debtorName,
            'debtor_iban' => $debtorIban,
            'debtor_bic' => $debtorBic !== '' ? $debtorBic : null,
            'mandate_reference' => (string)$item['mandate_reference'],
            'mandate_date' => $signedDate,
            'scheme' => 'CORE',
            'sequence_mode' => 'auto',
            'status' => 'active',
            'notes' => 'Online Mandat',
            'attachment_path' => $pdfRel,
        ]);

        // Bankdaten im sevdesk Kontakt aktualisieren, damit künftig immer die aktuelle Kontoverbindung genutzt wird
        try {
            $contactId = (int)($item['sevdesk_contact_id'] ?? 0);
            if ($contactId > 0) {
                $client = new SevdeskClient(new SevdeskAccountRepository());
                $client->updateContactBankData($contactId, $debtorIban, $debtorBic !== '' ? $debtorBic : null);
            }
        } catch (\Throwable $e) {
            // Nicht blockieren, Mandat soll trotzdem funktionieren
            (new \App\Services\Logger())->error('sevdesk Kontakt Bankdaten Update fehlgeschlagen: ' . $e->getMessage());
        }


        Flash::add('success', 'Mandat wurde gespeichert.');
        $this->clearOld($token);
        header('Location: ' . App::url('/m/' . $token . '/done'));
        exit;
    }

    public function done(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new OnlineMandateRepository();
        $item = $repo->findByToken($token);

        if (!$item || (string)$item['status'] !== 'signed') {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        View::render('public/mandate_done', [
            'item' => $item,
        ]);
    }

    public function pdf(array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $repo = new OnlineMandateRepository();
        $item = $repo->findByToken($token);

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
                'mandate_reference' => (string)($item['mandate_reference'] ?? ''),
                'debtor_name' => (string)($item['debtor_name'] ?? ''),
                'debtor_street' => (string)($item['debtor_street'] ?? ''),
                'debtor_zip' => (string)($item['debtor_zip'] ?? ''),
                'debtor_city' => (string)($item['debtor_city'] ?? ''),
                'debtor_country' => (string)($item['debtor_country'] ?? 'DE'),
                'debtor_iban' => (string)($item['debtor_iban'] ?? ''),
                'debtor_bic' => (string)($item['debtor_bic'] ?? ''),
                'signed_place' => (string)($item['signed_place'] ?? ''),
                'signed_date' => (string)($item['signed_date'] ?? ''),
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
        header('Content-Disposition: attachment; filename="SEPA_Mandat_" . (string)($item['mandate_reference'] ?? 'mandat') . ".pdf"');
        header('Content-Length: ' . filesize($pdfFile));
        readfile($pdfFile);
    }



    private function getOld(string $token): array
    {
        if (!isset($_SESSION['public_mandate_old']) || !is_array($_SESSION['public_mandate_old'])) {
            return [];
        }
        return (array)($_SESSION['public_mandate_old'][$token] ?? []);
    }

    private function setOld(string $token, array $data): void
    {
        if (!isset($_SESSION['public_mandate_old']) || !is_array($_SESSION['public_mandate_old'])) {
            $_SESSION['public_mandate_old'] = [];
        }
        $_SESSION['public_mandate_old'][$token] = $data;
    }

    private function clearOld(string $token): void
    {
        if (isset($_SESSION['public_mandate_old'][$token])) {
            unset($_SESSION['public_mandate_old'][$token]);
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
