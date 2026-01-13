<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Services\Db;

final class ExportRunRepository
{
    public function all(): array
    {
        $pdo = Db::pdo();
        $this->ensurePainVersionSupports((string)($data['pain_version'] ?? ''));
        return $pdo->query('SELECT er.*, u.email AS created_by_email FROM export_runs er JOIN users u ON u.id = er.created_by_user_id ORDER BY er.id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('SELECT er.*, u.email AS created_by_email FROM export_runs er JOIN users u ON u.id = er.created_by_user_id WHERE er.id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Db::pdo();
        $sql = 'INSERT INTO export_runs
            (title,collection_date,pain_version,batch_booking,scheme_default,endtoend_strategy,remittance_template,status,total_count,total_sum,created_by_user_id)
            VALUES
            (:title,:collection_date,:pain_version,:batch_booking,:scheme_default,:endtoend_strategy,:remittance_template,:status,0,0,:created_by_user_id)';
        $st = $pdo->prepare($sql);
        $st->execute($data);
        return (int)$pdo->lastInsertId();
    }

    public function updateTotals(int $id, int $count, float $sum, string $status, ?string $validatedAt = null): void
    {
        $pdo = Db::pdo();
        $sql = 'UPDATE export_runs SET total_count = :c, total_sum = :s, status = :st, validated_at = ' . ($validatedAt ? 'NOW()' : 'validated_at') . ' WHERE id = :id';
        $st = $pdo->prepare($sql);
        $st->execute(['c' => $count, 's' => number_format($sum,2,'.',''), 'st' => $status, 'id' => $id]);
    }

    public function markExported(int $id, string $filePath, string $hash): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE export_runs SET status = "exported", file_path = :p, file_hash = :h, exported_at = NOW() WHERE id = :id');
        $st->execute(['p' => $filePath, 'h' => $hash, 'id' => $id]);
    }

    public function finalize(int $id): void
    {
        $pdo = Db::pdo();
        $st = $pdo->prepare('UPDATE export_runs SET status = "final", finalized_at = NOW() WHERE id = :id');
        $st->execute(['id' => $id]);
    }


private function ensurePainVersionSupports(string $version): void
{
    $pdo = Db::pdo();

    try {
        $st = $pdo->query("SHOW COLUMNS FROM export_runs LIKE 'pain_version'");
        $col = $st ? $st->fetch() : null;
        $type = strtolower((string)($col['Type'] ?? ''));

        if ($type === '') {
            return;
        }

        if (str_contains($type, 'enum(')) {
            if (!str_contains($type, "'" . strtolower($version) . "'") && !str_contains($type, "'" . $version . "'")) {
                // Standard Werte sicherstellen
                $values = ["pain.008.001.02", "pain.008.001.08"];
                $valsSql = "'" . implode("','", $values) . "'";
                $def = in_array($version, $values, true) ? $version : "pain.008.001.08";
                $pdo->exec("ALTER TABLE export_runs MODIFY pain_version ENUM($valsSql) NOT NULL DEFAULT '$def'");
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }
}
}
