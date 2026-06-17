<?php
declare(strict_types=1);

/**
 * Diagnose-Hilfe für die Mahnautomatik: prüft für eine konkrete Rechnung,
 * wie sie in sevdesk vorliegt und ob sie über eine Stornorechnung (SR)
 * als erledigt erkannt wird.
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
use App\Services\InkassoService;
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

$client = new SevdeskClient(new SevdeskAccountRepository());

// 1. Rechnung anhand Nummer oder ID finden
$target = null;
if (ctype_digit($needle)) {
    $res = $client->getInvoice((int)$needle, 'contact');
    $obj = $res['objects'] ?? $res;
    if (is_array($obj) && isset($obj[0])) {
        $obj = $obj[0];
    }
    if (is_array($obj) && (int)($obj['id'] ?? 0) > 0) {
        $target = $obj;
    }
}
if ($target === null) {
    $limit = 200;
    $offset = 0;
    for ($page = 0; $page < 30; $page++) {
        $res = $client->getInvoices($limit, $offset, 'contact');
        $objs = $res['objects'] ?? [];
        if (!is_array($objs) || empty($objs)) {
            break;
        }
        foreach ($objs as $inv) {
            if (is_array($inv) && (string)($inv['invoiceNumber'] ?? '') === $needle) {
                $target = $inv;
                break 2;
            }
        }
        if (count($objs) < $limit) {
            break;
        }
        $offset += $limit;
    }
}

if ($target === null) {
    fwrite(STDERR, "Rechnung '" . $needle . "' wurde in sevdesk nicht gefunden.\n");
    exit(1);
}

$invoiceId = (int)$target['id'];
echo "=== Rechnung ===\n";
echo 'ID:           ' . $invoiceId . "\n";
echo 'Nummer:       ' . (string)($target['invoiceNumber'] ?? '') . "\n";
echo 'invoiceType:  ' . (string)($target['invoiceType'] ?? '') . "\n";
echo 'status:       ' . (string)($target['status'] ?? '') . "\n";
echo 'payDate:      ' . var_export($target['payDate'] ?? null, true) . "\n";
echo 'sumGross:     ' . var_export($target['sumGross'] ?? null, true) . "\n";
echo 'Betrag (svc): ' . InkassoService::invoiceAmount($target) . "\n";
echo 'dueDate:      ' . InkassoService::dueDateOf($target) . "\n";

// 2. Alle Stornorechnungen laden und nach Verknüpfung zu dieser Rechnung suchen
echo "\n=== Stornorechnungen (invoiceType=SR) ===\n";
$matched = [];
$srTotal = 0;
$limit = 200;
$offset = 0;
for ($page = 0; $page < 20; $page++) {
    $res = $client->getCancellationInvoices($limit, $offset);
    $objs = $res['objects'] ?? [];
    if (!is_array($objs) || empty($objs)) {
        break;
    }
    foreach ($objs as $sr) {
        if (!is_array($sr)) {
            continue;
        }
        $srTotal++;
        $origin = $sr['origin'] ?? null;
        $originId = is_array($origin) ? (int)($origin['id'] ?? 0) : 0;
        if ($originId === $invoiceId) {
            $matched[] = $sr;
        }
    }
    if (count($objs) < $limit) {
        break;
    }
    $offset += $limit;
}
echo 'SR-Belege gesamt: ' . $srTotal . "\n";

if (!empty($matched)) {
    echo "TREFFER: " . count($matched) . " Stornorechnung(en) verweisen per origin auf diese Rechnung:\n";
    foreach ($matched as $sr) {
        echo '  - SR-ID ' . (int)($sr['id'] ?? 0) . ', Nr. ' . (string)($sr['invoiceNumber'] ?? '-')
            . ', Datum ' . (string)($sr['invoiceDate'] ?? '-') . "\n";
    }
} else {
    echo "KEIN SR-Beleg verweist per 'origin' auf diese Rechnung.\n";
    echo "Falls die Rechnung dennoch storniert ist, ist die Verknüpfung anders gespeichert.\n";
    echo "Zur Analyse die Feldnamen einer beliebigen Stornorechnung ausgeben:\n\n";
    $res = $client->getCancellationInvoices(1, 0);
    $objs = $res['objects'] ?? [];
    if (is_array($objs) && isset($objs[0]) && is_array($objs[0])) {
        echo "Beispiel-SR – verfügbare Felder:\n";
        foreach ($objs[0] as $k => $v) {
            $printable = is_scalar($v) || $v === null ? var_export($v, true) : '(' . gettype($v) . ')';
            echo '  ' . $k . ' = ' . $printable . "\n";
        }
    } else {
        echo "(keine Stornorechnung zum Inspizieren vorhanden)\n";
    }
}

// 3. Ergebnis der eigentlichen Erkennungslogik
$service = new DunningService($client);
$cancelled = $service->cancelledOriginIds();
echo "\n=== Erkennung durch Mahnautomatik ===\n";
echo 'Als storniert erkannt: ' . (isset($cancelled[$invoiceId]) ? 'JA (wird nicht mehr gemahnt)' : 'NEIN (würde weiter gemahnt)') . "\n";

exit(0);
