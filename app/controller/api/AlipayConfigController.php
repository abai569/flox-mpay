<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\AlipayConfig;

class AlipayConfigController extends BaseController
{
    public function getConfig()
    {
        $config = AlipayConfig::getConfig();

        return json(backMsg(0, 'OK', [
            'app_id'             => $config['app_id'] ?? '',
            'private_key'        => $config['private_key'] ?? '',
            'alipay_public_key'  => $config['alipay_public_key'] ?? '',
            'transfer_user_id'   => $config['transfer_user_id'] ?? '',
            'query_minutes_back' => $config['bill_query']['query_minutes_back'] ?? 30,
        ]));
    }

    public function saveConfig()
    {
        $post = $this->request->post();

        $result = AlipayConfig::saveConfig([
            'app_id'             => $post['app_id'] ?? '',
            'private_key'        => $post['private_key'] ?? '',
            'alipay_public_key'  => $post['alipay_public_key'] ?? '',
            'transfer_user_id'   => $post['transfer_user_id'] ?? '',
            'query_minutes_back' => (int)($post['query_minutes_back'] ?? 30),
        ]);

        if ($result) {
            return json(backMsg(0, '保存成功'));
        }

        return json(backMsg(1, '保存失败'));
    }
}
