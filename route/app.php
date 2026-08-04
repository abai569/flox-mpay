<?php

use think\facade\Route;

// 页面跳转支付
Route::rule('submit', 'Pay/submit');
// API支付
Route::rule('mapi', 'Pay/mapi');
// 收银台
Route::rule('/Pay/console/[:order_id]', '/Pay/console');
// 查询订单状态
Route::rule('getOrderState/[:order_id]', 'Pay/getOrderState');
// 监控新订单
Route::rule('checkOrder/[:pid]/[:sign]', 'Pay/checkOrder');
// 处理收款通知
Route::rule('payHeart', 'Pay/payHeart');
// 监听收款通知
Route::rule('checkPayResult', 'Pay/checkPayResult');
// 监听微信/支付宝收款通知
Route::rule('mpayNotify', 'Pay/mpayNotify');
// 验证支付结果
Route::rule('validatePayResult', 'Pay/validatePayResult');
// 支付宝唤起跳转（302 重定向到 alipays://，绕过浏览器 scheme 缓存）
Route::rule('alipayJump', 'AlipayJump/jump');

// API多级控制器
Route::rule('api/:controller/:method', 'api.:controller/:method');

// 开发文档
Route::rule('doc', 'Index/doc');