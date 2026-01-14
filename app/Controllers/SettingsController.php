<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\SettingsRepository;
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
        ];

        if ($data['creditor_name'] === '' || $data['creditor_id'] === '' || $data['creditor_iban'] === '') {
            Flash::add('error', 'Bitte Pflichtfelder ausfüllen.');
            header('Location: ' . \App\Support\App::url('/settings'));
            exit;
        }

        (new SettingsRepository())->update($data);

        $user = Auth::user();
        if ($user) {
            (new AuditLogRepository())->add((int)$user['id'], 'settings_update', 'settings', 1, []);
        }

        Flash::add('success', 'Gespeichert.');
        header('Location: ' . \App\Support\App::url('/settings'));
        exit;
    }
}
