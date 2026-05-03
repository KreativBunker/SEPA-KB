<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ExportRunRepository;
use App\Repositories\MandateRepository;
use App\Repositories\OnlineMandateRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class DashboardController
{
    public function index(): void
    {
        $exportRuns = (new ExportRunRepository())->all();
        $latestExports = array_slice($exportRuns, 0, 5);
        $latest = $exportRuns[0] ?? null;

        $mandates = (new MandateRepository())->all('');
        $mandateStats = ['active' => 0, 'paused' => 0, 'revoked' => 0, 'total' => count($mandates)];
        foreach ($mandates as $m) {
            $status = (string)($m['status'] ?? '');
            if (isset($mandateStats[$status])) {
                $mandateStats[$status]++;
            }
        }

        $openOnlineMandates = 0;
        try {
            foreach ((new OnlineMandateRepository())->all() as $om) {
                if ((string)($om['status'] ?? '') === 'open') {
                    $openOnlineMandates++;
                }
            }
        } catch (\Throwable $e) {
            // Tabelle existiert evtl. noch nicht, Wert bleibt 0
        }

        $invoicesCache = $_SESSION['invoices_cache'] ?? [];
        $invoicesCount = is_array($invoicesCache) ? count($invoicesCache) : 0;
        $invoicesSum = 0.0;
        if (is_array($invoicesCache)) {
            foreach ($invoicesCache as $r) {
                $invoicesSum += (float)($r['sumGross'] ?? 0);
            }
        }

        $selectedIds = $_SESSION['selected_invoice_ids'] ?? [];
        $selectedCount = is_array($selectedIds) ? count($selectedIds) : 0;

        $contactsCache = $_SESSION['sevdesk_contacts_cache'] ?? [];
        $contactsCount = is_array($contactsCache) ? count($contactsCache) : 0;
        $contactsWithIban = 0;
        if (is_array($contactsCache)) {
            foreach ($contactsCache as $c) {
                if (is_array($c) && trim((string)($c['bankAccount'] ?? '')) !== '') {
                    $contactsWithIban++;
                }
            }
        }

        $sevdeskConfigured = (new SevdeskAccountRepository())->getActive() !== null;

        $invoicesWithoutMandate = 0;
        if (is_array($invoicesCache)) {
            foreach ($invoicesCache as $r) {
                if (empty($r['mandate_ok'])) {
                    $invoicesWithoutMandate++;
                }
            }
        }

        $warnings = [];
        if (!$sevdeskConfigured) {
            $warnings[] = ['type' => 'error', 'text' => 'sevdesk-Token ist nicht konfiguriert.', 'href' => '/sevdesk'];
        }
        if ($invoicesWithoutMandate > 0) {
            $warnings[] = ['type' => 'warn', 'text' => $invoicesWithoutMandate . ' geladene Rechnungen haben kein aktives Mandat.', 'href' => '/invoices'];
        }
        if ($openOnlineMandates > 0) {
            $warnings[] = ['type' => 'info', 'text' => $openOnlineMandates . ' offene Online-Mandate warten auf Unterschrift.', 'href' => '/online-mandates'];
        }

        View::render('dashboard', [
            'latest' => $latest,
            'latestExports' => $latestExports,
            'mandateStats' => $mandateStats,
            'openOnlineMandates' => $openOnlineMandates,
            'invoicesCount' => $invoicesCount,
            'invoicesSum' => $invoicesSum,
            'selectedCount' => $selectedCount,
            'contactsCount' => $contactsCount,
            'contactsWithIban' => $contactsWithIban,
            'sevdeskConfigured' => $sevdeskConfigured,
            'warnings' => $warnings,
            'role' => Auth::role(),
            'csrf' => Csrf::token(),
            'messages' => Flash::all(),
        ]);
    }
}
