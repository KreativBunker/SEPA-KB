<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\InkassoHandoverRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
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
            // 1. Offene Rechnungen paginiert laden (wie InvoicesController)
            $all = [];
            $limit = 200;
            $offset = 0;
            for ($page = 0; $page < 20; $page++) { // max 4000
                $res = $client->getInvoices($limit, $offset, 'contact');
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

            // 2. Alle Mahnungen (invoiceType=MA) in einer zweiten paginierten Abfrage
            //    laden und nach Ursprungsrechnung gruppieren – vermeidet N+1 API-Calls.
            $dunningsByOrigin = [];
            $offset = 0;
            for ($page = 0; $page < 20; $page++) {
                $res = $client->getDunningInvoices($limit, $offset);
                $objs = $res['objects'] ?? [];
                if (!is_array($objs) || empty($objs)) {
                    break;
                }
                foreach ($objs as $ma) {
                    if (!is_array($ma) || ($ma['invoiceType'] ?? 'MA') !== 'MA') {
                        continue;
                    }
                    $originId = 0;
                    $origin = $ma['origin'] ?? null;
                    if (is_array($origin) && !empty($origin['id'])) {
                        $originId = (int)$origin['id'];
                    }
                    if ($originId > 0) {
                        $dunningsByOrigin[$originId][] = $ma;
                    }
                }
                if (count($objs) < $limit) {
                    break;
                }
                $offset += $limit;
            }
        } catch (\Throwable $e) {
            Logger::error('Inkasso: sevdesk laden fehlgeschlagen', $e);
            Flash::add('error', 'sevdesk laden fehlgeschlagen: ' . $e->getMessage());
            header('Location: ' . App::url('/inkasso'));
            exit;
        }

        // 3. Filtern: offene, unbezahlte, überfällige Rechnungen (keine Mahnbelege selbst)
        $today = date('Y-m-d');
        $rows = [];
        foreach ($all as $inv) {
            if (($inv['invoiceType'] ?? 'RE') === 'MA') {
                continue;
            }
            if ((int)($inv['status'] ?? 0) !== 200) {
                continue;
            }
            if (!empty($inv['payDate'])) {
                continue;
            }

            $id = (int)($inv['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $dueDate = substr((string)($inv['dueDate'] ?? ''), 0, 10);
            $dunnings = $dunningsByOrigin[$id] ?? [];

            $overdue = $dueDate !== '' && $dueDate < $today;
            if (!$overdue && empty($dunnings)) {
                continue;
            }

            usort($dunnings, static function (array $a, array $b): int {
                return strcmp((string)($a['invoiceDate'] ?? ''), (string)($b['invoiceDate'] ?? ''));
            });

            $sumGross = (float)($inv['sumGross'] ?? 0);
            $totalClaim = $sumGross;
            $lastDunningDate = '';
            if (!empty($dunnings)) {
                $last = $dunnings[count($dunnings) - 1];
                $totalClaim = max($totalClaim, (float)($last['sumGross'] ?? 0));
                $lastDunningDate = substr((string)($last['invoiceDate'] ?? ''), 0, 10);
            }

            $daysOverdue = 0;
            if ($overdue) {
                $ts = strtotime($dueDate);
                if ($ts !== false) {
                    $daysOverdue = max(0, (int)floor((time() - $ts) / 86400));
                }
            }

            $contactId = null;
            $contactName = '';
            $contact = $inv['contact'] ?? null;
            if (is_array($contact)) {
                $contactId = !empty($contact['id']) ? (int)$contact['id'] : null;
                $contactName = $this->extractContactName($contact);
            }
            if ($contactName === '' || $contactName === 'Unbekannt') {
                $fallback = trim((string)($inv['customerName'] ?? ($inv['contactName'] ?? '')));
                if ($fallback !== '') {
                    $contactName = $fallback;
                }
            }

            $rows[] = [
                'id' => $id,
                'invoiceNumber' => (string)($inv['invoiceNumber'] ?? ''),
                'contact_id' => $contactId,
                'contact_name' => $contactName !== '' ? $contactName : 'Unbekannt',
                'dueDate' => $dueDate,
                'days_overdue' => $daysOverdue,
                'sumGross' => $sumGross,
                'total_claim' => $totalClaim,
                'currency' => (string)($inv['currency'] ?? 'EUR'),
                'dunning_level' => count($dunnings),
                'last_dunning_date' => $lastDunningDate,
            ];
        }

        // Sort: höchste Mahnstufe zuerst, dann am längsten überfällig
        usort($rows, static function (array $a, array $b): int {
            $cmp = (int)($b['dunning_level'] ?? 0) <=> (int)($a['dunning_level'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int)($b['days_overdue'] ?? 0) <=> (int)($a['days_overdue'] ?? 0);
        });

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
            $text = $service->composeEmailText($handover);

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

    private function extractContactName(array $contact): string
    {
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

        $name = trim((string)($contact['name'] ?? ''));
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
}
