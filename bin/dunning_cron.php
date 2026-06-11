<?php
declare(strict_types=1);

/**
 * CLI-Einstiegspunkt für den automatischen Mahnlauf.
 *
 * Aufruf per Hosting-Crontab (einmal täglich genügt):
 *   15 7 * * * php /pfad/zu/SEPA-KB/bin/dunning_cron.php >> /pfad/zu/SEPA-KB/storage/logs/dunning_cron.log 2>&1
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
use App\Support\Logger;

App::init($basePath);

if (!App::isInstalled()) {
    fwrite(STDERR, "SEPA-KB ist nicht installiert (config/installed.lock fehlt).\n");
    exit(1);
}

try {
    $service = new DunningService(new SevdeskClient(new SevdeskAccountRepository()));
    $result = $service->runCron('cron');

    echo '[' . date('Y-m-d H:i:s') . '] ';
    if (empty($result['ran'])) {
        echo 'SKIPPED ' . (string)($result['log'] ?? '') . "\n";
        exit(0);
    }

    echo 'OK mode=' . (string)$result['mode']
        . ' candidates=' . (int)$result['candidates']
        . ' queued=' . (int)$result['queued']
        . ' sent=' . (int)$result['sent']
        . ' skipped=' . (int)$result['skipped']
        . ' errors=' . (int)$result['errors'] . "\n";
    if (!empty($result['log'])) {
        echo $result['log'] . "\n";
    }

    exit ((int)$result['errors'] > 0 ? 1 : 0);
} catch (\Throwable $e) {
    Logger::error('Mahnwesen: CLI-Cron-Lauf fehlgeschlagen', $e);
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR ' . $e->getMessage() . "\n");
    exit(1);
}
