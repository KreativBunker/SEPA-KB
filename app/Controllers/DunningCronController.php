<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SettingsRepository;
use App\Repositories\SevdeskAccountRepository;
use App\Services\DunningService;
use App\Services\SevdeskClient;
use App\Support\Logger;

/**
 * Webcron-Endpoint für den automatischen Mahnlauf. Öffentlich erreichbar,
 * aber durch das in den Einstellungen hinterlegte Token geschützt
 * (Muster der Public-Routen /m/{token}).
 */
final class DunningCronController
{
    public function run(array $params = []): void
    {
        header('Content-Type: text/plain; charset=utf-8');

        $token = trim((string)($params['token'] ?? ''));
        $expected = trim((string)((new SettingsRepository())->get()['dunning_cron_token'] ?? ''));
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        try {
            $service = new DunningService(new SevdeskClient(new SevdeskAccountRepository()));
            $result = $service->runCron('web');

            if (empty($result['ran'])) {
                echo 'SKIPPED ' . (string)($result['log'] ?? '');
                exit;
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
        } catch (\Throwable $e) {
            Logger::error('Mahnwesen: Webcron-Lauf fehlgeschlagen', $e);
            http_response_code(500);
            echo 'ERROR ' . $e->getMessage();
        }
        exit;
    }
}
