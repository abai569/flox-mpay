<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\AlipayConfig;
use think\facade\View;

class AlipayConfigController extends BaseController
{
    public function index()
    {
        $config = AlipayConfig::getConfig();

        View::assign([
            'app_id'            => $config['app_id'] ?? '',
            'private_key'       => $config['private_key'] ?? '',
            'alipay_public_key' => $config['alipay_public_key'] ?? '',
            'transfer_user_id'  => $config['transfer_user_id'] ?? '',
            'query_minutes_back' => $config['bill_query']['query_minutes_back'] ?? 30,
        ]);

        return View::fetch();
    }
}
