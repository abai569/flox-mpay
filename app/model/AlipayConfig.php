<?php

declare(strict_types=1);

namespace app\model;

class AlipayConfig
{
    private static function getDb()
    {
        return \think\facade\Db::connect();
    }

    private static function getTable(): string
    {
        $default = (string)config('database.default');
        $prefix = (string)config('database.connections.' . $default . '.prefix');
        return $prefix . 'alipay_config';
    }

    private static function ensureTable(): void
    {
        $db = self::getDb();
        $table = self::getTable();

        try {
            $db->query("SELECT id FROM `$table` LIMIT 1");
            return;
        } catch (\Throwable $e) {
        }

        try {
            $db->execute("CREATE TABLE IF NOT EXISTS `$table` (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                app_id TEXT NOT NULL DEFAULT '',
                private_key TEXT NOT NULL DEFAULT '',
                alipay_public_key TEXT NOT NULL DEFAULT '',
                transfer_user_id TEXT NOT NULL DEFAULT '',
                query_minutes_back INTEGER NOT NULL DEFAULT 30
            )");
            $db->execute("INSERT INTO `$table` (id) VALUES (1)");
        } catch (\Throwable $e) {
        }
    }

    public static function getConfig(): array
    {
        try {
            self::ensureTable();
            $rows = self::getDb()->query("SELECT * FROM `" . self::getTable() . "` WHERE id = 1");
            $row = $rows[0] ?? null;
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
            self::ensureTable();
            $table = self::getTable();
            $db = self::getDb();
            $sql = "REPLACE INTO `$table` (id, app_id, private_key, alipay_public_key, transfer_user_id, query_minutes_back) VALUES (1, ?, ?, ?, ?, ?)";
            $db->execute($sql, [
                $data['app_id'] ?? '',
                $data['private_key'] ?? '',
                $data['alipay_public_key'] ?? '',
                $data['transfer_user_id'] ?? '',
                (int)($data['query_minutes_back'] ?? 30),
            ]);
            return true;
        } catch (\Throwable $e) {
            $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . 'alipay_config_error.log';
            @file_put_contents($logFile, date('c') . ' ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
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
