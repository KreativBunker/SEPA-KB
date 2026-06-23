<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\DunningActionRepository;
use App\Repositories\DunningExclusionRepository;
use App\Repositories\DunningRunRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\DunningService;
use App\Services\MailerFactory;
use App\Services\SevdeskClient;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\View;

final class DunningController
{
    public function index(): void
    {
        $this->renderIndex();
    }

    /**
     * Diagnose: prüft für eine Rechnungsnummer/-ID rein lesend, wie sie in
     * sevdesk vorliegt und ob sie über eine Stornorechnung als erledigt
     * erkannt wird. Hilft bei Rechnungen, die fälschlich weiter gemahnt werden.
     */
    public function diagnose(): void
    {
        Csrf::check();

        $needle = trim((string)($_POST['invoice'] ?? ''));
        $diagnose = null;
        if ($needle === '') {
            Flash::add('error', 'Bitte eine Rechnungsnummer oder sevdesk-ID eingeben.');
        } else {
            try {
                $diagnose = $this->service()->diagnoseInvoice($needle);
                if (!$diagnose['found']) {
                    Flash::add('error', 'Rechnung „' . $needle . '" wurde in sevdesk nicht gefunden.');
                }
            } catch (\Throwable $e) {
                Logger::error('Mahnwesen: Diagnose fehlgeschlagen', $e);
                Flash::add('error', 'Diagnose fehlgeschlagen: ' . $e->getMessage());
            }
        }

        $this->renderIndex(['diagnose' => $diagnose, 'diagnoseInput' => $needle]);
    }

    private function renderIndex(array $extra = []): void
    {
        $settings = (new SettingsRepository())->get();
        $service = $this->service();

        // Aktueller Ist-Stand live aus sevdesk (offene, überfällige Rechnungen).
        // Fehler werden abgefangen, damit die Seite auch ohne sevdesk-Verbindung
        // bedienbar bleibt.
        $liveOverdue = [];
        $liveDisposition = [];
        $liveError = null;
        try {
            $liveOverdue = $service->loadOverdueInvoices();
            $liveDisposition = $service->explainOverdue($liveOverdue, $settings);
        } catch (\Throwable $e) {
            Logger::error('Mahnwesen: Live-Abruf des Ist-Stands fehlgeschlagen', $e);
            $liveError = $e->getMessage();
        }

        View::render('dunning/index', array_merge([
            'csrf' => Csrf::token(),
            'pending' => (new DunningActionRepository())->findPending(),
            'history' => (new DunningActionRepository())->recent(100),
            'exclusions' => (new DunningExclusionRepository())->all(),
            'runs' => (new DunningRunRepository())->recent(10),
            'liveOverdue' => $liveOverdue,
            'liveDisposition' => $liveDisposition,
            'liveError' => $liveError,
            'service' => $service,
            'dunningEnabled' => !empty($settings['dunning_enabled']),
            'dunningMode' => (($settings['dunning_mode'] ?? 'review') === 'auto') ? 'auto' : 'review',
            'mailReady' => MailerFactory::isConfigured($settings),
            'testMode' => !empty($settings['smtp_test_mode']),
            'cronUrl' => !empty($settings['dunning_cron_token'])
                ? App::url('/cron/dunning/' . (string)$settings['dunning_cron_token'])
                : '',
            'messages' => Flash::all(),
            'diagnose' => null,
            'diagnoseInput' => '',
        ], $extra));
    }

