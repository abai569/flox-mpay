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
     * 不传金额、不传备注，由用户手动填写，避免支付宝风控拦截
     *
     * @return string alipays:// 链接
     */
    public function generateTransferLink(float $amount = 0): string
    {
        $userId = $this->config['transfer_user_id'] ?? '';
        if (empty($userId)) {
            throw new \RuntimeException('未配置支付宝用户ID (transfer_user_id)');
        }

        $params = [
            'appId'      => '20000116',
            'actionType' => 'toAccount',
            'goBack'     => 'NO',
            'userId'     => $userId,
        ];

        return 'alipays://platformapi/startapp?' . http_build_query($params);
    }
}
