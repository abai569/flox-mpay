<?php

namespace app\controller;

use think\Response;

class AlipayJumpController
{
    /**
     * 302 重定向到 alipays:// 唤起支付宝
     * 通过服务端 302 绕过 Android Chrome 的 custom scheme 缓存
     */
    public function jump()
    {
        $orderId = request()->get('order_id', '');
        $nonce = $orderId . random_int(100000, 999999);

        $amount = 0;
        if ($orderId) {
            $order = \app\model\Order::where('order_id', $orderId)->find();
            if ($order) {
                $amount = (float)$order->really_price;
            }
        }

        try {
            $transfer = new \alipay\AlipayTransfer();
            $appUrl = $transfer->generateTransferLink($nonce, $amount);
        } catch (\Exception $e) {
            return Response::create('支付宝配置错误，请联系管理员', 'html', 500);
        }

        return Response::create('', 'html', 302)
            ->header([
                'Location'        => $appUrl,
                'Cache-Control'   => 'no-cache, no-store, must-revalidate',
                'Pragma'          => 'no-cache',
                'Expires'         => '0',
            ]);
    }
}
