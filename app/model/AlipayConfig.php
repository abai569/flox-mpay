<?php

declare(strict_types=1);

namespace app\model;

class AlipayConfig
{
    private static function getPdo(): \PDO
    {
        $db = \think\facade\Db::getConnection();
        $pdo = $db->getPdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private static function getTable(): string
    {
        $db = \think\facade\Db::getConnection();
        $prefix = $db->getConfig('prefix');
        return $prefix . 'alipay_config';
    }

    private static function ensureTable(\PDO $pdo, string $table): void
    {
        try {
            $pdo->query("SELECT id FROM `$table` LIMIT 1");
            return;
        } catch (\Throwable $e) {
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                app_id TEXT NOT NULL DEFAULT '',
                private_key TEXT NOT NULL DEFAULT '',
                alipay_public_key TEXT NOT NULL DEFAULT '',
                transfer_user_id TEXT NOT NULL DEFAULT '',
                query_minutes_back INTEGER NOT NULL DEFAULT 30
            )");
            $pdo->exec("INSERT INTO `$table` (id) VALUES (1)");
        } catch (\Throwable $e) {
        }
    }

    public static function getConfig(): array
    {
        try {
            $pdo = self::getPdo();
            $table = self::getTable();
            self::ensureTable($pdo, $table);
            $stmt = $pdo->query("SELECT * FROM `$table` WHERE id = 1");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return self::defaults();
        }

        if (!$row) return self::defaults();

        return [
            'app_id'             => $row['app_id'] ?? '',
            'private_key'        => $row['private_key'] ?? '',
            'alipay_public_key'  => $row['alipay_public_key'] ?? '',
            'transfer_user_id'   => $row['transfer_user_id'] ?? '',
            'bill_query'         => [
                'query_minutes_back' => (int)($row['query_minutes_back'] ?? 30),
                'page_size'        => 200,
            ],
        ];
    }

    public static function saveConfig(array $data): bool
    {
        try {
            $pdo = self::getPdo();
            $table = self::getTable();
            self::ensureTable($pdo, $table);

            $stmt = $pdo->prepare("UPDATE `$table` SET app_id = ?, private_key = ?, alipay_public_key = ?, transfer_user_id = ?, query_minutes_back = ? WHERE id = 1");
            return $stmt->execute([
                $data['app_id'] ?? '',
                $data['private_key'] ?? '',
                $data['alipay_public_key'] ?? '',
                $data['transfer_user_id'] ?? '',
                (int)($data['query_minutes_back'] ?? 30),
            ]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function defaults(): array
    {
        return [
            'app_id'             => '',
            'private_key'        => '',
            'alipay_public_key'  => '',
            'transfer_user_id'   => '',
            'bill_query'         => [
                'query_minutes_back' => 30,
                'page_size'        => 200,
            ],
        ];
    }
}
