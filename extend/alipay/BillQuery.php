<?php

namespace alipay;

class BillQuery
{
    private AlipayClient $client;
    private array $config;

    public function __construct(?AlipayClient $client = null)
    {
        $this->client = $client ?? new AlipayClient();
        $this->config = $this->client->getConfig();
    }

    /**
     * 查询账户账单流水
     *
     * @param string $startTime 开始时间 Y-m-d H:i:s
     * @param string $endTime   结束时间 Y-m-d H:i:s
     * @param int    $pageNo    页码
     * @param int    $pageSize  每页大小
     * @return array
     */
    public function queryBills(string $startTime, string $endTime, int $pageNo = 1, int $pageSize = 200): array
    {
        $this->validateTime($startTime);
        $this->validateTime($endTime);

        $bizContent = [
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'page_no'    => (string)$pageNo,
            'page_size'  => (string)min($pageSize, 2000),
        ];

        $result = $this->client->execute('alipay.data.bill.accountlog.query', $bizContent);

        if (isset($result['code']) && $result['code'] !== '10000') {
            throw new \RuntimeException('支付宝账单查询失败: ' . ($result['sub_msg'] ?? $result['msg'] ?? '未知错误'));
        }

        return $result;
    }

    /**
     * 查询今天账单
     */
    public function queryTodayBills(): array
    {
        date_default_timezone_set('Asia/Shanghai');
        $today = date('Y-m-d');
        return $this->queryBills($today . ' 00:00:00', $today . ' 23:59:59');
    }

    /**
     * 从查询结果中提取账单列表
     */
    public function extractBills(array $result): array
    {
        $bills = [];

        // 支持多种返回格式
        $detailList = $result['detail_list'] ?? $result['account_log_list'] ?? [];

        foreach ($detailList as $item) {
            // 只处理收入类型
            $direction = $item['direction'] ?? '';
            if (!empty($direction) && $direction !== '收入' && $direction !== 'in') {
                continue;
            }

            $bills[] = [
                'trade_no'    => $item['alipay_order_no'] ?? $item['trade_no'] ?? '',
                'amount'      => $item['trans_amount'] ?? $item['amount'] ?? '0',
                'remark'      => $item['trans_memo'] ?? $item['memo'] ?? $item['remark'] ?? '',
                'trans_date'  => $item['trans_dt'] ?? $item['trans_date'] ?? '',
                'direction'   => $direction,
                'type'        => $item['type'] ?? '',
                'other_account' => $item['other_account'] ?? '',
            ];
        }

        return $bills;
    }

    private function validateTime(string $time): void
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $time);
        if (!$d || $d->format('Y-m-d H:i:s') !== $time) {
            throw new \InvalidArgumentException('时间格式错误，应为 Y-m-d H:i:s');
        }
    }
}
