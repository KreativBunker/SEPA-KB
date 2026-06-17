<?php
declare(strict_types=1);

/**
 * Diagnose-Hilfe für die Mahnautomatik: prüft für eine konkrete Rechnung,
 * wie sie in sevdesk vorliegt und ob sie über eine Stornorechnung (SR)
 * als erledigt erkannt wird. Dieselbe Prüfung steht auch in der Weboberfläche
 * unter „Mahnautomatik“ → „Storno-Prüfung einer Rechnung“ zur Verfügung.
 *
 * Aufruf (rein lesend, nur GET-Requests an sevdesk):
 *   php bin/dunning_diagnose.php RE-2026/252406
 *   php bin/dunning_diagnose.php 12345          (sevdesk-Invoice-ID)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Nur per CLI ausführbar.';
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

use App\Repositories\SevdeskAccountRepository;
use App\Services\DunningService;
use App\Services\SevdeskClient;
use App\Support\App;

App::init($basePath);

if (!App::isInstalled()) {
    fwrite(STDERR, "SEPA-KB ist nicht installiert (config/installed.lock fehlt).\n");
    exit(1);
}

$needle = trim((string)($argv[1] ?? ''));
if ($needle === '') {
    fwrite(STDERR, "Usage: php bin/dunning_diagnose.php <Rechnungsnummer|InvoiceID>\n");
    exit(1);
}

$service = new DunningService(new SevdeskClient(new SevdeskAccountRepository()));
$d = $service->diagnoseInvoice($needle);

if (!$d['found']) {
    fwrite(STDERR, "Rechnung '" . $needle . "' wurde in sevdesk nicht gefunden.\n");
    exit(1);
}

$inv = $d['invoice'];
echo "=== Rechnung ===\n";
echo 'ID:           ' . (int)$inv['id'] . "\n";
echo 'Nummer:       ' . (string)$inv['invoiceNumber'] . "\n";
echo 'invoiceType:  ' . (string)$inv['invoiceType'] . "\n";
echo 'status:       ' . (string)$inv['status'] . "  (200 = offen, 1000 = bezahlt)\n";
echo 'payDate:      ' . var_export($inv['payDate'], true) . "\n";
echo 'Betrag:       ' . $inv['amount'] . "\n";
echo 'dueDate:      ' . (string)$inv['dueDate'] . "\n";

echo "\n=== Stornorechnungen (invoiceType=SR) ===\n";
echo 'SR-Belege gesamt: ' . (int)$d['sr_total'] . "\n";
if (!empty($d['matched'])) {
    echo 'TREFFER: ' . count($d['matched']) . " Stornorechnung(en) verweisen per origin auf diese Rechnung:\n";
    foreach ($d['matched'] as $m) {
        echo '  - SR-ID ' . (int)$m['id'] . ', Nr. ' . ((string)$m['invoiceNumber'] ?: '-')
            . ', Datum ' . ((string)$m['invoiceDate'] ?: '-') . "\n";
    }
} else {
    echo "KEIN SR-Beleg verweist per 'origin' auf diese Rechnung.\n";
    if (!empty($d['sample_sr_fields'])) {
        echo "Falls die Rechnung dennoch storniert ist, ist die Verknüpfung anders gespeichert.\n";
        echo "Felder einer Beispiel-Stornorechnung:\n";
        foreach ($d['sample_sr_fields'] as $k => $v) {
            echo '  ' . $k . ' = ' . var_export($v, true) . "\n";
        }
    }
}

echo "\n=== Erkennung durch Mahnautomatik ===\n";
echo 'Als storniert erkannt: ' . ($d['recognized'] ? 'JA (wird nicht mehr gemahnt)' : 'NEIN (würde weiter gemahnt)') . "\n";

exit(0);
