<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InstallmentPlanRepository;
use App\Repositories\InstallmentRateRepository;
use App\Repositories\MandateRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\InstallmentService;
use App\Services\SevdeskClient;
use App\Services\ValidationService;
use App\Support\App;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class InstallmentsController
{
    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $statusFilter = (string)($_GET['status'] ?? '');
        if (!in_array($statusFilter, ['active', 'completed', 'cancelled'], true)) {
            $statusFilter = '';
        }

        $plans = (new InstallmentPlanRepository())->all($q, $statusFilter);

        $settings = (new SettingsRepository())->get();
        $days = (int)($settings['default_days_until_collection'] ?? 5);

        // fällige Raten zählen (Stichtag heute)
        $due = (new InstallmentRateRepository())->dueRates(date('Y-m-d'));

        View::render('installments/index', [
            'plans' => $plans,
            'dueCount' => count($due),
            'q' => $q,
            'statusFilter' => $statusFilter,
            'defaultCollectionDate' => date('Y-m-d', time() + ($days * 86400)),
            'csrf' => Csrf::token(),
            'messages' => Flash::all(),
        ]);
    }

    public function create(): void
    {
        $settings = (new SettingsRepository())->get();

        // offene Rechnungen aus dem /invoices-Cache anbieten (sofern geladen)
        $invoices = $_SESSION['invoices_cache'] ?? [];
        if (!is_array($invoices)) {
            $invoices = [];
        }
        // bereits abgeschlossene ausblenden
        $invoices = array_values(array_filter($invoices, static fn($r): bool => empty($r['completed'])));

        $mandates = (new MandateRepository())->all('', 'active');

        View::render('installments/create', [
            'csrf' => Csrf::token(),
            'invoices' => $invoices,
            'mandates' => $mandates,
            'settings' => $settings,
            'defaultRates' => (int)($settings['installment_default_rates'] ?? 3),
            'defaultRemittance' => (string)($settings['installment_remittance_template'] ?? 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}'),
            'defaultFirstDate' => date('Y-m-d', time() + ((int)($settings['default_days_until_collection'] ?? 5) * 86400)),
            'messages' => Flash::all(),
        ]);
    }

    public function store(): void
    {
        Csrf::check();

        $settings = (new SettingsRepository())->get();
        $planRepo = new InstallmentPlanRepository();
        $rateRepo = new InstallmentRateRepository();
        $mandateRepo = new MandateRepository();
        $val = new ValidationService();
        $service = new InstallmentService();

        $source = ($_POST['source'] ?? 'invoice') === 'manual' ? 'manual' : 'invoice';
        $rateCount = max(1, (int)($_POST['rate_count'] ?? 1));
        $intervalMonths = max(1, (int)($_POST['interval_months'] ?? 1));
        $firstDate = trim((string)($_POST['first_collection_date'] ?? ''));
        $remTemplate = trim((string)($_POST['remittance_template'] ?? 'Rechnung {invoice_number} Rate {rate_no}/{rate_count}'));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $seqMode = (string)($settings['installment_seq_mode'] ?? 'rcur_only');

        if ($firstDate === '') {
            Flash::add('error', 'Bitte erstes Einzugsdatum setzen.');
            header('Location: ' . App::url('/installments/create'));
            exit;
        }

        $plan = [
            'source' => $source,
            'rate_count' => $rateCount,
            'interval_months' => $intervalMonths,
            'first_collection_date' => $firstDate,
            'remittance_template' => $remTemplate,
            'notes' => $notes !== '' ? $notes : null,
            'created_by_user_id' => (int)(Auth::user()['id'] ?? 0),
            'status' => 'active',
        ];

        if ($source === 'invoice') {
            $invoiceId = (int)($_POST['sevdesk_invoice_id'] ?? 0);
            if ($invoiceId <= 0) {
                Flash::add('error', 'Bitte eine Rechnung wählen.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }
            if ($planRepo->hasActivePlan($invoiceId)) {
                Flash::add('error', 'Für diese Rechnung existiert bereits ein aktiver Ratenplan.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }

            try {
                $client = new SevdeskClient(new SevdeskAccountRepository());
                $invRes = $client->getInvoice($invoiceId, 'contact');
                $invObj = $invRes['objects'] ?? $invRes;
                if (is_array($invObj) && isset($invObj[0])) {
                    $invObj = $invObj[0];
                }
            } catch (\Throwable $e) {
                $invObj = null;
            }
            if (!is_array($invObj)) {
                Flash::add('error', 'Rechnung konnte nicht aus sevdesk geladen werden.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }

            $invoiceNumber = (string)($invObj['invoiceNumber'] ?? $invObj['number'] ?? $invoiceId);
            $total = (float)($invObj['sumGross'] ?? $invObj['sumGrossAccounting'] ?? $invObj['sumNet'] ?? $invObj['sum'] ?? 0.0);

            $contact = $invObj['contact'] ?? null;
            $contactId = is_array($contact) ? (int)($contact['id'] ?? 0) : 0;

            $mandate = $contactId > 0 ? $mandateRepo->findByContactId($contactId) : null;
            if (!$mandate || ($mandate['status'] ?? '') !== 'active') {
                Flash::add('error', 'Kein aktives SEPA-Mandat für den Kontakt dieser Rechnung vorhanden.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }

            $plan['sevdesk_invoice_id'] = $invoiceId;
            $plan['invoice_number'] = $invoiceNumber;
            $plan['sevdesk_contact_id'] = $contactId;
            $plan['total_amount'] = $total;
            $plan['mandate_id'] = (int)$mandate['id'];
            $plan['debtor_name'] = (string)($mandate['debtor_name'] ?? '');
            $plan['debtor_iban'] = (string)($mandate['debtor_iban'] ?? '');
            $plan['mandate_reference'] = (string)($mandate['mandate_reference'] ?? '');
            $plan['mandate_date'] = (string)($mandate['mandate_date'] ?? null);
            $plan['scheme'] = (string)($mandate['scheme'] ?? 'CORE');
        } else {
            $mandateId = (int)($_POST['mandate_id'] ?? 0);
            $total = (float)str_replace(',', '.', (string)($_POST['total_amount'] ?? '0'));
            $label = trim((string)($_POST['invoice_number'] ?? ''));

            $mandate = $mandateId > 0 ? $mandateRepo->find($mandateId) : null;
            if (!$mandate || ($mandate['status'] ?? '') !== 'active') {
                Flash::add('error', 'Bitte ein aktives Mandat wählen.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }
            if ($total <= 0.0) {
                Flash::add('error', 'Bitte einen Gesamtbetrag größer 0 eingeben.');
                header('Location: ' . App::url('/installments/create'));
                exit;
            }

            $plan['sevdesk_invoice_id'] = null;
            $plan['invoice_number'] = $label;
            $plan['sevdesk_contact_id'] = (int)($mandate['sevdesk_contact_id'] ?? 0);
            $plan['total_amount'] = $total;
            $plan['mandate_id'] = (int)$mandate['id'];
            $plan['debtor_name'] = (string)($mandate['debtor_name'] ?? '');
            $plan['debtor_iban'] = (string)($mandate['debtor_iban'] ?? '');
            $plan['mandate_reference'] = (string)($mandate['mandate_reference'] ?? '');
            $plan['mandate_date'] = (string)($mandate['mandate_date'] ?? null);
            $plan['scheme'] = (string)($mandate['scheme'] ?? 'CORE');
        }

        if (!$val->validateIban((string)$plan['debtor_iban'])) {
            Flash::add('error', 'Die IBAN des Mandats ist ungültig.');
            header('Location: ' . App::url('/installments/create'));
            exit;
        }

        try {
            $planId = $planRepo->create($plan);
            $rates = $service->buildSchedule((float)$plan['total_amount'], $rateCount, $intervalMonths, $firstDate, $seqMode);
            $rateRepo->createMany($planId, $rates);

            Flash::add('success', 'Ratenplan mit ' . count($rates) . ' Raten erstellt.');
            header('Location: ' . App::url('/installments/' . $planId));
            exit;
        } catch (\Throwable $e) {
            Flash::add('error', 'Ratenplan konnte nicht erstellt werden: ' . $e->getMessage());
            header('Location: ' . App::url('/installments/create'));
            exit;
        }
    }

    public function show(array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $plan = (new InstallmentPlanRepository())->find($id);
        if (!$plan) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }
        $rates = (new InstallmentRateRepository())->forPlan($id);

        View::render('installments/show', [
            'csrf' => Csrf::token(),
            'plan' => $plan,
            'rates' => $rates,
            'messages' => Flash::all(),
        ]);
    }

    public function cancel(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);
        $planRepo = new InstallmentPlanRepository();
        $plan = $planRepo->find($id);
        if (!$plan) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        // offene Raten stornieren, bereits eingezogene bleiben unangetastet
        $pdo = \App\Services\Db::pdo();
        $st = $pdo->prepare("UPDATE installment_rates SET status = 'cancelled', updated_at = NOW() WHERE plan_id = :pid AND status IN ('planned','queued','failed')");
        $st->execute(['pid' => $id]);

        $planRepo->setStatus($id, 'cancelled');

        Flash::add('success', 'Ratenplan storniert.');
        header('Location: ' . App::url('/installments/' . $id));
        exit;
    }

    public function markRateFailed(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);
        $rateId = (int)($params['rid'] ?? 0);
        (new InstallmentRateRepository())->markFailed($rateId, 'Manuell als fehlgeschlagen markiert (Rücklastschrift).');

        Flash::add('success', 'Rate als fehlgeschlagen markiert.');
        header('Location: ' . App::url('/installments/' . $id));
        exit;
    }

    public function resetRate(array $params): void
    {
        Csrf::check();
        $id = (int)($params['id'] ?? 0);
        $rateId = (int)($params['rid'] ?? 0);
        (new InstallmentRateRepository())->resetToPlanned($rateId);

        Flash::add('success', 'Rate wieder als offen eingeplant.');
        header('Location: ' . App::url('/installments/' . $id));
        exit;
    }

    public function queueDue(): void
    {
        Csrf::check();

        $settings = (new SettingsRepository())->get();
        $cutoff = trim((string)($_POST['cutoff_date'] ?? date('Y-m-d')));
        $collectionDate = trim((string)($_POST['collection_date'] ?? ''));
        if ($collectionDate === '') {
            $days = (int)($settings['default_days_until_collection'] ?? 5);
            $collectionDate = date('Y-m-d', time() + ($days * 86400));
        }

        $result = (new InstallmentService())->queueDueRates($cutoff, $collectionDate, $settings, (int)(Auth::user()['id'] ?? 0));

        $runs = $result['runs'];
        if (empty($runs)) {
            Flash::add('error', 'Keine fälligen Raten gefunden.');
            header('Location: ' . App::url('/installments'));
            exit;
        }

        $msg = 'Fällige Raten übernommen: ' . $result['queued'] . ' ok';
        if ($result['errors'] > 0) {
            $msg .= ', ' . $result['errors'] . ' mit Fehlern';
        }
        $msg .= '. Export-Läufe erstellt: ' . count($runs) . '.';
        Flash::add('success', $msg);

        // direkt zum ersten Lauf; weitere stehen unter /exports
        if (count($runs) === 1) {
            header('Location: ' . App::url('/exports/' . (int)$runs[0]));
        } else {
            header('Location: ' . App::url('/exports'));
        }
        exit;
    }
}
