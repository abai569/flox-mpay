<?php

namespace payclient;

use alipay\AlipayClient;
use alipay\BillQuery;

class AliBill
{
    private array $payConfig;
    private AlipayClient $client;
    private BillQuery $billQuery;

    public function __construct(array $payConfig)
    {
        $this->payConfig = $payConfig;
        $this->client = new AlipayClient();
        $this->billQuery = new BillQuery($this->client);
    }

    /**
     * 获取收款记录 - mpay的checkPayResult调用此方法
     *
     * @param array $params 配置参数
     * @return array ['code' => 0, 'data' => [...]]
     */
    public function getOrderInfo(array $params = []): array
    {
        try {
            if (!$this->client->validateConfig()) {
                return ['code' => 1, 'msg' => '支付宝配置不完整，请检查 config/alipay.php'];
            }

            $config = $this->client->getConfig();
            $minutesBack = $config['bill_query']['query_minutes_back'] ?? 30;
            $pageSize = $config['bill_query']['page_size'] ?? 200;

            date_default_timezone_set('Asia/Shanghai');
            $endTime = date('Y-m-d H:i:s');
            $startTime = date('Y-m-d H:i:s', strtotime("-{$minutesBack} minutes"));

            $result = $this->billQuery->queryBills($startTime, $endTime, 1, $pageSize);
            $bills = $this->billQuery->extractBills($result);

            if (empty($bills)) {
                return ['code' => 0, 'data' => [], 'msg' => '无新收款记录'];
            }

            // 转换为mpay格式
            $records = [];

            foreach ($bills as $bill) {
                $records[] = [
                    'order_no' => $bill['trade_no'],
                    'price'    => $bill['amount'],
                    'payway'   => 'alipay',
                    'channel'  => '',
                    'remark'   => $bill['remark'],
                ];
            }

            return ['code' => 0, 'data' => $records];

        } catch (\Exception $e) {
            return ['code' => 1, 'msg' => '账单查询失败: ' . $e->getMessage()];
        }
    }
}
