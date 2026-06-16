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
     * 生成防风控转账链接（alipays:// 协议唤起支付宝APP）
     *
     * @param float  $amount  转账金额
     * @param string $memo    转账备注（订单号）
     * @return string alipays:// 链接
     */
    public function generateTransferLink(float $amount, string $memo): string
    {
        $userId = $this->config['transfer_user_id'] ?? '';
        if (empty($userId)) {
            throw new \RuntimeException('未配置支付宝用户ID (transfer_user_id)');
        }

        $params = [
            'appId'      => '20000116',
            'actionType' => 'toAccount',
            'goBack'     => 'NO',
            'amount'     => number_format($amount, 2, '.', ''),
            'userId'     => $userId,
        ];

        return 'alipays://platformapi/startapp?' . http_build_query($params);
    }

    /**
     * 生成 Android intent:// 唤起链接，绕过系统 scheme 缓存
     */
    public function generateIntentUrl(float $amount, string $memo): string
    {
        $alipaysUrl = $this->generateTransferLink($amount, $memo);
        $intentUrl = preg_replace('/^alipays:/', 'intent:', $alipaysUrl);
        return $intentUrl . '#Intent;scheme=alipays;package=com.eg.android.AlipayGphone;end;';
    }
}
