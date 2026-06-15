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
        $config = \app\model\AlipayConfig::getConfig();
        if (empty($config['app_id'])) {
            throw new \RuntimeException('支付宝配置未设置，请先在后台"支付宝配置"页面填写');
        }
        $config['server_url'] = 'https://openapi.alipay.com';
        $config['sign_type'] = 'RSA2';
        $config['charset'] = 'UTF-8';
        return $config;
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
        $privateKey = $this->resolvePrivateKey($this->config['private_key']);
        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 验证RSA2签名
     */
    public function verify(string $data, string $signature): bool
    {
        $publicKey = $this->resolvePublicKey($this->config['alipay_public_key']);
        $result = openssl_verify($data, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * 解析应用私钥
     */
    private function resolvePrivateKey(string $key): string|\OpenSSLAsymmetricKey
    {
        $key = $this->normalizeKey($key);
        $candidates = [$key];
        $body = $this->extractKeyBody($key);
        if ($body !== '') {
            $candidates[] = $this->wrapKey($body, 'PRIVATE');
            $candidates[] = $this->wrapKey($body, 'RSA PRIVATE');
        }
        foreach ($candidates as $candidate) {
            $res = @openssl_pkey_get_private($candidate);
            if ($res !== false) {
                return $res;
            }
        }
        throw new \RuntimeException('支付宝应用私钥格式无效');
    }

    /**
     * 解析支付宝公钥
     */
    private function resolvePublicKey(string $key): string|\OpenSSLAsymmetricKey
    {
        $key = $this->normalizeKey($key);
        $candidates = [$key];
        $body = $this->extractKeyBody($key);
        if ($body !== '') {
            $candidates[] = $this->wrapKey($body, 'PUBLIC');
            $candidates[] = $this->wrapKey($body, 'RSA PUBLIC');
        }
        foreach ($candidates as $candidate) {
            $res = @openssl_pkey_get_public($candidate);
            if ($res !== false) {
                return $res;
            }
        }
        throw new \RuntimeException('支付宝公钥格式无效');
    }

    private function normalizeKey(string $key): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $key));
    }

    private function extractKeyBody(string $key): string
    {
        $key = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/', '', $key);
        return is_string($key) ? trim($key) : '';
    }

    private function wrapKey(string $body, string $type): string
    {
        return "-----BEGIN {$type} KEY-----\n" . wordwrap($body, 64, "\n", true) . "\n-----END {$type} KEY-----";
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

        $response = $this->normalizeResponse($response);
        $result = json_decode($response, true);
        if (!$result) {
            throw new \RuntimeException('支付宝API响应解析失败: ' . mb_substr(trim((string)$response), 0, 500));
        }

        // 获取响应内容
        $responseKey = str_replace('.', '_', $method) . '_response';
        if (!isset($result[$responseKey])) {
            throw new \RuntimeException('支付宝API响应格式错误: ' . json_encode($result));
        }

        $data = $result[$responseKey];

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

    private function normalizeResponse(string $response): string
    {
        $response = trim($response);
        $response = preg_replace('/^\xEF\xBB\xBF/', '', $response) ?? $response;
        if (function_exists('mb_check_encoding') && !mb_check_encoding($response, 'UTF-8')) {
            $converted = mb_convert_encoding($response, 'UTF-8', 'GBK,GB2312,UTF-8');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return $response;
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
