<?php

namespace alipay;

class AlipayClient
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadConfig();
    }

    private function loadConfig(): array
    {
        $configPath = root_path() . 'config/alipay.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('支付宝配置文件不存在: ' . $configPath);
        }
        return require $configPath;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function validateConfig(): bool
    {
        $required = ['app_id', 'private_key', 'alipay_public_key'];
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 生成RSA2签名
     */
    public function sign(string $data): string
    {
        $privateKey = $this->formatKey($this->config['private_key'], true);
        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 验证RSA2签名
     */
    public function verify(string $data, string $signature): bool
    {
        $publicKey = $this->formatKey($this->config['alipay_public_key'], false);
        $result = openssl_verify($data, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * 格式化密钥（确保PEM格式）
     */
    private function formatKey(string $key, bool $isPrivate = false): string
    {
        $key = trim($key);
        if (strpos($key, '-----') === false) {
            $type = $isPrivate ? 'PRIVATE' : 'PUBLIC';
            $key = "-----BEGIN {$type} KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END {$type} KEY-----";
        }
        return $key;
    }

    /**
     * 执行支付宝API请求
     */
    public function execute(string $method, array $bizContent = []): array
    {
        $params = [
            'app_id'     => $this->config['app_id'],
            'method'     => $method,
            'format'     => 'JSON',
            'charset'    => $this->config['charset'] ?? 'UTF-8',
            'sign_type'  => $this->config['sign_type'] ?? 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        // 生成签名
        $signStr = $this->buildSignString($params);
        $params['sign'] = $this->sign($signStr);

        // 发送请求
        $url = $this->config['server_url'] . '/gateway.do';
        $response = $this->httpPost($url, $params);

        if (!$response) {
            throw new \RuntimeException('支付宝API请求失败');
        }

        $result = json_decode($response, true);
        if (!$result) {
            throw new \RuntimeException('支付宝API响应解析失败');
        }

        // 获取响应内容
        $responseKey = str_replace('.', '_', $method) . '_response';
        if (!isset($result[$responseKey])) {
            throw new \RuntimeException('支付宝API响应格式错误: ' . json_encode($result));
        }

        $data = $result[$responseKey];

        // 验证签名
        if (isset($result['sign'])) {
            $verifyStr = $this->buildSignString($data);
            if (!$this->verify($verifyStr, $result['sign'])) {
                throw new \RuntimeException('支付宝API响应签名验证失败');
            }
        }

        return $data;
    }

    /**
     * 构建签名字符串
     */
    private function buildSignString(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($key !== 'sign' && $value !== '' && $value !== null) {
                $parts[] = $key . '=' . $value;
            }
        }
        return implode('&', $parts);
    }

    /**
     * HTTP POST请求
     */
    private function httpPost(string $url, array $data): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('cURL请求失败: ' . $error);
        }

        return $response;
    }
}
