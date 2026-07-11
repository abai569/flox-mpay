<?php
// +----------------------------------------------------------------------
// | 支付插件列表
// +----------------------------------------------------------------------
return [
    [
        'platform'   => 'wxpay',
        'name'       => '微信',
        'class_name' => 'WxPay',
        'price'      => 0,
        'describe'   => '微信支付',
        'website'    => '',
        'helplink'   => '',
        'version'    => '1.0.0',
        'state'      => 1,
    ],
    [
        'platform'   => 'alipay',
        'name'       => '支付宝',
        'class_name' => 'AliPay',
        'price'      => 0,
        'describe'   => '支付宝支付',
        'website'    => '',
        'helplink'   => '',
        'version'    => '1.0.0',
        'state'      => 1,
    ],
    [
        'platform'   => 'alibill',
        'name'       => '支付宝账单',
        'class_name' => 'AliBill',
        'price'      => 0,
        'describe'   => '通过支付宝开放平台账单API监听收款，无需手机挂机',
        'website'    => 'https://github.com/MiaM1ku/AliMPay',
        'helplink'   => '',
        'version'    => '1.0.0',
        'state'      => 1,
    ],
];
