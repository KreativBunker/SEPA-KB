<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class AuditLogRepository
{
    public function add(int $userId, string $action, string $objectType, ?int $objectId = null, array $meta = []): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('INSERT INTO audit_logs (user_id, action, object_type, object_id, meta_json) VALUES (:u,:a,:t,:oid,:m)');
        $st->execute([
            'u' => $userId,
            'a' => $action,
            't' => $objectType,
            'oid' => $objectId,
            'm' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
