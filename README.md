# flox-mpay — Docker 一键免签收款

基于 mpay 魔改，专注 Docker 部署，免配 PHP/MySQL/Nginx。

## 安装

```bash
bash <(curl -L https://raw.githubusercontent.com/abai569/flox-mpay/main/install.sh)
```

## 管理命令

更新到最新版：

```bash
bash <(curl -L https://raw.githubusercontent.com/abai569/flox-mpay/main/install.sh) update
```

安装指定版本：

```bash
bash <(curl -L https://raw.githubusercontent.com/abai569/flox-mpay/main/install.sh) 1.8.6
```

卸载：

```bash
bash <(curl -L https://raw.githubusercontent.com/abai569/flox-mpay/main/install.sh) uninstall
```

## 系统要求

Docker 环境，脚本自动检测并安装。支持 Debian/Ubuntu/CentOS。

## 功能

- 支付宝账单模式收款（无需手机挂机，通过支付宝开放平台账单 API 监听）
- 支付宝收钱码/经营码收款（需手机挂机监听）
- 微信收款码收款（需手机/PC 挂机监听）
- 聚合码收款（收钱吧等，无需挂机）
- 多平台、多账号、多通道轮询

## 使用文档

完整配置文档：[飞书文档](https://f0bmwzqjtq2.feishu.cn/docx/HBVrdrsACo36bzxUCSPcjOBNnyb)

## 版本

| 版本 | 主要内容 |
|------|---------|
| v1.8.6 | 修复微信监控成功回调被重复重试，更新时保留登录状态 |
| v1.8.5 | 订单列表支持批量手动补单 |
| v1.8.4 | 订单列表区分自动支付与手动补单，补单显示补单时间 |
| v1.8.3 | 发布当前源码构建，延续支付宝账单唤起优化 |
| v1.8.2 | 支付宝账单 5层 HTTPS 外壳修复二次拉起跳首页 |
| v1.8.1 | Docker/CI 优化，install.sh 支持无交互升级 |

## 致谢

本项目基于 [mpay](https://gitee.com/technical-laohu/mpay) 二次开发。

支付宝账单模式参考 [AliMPay](https://github.com/MiaM1ku/AliMPay)。