    public function scan(): void
    {
        Csrf::check();

        try {
            $result = $this->service()->runCron('manual', true);
            if (empty($result['ran'])) {
                Flash::add('error', (string)($result['log'] ?? 'Mahnlauf konnte nicht gestartet werden.'));
            } else {
                Flash::add('success', 'Mahnlauf abgeschlossen: ' . (int)$result['candidates'] . ' überfällige Rechnungen geprüft, '
                    . (int)$result['queued'] . ' neue Mahnvorschläge, ' . (int)$result['skipped'] . ' übersprungen.');
            }
        } catch (\Throwable $e) {
            Logger::error('Mahnwesen: manueller Scan fehlgeschlagen', $e);
            Flash::add('error', 'Mahnlauf fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    public function approve(): void
    {
        Csrf::check();

        $ids = $_POST['action_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0));
        if (empty($ids)) {
            Flash::add('error', 'Keine Mahnvorschläge ausgewählt.');
            header('Location: ' . App::url('/dunning'));
            exit;
        }

        $settings = (new SettingsRepository())->get();
        if (!MailerFactory::isConfigured($settings)) {
            Flash::add('error', 'Bitte zuerst in den Einstellungen den E-Mail-Versand konfigurieren.');
            header('Location: ' . App::url('/dunning'));
            exit;
        }

        $service = $this->service();
        $repo = new DunningActionRepository();
        $user = Auth::user();
        $userId = $user ? (int)$user['id'] : null;

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $test = 0;

        foreach ($repo->findByIds($ids) as $action) {
            if (($action['status'] ?? '') !== 'pending') {
                continue;
            }

            $result = $service->executeAction($action, $settings, $userId);

            if ($result === 'sent') {
                $sent++;
                if ($userId) {
                    (new AuditLogRepository())->add($userId, 'dunning_sent', 'invoice', (int)$action['sevdesk_invoice_id'], [
                        'invoice_number' => (string)$action['invoice_number'],
                        'stage' => (int)$action['stage'],
                        'recipient' => (string)($action['recipient_email'] ?? ''),
                    ]);
                }
            } elseif ($result === 'skipped') {
                $skipped++;
            } elseif ($result === 'test') {
                $test++;
            } else {
                $failed++;
            }
        }

        $parts = [];
        if ($sent > 0) {
            $parts[] = $sent . ' gesendet';
        }
        if ($test > 0) {
            $parts[] = $test . ' im Test-Modus simuliert (E-Mail in storage/logs/mail, kein Beleg in sevdesk)';
        }
        if ($skipped > 0) {
            $parts[] = $skipped . ' übersprungen';
        }
        if ($failed > 0) {
            $parts[] = $failed . ' fehlgeschlagen';
        }
        Flash::add($failed > 0 ? 'error' : 'success', 'Mahnungen verarbeitet: ' . (empty($parts) ? 'keine Aktion' : implode(', ', $parts)) . '.');

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    public function skip(array $params = []): void
    {
        Csrf::check();

        $id = (int)($params['id'] ?? 0);
        $repo = new DunningActionRepository();
        $action = $id > 0 ? $repo->find($id) : null;
        if ($action && ($action['status'] ?? '') === 'pending') {
            $repo->markSkipped($id, 'Manuell übersprungen');
            Flash::add('success', 'Mahnvorschlag für Rechnung ' . (string)$action['invoice_number'] . ' übersprungen.');
        } else {
            Flash::add('error', 'Mahnvorschlag nicht gefunden oder nicht mehr offen.');
        }

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    public function retry(array $params = []): void
    {
        Csrf::check();

        $id = (int)($params['id'] ?? 0);
        $repo = new DunningActionRepository();
        $action = $id > 0 ? $repo->find($id) : null;
        if ($action && in_array((string)($action['status'] ?? ''), ['failed', 'skipped'], true)) {
            $repo->resetToPending($id);
            Flash::add('success', 'Mahnvorschlag für Rechnung ' . (string)$action['invoice_number'] . ' wieder vorgemerkt.');
        } else {
            Flash::add('error', 'Mahnvorschlag nicht gefunden.');
        }

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    public function exclude(): void
    {
        Csrf::check();

        $scope = (string)($_POST['scope'] ?? '');
        $sevdeskId = (int)($_POST['sevdesk_id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $note = trim((string)($_POST['note'] ?? '')) ?: null;

        $user = Auth::user();
        $added = (new DunningExclusionRepository())->add($scope, $sevdeskId, $label, $note, $user ? (int)$user['id'] : null);

        if ($added) {
            Flash::add('success', ($scope === 'contact' ? 'Kontakt' : 'Rechnung') . ' ' . ($label !== '' ? $label : (string)$sevdeskId) . ' vom Mahnlauf ausgeschlossen.');
        } else {
            Flash::add('error', 'Ausschluss konnte nicht angelegt werden (ungültig oder bereits vorhanden).');
        }

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    public function unexclude(array $params = []): void
    {
        Csrf::check();

        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            (new DunningExclusionRepository())->remove($id);
            Flash::add('success', 'Ausschluss entfernt.');
        }

        header('Location: ' . App::url('/dunning'));
        exit;
    }

    private function service(): DunningService
    {
        return new DunningService(new SevdeskClient(new SevdeskAccountRepository()));
    }
}
