<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;

class AlipayConfigController extends BaseController
{
    private string $configPath;

    public function initialize(): void
    {
        $this->configPath = root_path() . 'config/alipay.php';
    }

    public function getConfig()
    {
        $config = file_exists($this->configPath) ? require $this->configPath : [];

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

        $config = file_exists($this->configPath) ? require $this->configPath : [];

        $config['app_id']             = $post['app_id'] ?? $config['app_id'] ?? '';
        $config['private_key']        = $post['private_key'] ?? $config['private_key'] ?? '';
        $config['alipay_public_key']  = $post['alipay_public_key'] ?? $config['alipay_public_key'] ?? '';
        $config['transfer_user_id']   = $post['transfer_user_id'] ?? $config['transfer_user_id'] ?? '';

        if (!isset($config['bill_query'])) {
            $config['bill_query'] = [];
        }
        $config['bill_query']['query_minutes_back'] = (int)($post['query_minutes_back'] ?? $config['bill_query']['query_minutes_back'] ?? 30);
        $config['bill_query']['page_size'] = $config['bill_query']['page_size'] ?? 200;

        $content = "<?php\n// +----------------------------------------------------------------------\n// | 支付宝账单API配置 - 由后台自动生成，请勿手动修改\n// +----------------------------------------------------------------------\nreturn " . var_export($config, true) . ";\n";

        $result = file_put_contents($this->configPath, $content);
        if ($result !== false) {
            return json(backMsg(0, '保存成功'));
        }

        return json(backMsg(1, '保存失败，请检查目录写入权限'));
    }
}
