<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class SevdeskAccountRepository
{
    public function getActive(): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT * FROM sevdesk_accounts WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    public function upsertDefault(array $data): void
    {
        $pdo = Db::pdo();
        $exists = $pdo->prepare('SELECT id FROM sevdesk_accounts WHERE label = :label LIMIT 1');
        $exists->execute(['label' => 'default']);
        $row = $exists->fetch();
        if ($row) {
            $sql = 'UPDATE sevdesk_accounts SET api_token_encrypted = :api_token_encrypted, header_mode = :header_mode, base_url = :base_url, updated_at = NOW() WHERE label = :label';
            $st = $pdo->prepare($sql);
            $st->execute([
                'api_token_encrypted' => $data['api_token_encrypted'],
                'header_mode' => $data['header_mode'],
                'base_url' => $data['base_url'],
                'label' => 'default',
            ]);
            return;
        }

        $sql = 'INSERT INTO sevdesk_accounts (label, api_token_encrypted, header_mode, base_url, is_active) VALUES (:label,:api_token_encrypted,:header_mode,:base_url,1)';
        $st = $pdo->prepare($sql);
        $st->execute([
            'label' => 'default',
            'api_token_encrypted' => $data['api_token_encrypted'],
            'header_mode' => $data['header_mode'],
            'base_url' => $data['base_url'],
        ]);
    }
}
