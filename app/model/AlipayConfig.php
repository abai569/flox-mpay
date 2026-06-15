<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

class AlipayConfig extends Model
{
    protected $name = 'alipay_config';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    private static function ensureTable(): void
    {
        try {
            $row = self::find(1);
            if ($row) return;
        } catch (\Throwable $e) {
        }

        $db = \think\facade\Db::getConnection();
        $prefix = $db->getConfig('prefix');
        $table = $prefix . 'alipay_config';

        try {
            $db->execute("CREATE TABLE IF NOT EXISTS `$table` (
                id INTEGER PRIMARY KEY,
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
        self::ensureTable();
        try {
            $row = self::find(1);
        } catch (\Throwable $e) {
            return [];
        }
        if (!$row) return [];
        return [
            'app_id'             => $row->app_id ?? '',
            'private_key'        => $row->private_key ?? '',
            'alipay_public_key'  => $row->alipay_public_key ?? '',
            'transfer_user_id'   => $row->transfer_user_id ?? '',
            'bill_query'         => [
                'query_minutes_back' => (int)($row->query_minutes_back ?? 30),
                'page_size'        => 200,
            ],
        ];
    }

    public static function saveConfig(array $data): bool
    {
        self::ensureTable();
        try {
            $row = self::find(1);
        } catch (\Throwable $e) {
            return false;
        }
        if (!$row) {
            $row = new self();
            $row->id = 1;
        }
        $row->app_id            = $data['app_id'] ?? '';
        $row->private_key       = $data['private_key'] ?? '';
        $row->alipay_public_key = $data['alipay_public_key'] ?? '';
        $row->transfer_user_id  = $data['transfer_user_id'] ?? '';
        $row->query_minutes_back = (int)($data['query_minutes_back'] ?? 30);
        return $row->save();
    }
}
