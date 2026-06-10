<?php

return [
    [
        'id'       => 1,
        'title'    => '控制台',
        'icon'     => 'layui-icon layui-icon-console',
        'type'     => 0,
        'href'     => '',
        'children' => [
            [
                'id'       => 11,
                'title'    => '仪表盘',
                'icon'     => 'layui-icon layui-icon-console',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/Console/console',
            ],
        ],
    ],
    [
        'id'       => 2,
        'title'    => '订单管理',
        'icon'     => 'layui-icon layui-icon-list',
        'type'     => 0,
        'href'     => '',
        'children' => [
            [
                'id'       => 21,
                'title'    => '订单列表',
                'icon'     => 'layui-icon layui-icon-table',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/Order/index',
            ],
            [
                'id'       => 22,
                'title'    => '支付测试',
                'icon'     => 'layui-icon layui-icon-ok-circle',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/Order/testPay',
            ],
        ],
    ],
    [
        'id'       => 3,
        'title'    => '账号管理',
        'icon'     => 'layui-icon layui-icon-username',
        'type'     => 0,
        'href'     => '',
        'children' => [
            [
                'id'       => 31,
                'title'    => '账号列表',
                'icon'     => 'layui-icon layui-icon-user',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/PayManage/index',
            ],
            [
                'id'       => 32,
                'title'    => '收款统计',
                'icon'     => 'layui-icon layui-icon-chart',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/PayManage/payStatistics',
            ],
        ],
    ],
    [
        'id'       => 4,
        'title'    => '插件管理',
        'icon'     => 'layui-icon layui-icon-component',
        'type'     => 0,
        'href'     => '',
        'children' => [
            [
                'id'       => 41,
                'title'    => '插件中心',
                'icon'     => 'layui-icon layui-icon-component',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/Plugin/index',
            ],
        ],
    ],
    [
        'id'       => 5,
        'title'    => '用户中心',
        'icon'     => 'layui-icon layui-icon-set',
        'type'     => 0,
        'href'     => '',
        'children' => [
            [
                'id'       => 51,
                'title'    => '基本资料',
                'icon'     => 'layui-icon layui-icon-face-smile',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/User/index',
            ],
            [
                'id'       => 52,
                'title'    => '修改资料',
                'icon'     => 'layui-icon layui-icon-edit',
                'type'     => 1,
                'openType' => '_iframe',
                'href'     => '/User/setUser',
            ],
        ],
    ],
];
