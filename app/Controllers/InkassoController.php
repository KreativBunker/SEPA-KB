<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\InkassoHandoverRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\DunningService;
use App\Services\InkassoService;
use App\Services\MailerFactory;
use App\Services\SevdeskClient;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\View;

final class InkassoController
{
    public function index(): void
    {
        $list = $_SESSION['inkasso_cache'] ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        // Übergabe-Status aus DB anhängen
        if (!empty($list)) {
            $ids = array_values(array_filter(array_map(static fn($r): int => (int)($r['id'] ?? 0), $list)));
            $handoverMap = (new InkassoHandoverRepository())->mapByInvoiceIds($ids);
            foreach ($list as &$r) {
                $iid = (int)($r['id'] ?? 0);
                $r['handed_over'] = $iid && isset($handoverMap[$iid]);
                $r['handed_over_at'] = $r['handed_over'] ? (string)($handoverMap[$iid]['sent_at'] ?? '') : null;
            }
            unset($r);
        }

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $lower = function (string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); };
            $needle = $lower($q);
            $list = array_values(array_filter($list, function (array $r) use ($needle, $lower): bool {
                $hay = implode(' | ', [
                    (string)($r['id'] ?? ''),
                    (string)($r['invoiceNumber'] ?? ''),
                    (string)($r['contact_name'] ?? ''),
                    (string)($r['dueDate'] ?? ''),
                    (string)($r['sumGross'] ?? ''),
                ]);
                return str_contains($lower($hay), $needle);
            }));
        }

        $settings = (new SettingsRepository())->get();
        $mailReady = !empty($settings['inkasso_email']) && MailerFactory::isConfigured($settings);

        View::render('inkasso/index', [
            'csrf' => Csrf::token(),
            'invoices' => $list,
            'q' => $q,
            'mailReady' => $mailReady,
            'inkassoEmail' => (string)($settings['inkasso_email'] ?? ''),
            'messages' => Flash::all(),
        ]);
    }

    public function load(): void
    {
        Csrf::check();

        $client = new SevdeskClient(new SevdeskAccountRepository());

        try {
            // Kandidaten-Ermittlung teilt sich die Logik mit der Mahnautomatik
            $rows = (new DunningService($client))->loadOverdueInvoices();
        } catch (\Throwable $e) {
            Logger::error('Inkasso: sevdesk laden fehlgeschlagen', $e);
            Flash::add('error', 'sevdesk laden fehlgeschlagen: ' . $e->getMessage());
            header('Location: ' . App::url('/inkasso'));
            exit;
        }

        $_SESSION['inkasso_cache'] = $rows;

        Flash::add('success', 'sevdesk geladen: ' . count($rows) . ' überfällige Rechnungen.');
        header('Location: ' . App::url('/inkasso'));
        exit;
    }

    public function handover(array $params = []): void
    {
        Csrf::check();

        $invoiceId = (int)($params['id'] ?? 0);
        if ($invoiceId <= 0) {
            Flash::add('error', 'Ungültige Rechnungs-ID.');
            header('Location: ' . App::url('/inkasso'));
            exit;
        }

        $settings = (new SettingsRepository())->get();
        $inkassoEmail = trim((string)($settings['inkasso_email'] ?? ''));
        if ($inkassoEmail === '' || !MailerFactory::isConfigured($settings)) {
            Flash::add('error', 'Bitte zuerst in den Einstellungen den E-Mail-Versand und die Inkassobüro E-Mail konfigurieren.');
            header('Location: ' . App::url('/inkasso'));
            exit;
        }

        // Schutz vor versehentlicher Doppel-Übergabe
        $repo = new InkassoHandoverRepository();
        $existing = $repo->latestByInvoiceId($invoiceId);
        $resend = !empty($_POST['resend']);
        if ($existing && !$resend) {
            Flash::add('error', 'Diese Rechnung wurde bereits am ' . (string)$existing['sent_at'] . ' übergeben. Nutze "Erneut senden", um sie nochmals zu übermitteln.');
            header('Location: ' . App::url('/inkasso'));
            exit;
        }

        try {
            $client = new SevdeskClient(new SevdeskAccountRepository());
            $service = new InkassoService($client);
            $handover = $service->buildHandover($invoiceId);

            $subject = 'Inkasso-Übergabe: Rechnung ' . $handover['invoice_number'] . ' – ' . $handover['debtor']['name'];
            // Signatur kann aus dem WYSIWYG-Editor HTML enthalten – die
            // Inkasso-Übergabe bleibt eine Plaintext-E-Mail
            $signature = (string)($settings['inkasso_signature'] ?? '');
            if (\App\Support\HtmlText::isHtml($signature)) {
                $signature = \App\Support\HtmlText::toPlain($signature);
            }
            $text = $service->composeEmailText($handover, $signature);

            $mailer = MailerFactory::fromSettings($settings);
            $mailer->send($inkassoEmail, $subject, $text, $handover['attachments']);

            $user = Auth::user();
            $repo->add([
                'sevdesk_invoice_id' => $handover['invoice_id'],
                'invoice_number' => $handover['invoice_number'],
                'sevdesk_contact_id' => $handover['debtor']['id'],
                'contact_name' => $handover['debtor']['name'],
                'amount_original' => $handover['amount_original'],
                'amount_total' => $handover['amount_total'],
                'dunning_level' => $handover['dunning_level'],
                'due_date' => $handover['due_date'] !== '' ? substr($handover['due_date'], 0, 10) : null,
                'recipient_email' => $inkassoEmail,
                'attachments_count' => count($handover['attachments']),
                'sent_by' => $user ? (int)$user['id'] : null,
            ]);

            if ($user) {
                (new AuditLogRepository())->add((int)$user['id'], 'inkasso_handover', 'invoice', $invoiceId, [
                    'invoice_number' => $handover['invoice_number'],
                    'recipient' => $inkassoEmail,
                    'dunning_level' => $handover['dunning_level'],
                    'resend' => $resend,
                ]);
            }

            $msg = 'Rechnung ' . $handover['invoice_number'] . ' an ' . $inkassoEmail . ' übergeben (' . count($handover['attachments']) . ' PDF-Anhänge).';
            if (!empty($settings['smtp_test_mode'])) {
                $msg .= ' Test-Modus: E-Mail wurde nur in storage/logs/mail abgelegt.';
            }
            if (!empty($handover['pdf_errors'])) {
                $msg .= ' Hinweis: ' . implode(' / ', $handover['pdf_errors']);
            }
            Flash::add('success', $msg);
        } catch (\Throwable $e) {
            Logger::error('Inkasso-Übergabe fehlgeschlagen (Invoice ' . $invoiceId . ')', $e);
            Flash::add('error', 'Übergabe fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/inkasso'));
        exit;
    }

    public function smtpTest(): void
    {
        Csrf::check();

        $settings = (new SettingsRepository())->get();
        $to = trim((string)($settings['inkasso_email'] ?? ''));
        if ($to === '') {
            $user = Auth::user();
            $to = $user ? trim((string)($user['email'] ?? '')) : '';
        }
        if ($to === '') {
            Flash::add('error', 'Keine Empfänger-Adresse: Bitte Inkassobüro E-Mail eintragen.');
            header('Location: ' . App::url('/settings'));
            exit;
        }

        try {
            $mailer = MailerFactory::fromSettings($settings);
            $mailer->send($to, 'SEPA-KB Test-E-Mail', "Dies ist eine Test-E-Mail aus SEPA-KB.\nWenn diese Nachricht ankommt, ist der E-Mail-Versand korrekt konfiguriert.");
            $msg = 'Test-E-Mail an ' . $to . ' gesendet.';
            if (!empty($settings['smtp_test_mode'])) {
                $msg .= ' Test-Modus: E-Mail wurde nur in storage/logs/mail abgelegt.';
            }
            Flash::add('success', $msg);
        } catch (\Throwable $e) {
            Logger::error('SMTP-Test fehlgeschlagen', $e);
            Flash::add('error', 'SMTP-Test fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/settings'));
        exit;
    }

}
