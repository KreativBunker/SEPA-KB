<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\SettingsRepository;
use App\Services\CryptoService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class SettingsController
{
    public function edit(): void
    {
        $settings = (new SettingsRepository())->get();

        View::render('settings', [
            'csrf' => Csrf::token(),
            'settings' => $settings,
            'messages' => Flash::all(),
        ]);
    }

    public function update(): void
    {
        Csrf::check();

        $repo = new SettingsRepository();
        $current = $repo->get();

        // SMTP-Passwort: leer lassen = bestehendes Passwort behalten
        $smtpPassEncrypted = $current['smtp_pass_encrypted'] ?? null;
        $smtpPass = (string)($_POST['smtp_pass'] ?? '');
        if ($smtpPass !== '') {
            $smtpPassEncrypted = (new CryptoService())->encrypt($smtpPass);
        }

        $smtpEncryption = (string)($_POST['smtp_encryption'] ?? 'tls');
        if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
            $smtpEncryption = 'tls';
        }

        // M365 Client Secret: leer lassen = bestehendes Secret behalten
        $m365SecretEncrypted = $current['m365_client_secret_encrypted'] ?? null;
        $m365Secret = (string)($_POST['m365_client_secret'] ?? '');
        if ($m365Secret !== '') {
            $m365SecretEncrypted = (new CryptoService())->encrypt($m365Secret);
        }

        $mailProvider = (($_POST['mail_provider'] ?? 'smtp') === 'm365') ? 'm365' : 'smtp';

        // Webcron-Token: beim ersten Speichern erzeugen, auf Wunsch neu generieren
        $cronToken = trim((string)($current['dunning_cron_token'] ?? ''));
        if ($cronToken === '' || !empty($_POST['dunning_regenerate_cron_token'])) {
            $cronToken = bin2hex(random_bytes(24));
        }

        $dunningDays = static function (string $field) {
            return max(0, min(365, (int)($_POST[$field] ?? 7)));
        };

        // WYSIWYG-Felder: HTML auf erlaubte Formatierungs-Tags reduzieren,
        // reiner Text (Alt-Bestand) bleibt unverändert
        $richField = static function (string $field): ?string {
            $raw = str_replace(["\r\n", "\r"], "\n", (string)($_POST[$field] ?? ''));
            if (\App\Support\HtmlText::isHtml($raw)) {
                $clean = \App\Support\HtmlText::sanitize($raw);
                return \App\Support\HtmlText::toPlain($clean) !== '' ? $clean : null;
            }
            return trim($raw) !== '' ? trim($raw) : null;
        };

        $data = [
            'creditor_name' => trim((string)($_POST['creditor_name'] ?? '')),
            'creditor_id' => trim((string)($_POST['creditor_id'] ?? '')),
            'creditor_iban' => trim((string)($_POST['creditor_iban'] ?? '')),
            'creditor_bic' => trim((string)($_POST['creditor_bic'] ?? '')) ?: null,
            'creditor_street' => trim((string)($_POST['creditor_street'] ?? '')) ?: null,
            'creditor_zip' => trim((string)($_POST['creditor_zip'] ?? '')) ?: null,
            'creditor_city' => trim((string)($_POST['creditor_city'] ?? '')) ?: null,
            'creditor_country' => trim((string)($_POST['creditor_country'] ?? '')) ?: null,
            'initiating_party_name' => trim((string)($_POST['initiating_party_name'] ?? '')) ?: null,
            'default_scheme' => ($_POST['default_scheme'] ?? 'CORE') === 'B2B' ? 'B2B' : 'CORE',
            'default_days_until_collection' => (int)($_POST['default_days_until_collection'] ?? 5),
            'batch_booking' => !empty($_POST['batch_booking']) ? 1 : 0,
            'sanitize_text' => !empty($_POST['sanitize_text']) ? 1 : 0,
            'include_bic' => !empty($_POST['include_bic']) ? 1 : 0,
            'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')) ?: null,
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_encryption' => $smtpEncryption,
            'smtp_user' => trim((string)($_POST['smtp_user'] ?? '')) ?: null,
            'smtp_pass_encrypted' => $smtpPassEncrypted,
            'smtp_from_email' => trim((string)($_POST['smtp_from_email'] ?? '')) ?: null,
            'smtp_from_name' => trim((string)($_POST['smtp_from_name'] ?? '')) ?: null,
            'smtp_test_mode' => !empty($_POST['smtp_test_mode']) ? 1 : 0,
            'inkasso_email' => trim((string)($_POST['inkasso_email'] ?? '')) ?: null,
            'mail_provider' => $mailProvider,
            'm365_tenant_id' => trim((string)($_POST['m365_tenant_id'] ?? '')) ?: null,
            'm365_client_id' => trim((string)($_POST['m365_client_id'] ?? '')) ?: null,
            'm365_client_secret_encrypted' => $m365SecretEncrypted,
            'inkasso_signature' => $richField('inkasso_signature'),
            'dunning_enabled' => !empty($_POST['dunning_enabled']) ? 1 : 0,
            'dunning_mode' => (($_POST['dunning_mode'] ?? 'review') === 'auto') ? 'auto' : 'review',
            'dunning_days_stage1' => $dunningDays('dunning_days_stage1'),
            'dunning_days_stage2' => $dunningDays('dunning_days_stage2'),
            'dunning_days_stage3' => $dunningDays('dunning_days_stage3'),
            'dunning_pay_days' => $dunningDays('dunning_pay_days'),
            'dunning_skip_sepa' => !empty($_POST['dunning_skip_sepa']) ? 1 : 0,
            'dunning_cron_token' => $cronToken,
            'dunning_subject_1' => trim((string)($_POST['dunning_subject_1'] ?? '')) ?: null,
            'dunning_subject_2' => trim((string)($_POST['dunning_subject_2'] ?? '')) ?: null,
            'dunning_subject_3' => trim((string)($_POST['dunning_subject_3'] ?? '')) ?: null,
            'dunning_body_1' => $richField('dunning_body_1'),
            'dunning_body_2' => $richField('dunning_body_2'),
            'dunning_body_3' => $richField('dunning_body_3'),
        ];

        if ($data['creditor_name'] === '' || $data['creditor_id'] === '' || $data['creditor_iban'] === '') {
            Flash::add('error', 'Bitte Pflichtfelder ausfüllen.');
            header('Location: ' . \App\Support\App::url('/settings'));
            exit;
        }

        $repo->update($data);

        $user = Auth::user();
        if ($user) {
            (new AuditLogRepository())->add((int)$user['id'], 'settings_update', 'settings', 1, []);
        }

        Flash::add('success', 'Gespeichert.');
        header('Location: ' . \App\Support\App::url('/settings'));
        exit;
    }
}
