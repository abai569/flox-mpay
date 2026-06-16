<?php

namespace alipay;

class PagePay
{
    private AlipayClient $client;

    public function __construct(AlipayClient $client)
    {
        $this->client = $client;
    }

    /**
     * 生成手机网站支付 URL（alipay.trade.wap.pay）
     * 用户浏览器打开此 URL 将跳转到支付宝安全收银台
     * 避免 alipays:// 自定义协议的风险提示和 scheme 缓存问题
     */
    public function generatePayUrl(string $outTradeNo, float $totalAmount, string $subject, string $quitUrl = '', string $returnUrl = ''): string
    {
        $bizContent = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'subject'      => $subject,
            'product_code' => 'QUICK_WAP_WAY',
        ];

        if ($quitUrl) {
            $bizContent['quit_url'] = $quitUrl;
        }

        $extraParams = [];
        if ($returnUrl) {
            $extraParams['return_url'] = $returnUrl;
        }

        $config = $this->client->getConfig();
        $params = $this->buildSignedParams('alipay.trade.wap.pay', $bizContent, $extraParams);

        return $config['server_url'] . '/gateway.do?' . http_build_query($params);
    }

    private function buildSignedParams(string $method, array $bizContent, array $extraParams = []): array
    {
        $config = $this->client->getConfig();

        $params = [
            'app_id'     => $config['app_id'],
            'method'     => $method,
            'format'     => 'JSON',
            'charset'    => $config['charset'] ?? 'UTF-8',
            'sign_type'  => $config['sign_type'] ?? 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        if (!empty($extraParams)) {
            $params = array_merge($params, $extraParams);
        }

        $signStr = $this->buildSignString($params);
        $params['sign'] = $this->client->sign($signStr);

        return $params;
    }

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
}
