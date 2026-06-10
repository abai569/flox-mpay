<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\View;
use app\model\User;

class UserController extends BaseController
{
    protected $middleware = ['Auth' => ['except' => ['login']]];
    // 用户中心
    public function index()
    {
        $userinfo = User::find(\session('userid'))->toArray();
        View::assign($userinfo);
        View::assign('url', $this->request->domain().'/');
        $sign = md5($userinfo['pid'] . $userinfo['secret_key']);
        View::assign('orderurl', $this->request->domain() . "/checkOrder/{$userinfo['pid']}/{$sign}");

        // 加密扫码导入 payload：WePay APP 专属，普通扫码 APP 只会看到 MPAYQR1:xxxxx 密文。
        //   明文: {"s":"host","p":"pid","k":"secret_key"}
        //   算法: AES-256-GCM, key = SHA-256(固定 passphrase), IV 12B 随机
        //   编码: urlsafe-base64(IV || CT || TAG)
        $host    = preg_replace('#^https?://#i', '', rtrim($this->request->domain(), '/'));
        $payload = json_encode([
            's' => $host,
            'p' => (string)$userinfo['pid'],
            'k' => (string)$userinfo['secret_key'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $keyBin = hash('sha256', 'WePay-Mpay-QR-v1-SharedKey-DoNotChange', true);
        $iv     = random_bytes(12);
        $tag    = '';
        $ct     = openssl_encrypt($payload, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
        $raw    = $iv . $ct . $tag;
        $mpayQr = 'MPAYQR1:' . rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        View::assign('mpay_qr', $mpayQr);

        return View::fetch();
    }
    // 登陆视图
    public function login()
    {
        if (session('?islogin')) {
            return redirect('/Console/index');
        }
        return View::fetch();
    }
    // 修改用户
    public function setUser()
    {
        $userinfo = User::find(session('userid'))->toArray();
        View::assign($userinfo);
        return View::fetch();
    }
    // 管理菜单
    public function menu()
    {
        $menu = include app()->getConfigPath() . 'extend/menu.php';
        return json($menu);
    }
}
