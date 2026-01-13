<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SevdeskAccountRepository;
use App\Services\CryptoService;
use App\Services\SevdeskClient;
use App\Support\App;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\View;

final class SevdeskController
{
    public function edit(): void
    {
        $acc = (new SevdeskAccountRepository())->getActive();

        View::render('sevdesk', [
            'csrf' => Csrf::token(),
            'account' => $acc,
            'messages' => Flash::all(),
        ]);
    }

    public function update(): void
    {
        Csrf::check();

        $token = trim((string)($_POST['api_token'] ?? ''));
        $headerMode = ($_POST['header_mode'] ?? 'Authorization') === 'X-Authorization' ? 'X-Authorization' : 'Authorization';
        $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? 'https://my.sevdesk.de/api/v1')), '/');

        if ($token === '' || $baseUrl === '') {
            Flash::add('error', 'Bitte Token und Base URL angeben.');
            header('Location: ' . App::url('/sevdesk'));
            exit;
        }

        $crypto = new CryptoService();
        $encrypted = $crypto->encrypt($token);

        (new SevdeskAccountRepository())->upsertDefault([
            'api_token_encrypted' => $encrypted,
            'header_mode' => $headerMode,
            'base_url' => $baseUrl,
        ]);

        Flash::add('success', 'sevdesk gespeichert.');
        header('Location: ' . App::url('/sevdesk'));
        exit;
    }

    public function test(): void
    {
        Csrf::check();

        try {
            $client = new SevdeskClient(new SevdeskAccountRepository());
            $res = $client->test();
            $count = is_array($res['objects'] ?? null) ? count($res['objects']) : 0;
            Flash::add('success', 'Verbindung ok, Antwort enthält ' . $count . ' Datensatz oder weniger.');
        } catch (\Throwable $e) {
            Flash::add('error', 'Test fehlgeschlagen: ' . $e->getMessage());
        }

        header('Location: ' . App::url('/sevdesk'));
        exit;
    }
}
