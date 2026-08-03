<?php

namespace alipay;

class AlipayTransfer
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: \app\model\AlipayConfig::getConfig();
    }

    /**
     * 生成转账链接（通过 render.alipay.com 5层 HTTPS 包装，绕过 Chrome scheme 去重）
     * 走支付宝自身服务器中转后可安全预填金额，不触发风控
     *
     * @return string https:// 链接（浏览器不触发 scheme 缓存）
     */
    public function generateTransferLink(string $nonce = ''): string
    {
        $userId = $this->config['transfer_user_id'] ?? '';
        if (empty($userId)) {
            throw new \RuntimeException('未配置支付宝用户ID (transfer_user_id)');
        }

        if ($nonce === '') {
            $nonce = time() . random_int(100000, 999999);
        }

        // 第5层：内部转账页（不传金额，避免风控）
        $innerParams = [
            'appId'      => '20000116',
            'actionType' => 'toAccount',
            'goBack'     => 'NO',
            'userId'     => $userId,
            '_t'         => $nonce,
        ];
        $innerUrl = 'alipays://platformapi/startapp?' . http_build_query($innerParams);

        // 第4层 -> 第3层 -> 第2层 -> 第1层：逐层 HTTPS 包装
        $layer2 = 'https://render.alipay.com/p/s/i?scheme=' . urlencode($innerUrl);
        $layer3Params = ['appId' => '20000218', 'url' => $layer2];
        $layer3 = 'alipays://platformapi/startapp?' . http_build_query($layer3Params);
        $layer4 = 'https://render.alipay.com/p/s/i?scheme=' . urlencode($layer3);

        return 'https://render.alipay.com/p/c/mdeduct-landing?scheme=' . urlencode($layer4);
    }
}
