<?php

namespace alipay;

class AlipayTransfer
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: (file_exists(root_path() . 'config/alipay.php') ? require root_path() . 'config/alipay.php' : []);
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

        $outerAppId = '20000218';
        $innerAppId = '20000116';
        $mdeductUrl = 'https://render.alipay.com/p/c/mdeduct-landing';
        $renderUrl = 'https://render.alipay.com/p/s/i';

        // 最内层：转账参数
        $innerParams = [
            'appId'      => $innerAppId,
            'actionType' => 'toAccount',
            'goBack'     => 'NO',
            'amount'     => number_format($amount, 2, '.', ''),
            'userId'     => $userId,
            'memo'       => $memo,
        ];
        $innerUrl = 'alipays://platformapi/startapp?' . http_build_query($innerParams);

        // 第四层
        $fourthUrl = $renderUrl . '?scheme=' . urlencode($innerUrl);

        // 第三层
        $thirdParams = ['appId' => $outerAppId, 'url' => $fourthUrl];
        $thirdUrl = 'alipays://platformapi/startapp?' . http_build_query($thirdParams);

        // 第二层
        $secondUrl = $renderUrl . '?scheme=' . urlencode($thirdUrl);

        // 最外层
        return $mdeductUrl . '?scheme=' . urlencode($secondUrl);
    }
}
