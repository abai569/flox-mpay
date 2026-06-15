<?php

declare(strict_types=1);

namespace app\model;

class AlipayConfig
{
    private static function getConfigPath(): string
    {
        return runtime_path() . 'config' . DIRECTORY_SEPARATOR . 'alipay_config.json';
    }

    private static function ensureDir(): void
    {
        $dir = dirname(self::getConfigPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function getConfig(): array
    {
        $path = self::getConfigPath();
        if (!file_exists($path)) {
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
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return [];
        }
        return [
            'app_id'             => $data['app_id'] ?? '',
            'private_key'        => $data['private_key'] ?? '',
            'alipay_public_key'  => $data['alipay_public_key'] ?? '',
            'transfer_user_id'   => $data['transfer_user_id'] ?? '',
            'bill_query'         => [
                'query_minutes_back' => (int)($data['query_minutes_back'] ?? 30),
                'page_size'        => 200,
            ],
        ];
    }

    public static function saveConfig(array $data): bool
    {
        self::ensureDir();
        $config = [
            'app_id'             => $data['app_id'] ?? '',
            'private_key'        => $data['private_key'] ?? '',
            'alipay_public_key'  => $data['alipay_public_key'] ?? '',
            'transfer_user_id'   => $data['transfer_user_id'] ?? '',
            'query_minutes_back' => (int)($data['query_minutes_back'] ?? 30),
        ];
        return file_put_contents(
            self::getConfigPath(),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ) !== false;
    }
}
