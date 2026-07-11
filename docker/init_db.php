<?php
/**
 * SQLite 自动初始化脚本
 * 在 Docker 容器启动时由 entrypoint.sh 调用
 */

$dbPath = '/var/www/html/database/mpay.db';
$rootPath = '/var/www/html';

if (file_exists($dbPath)) {
    echo "[INFO] SQLite 数据库已存在，跳过自动初始化\n";
    exit(0);
}

echo "[INFO] 正在初始化 SQLite 数据库...\n";

$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = [
        'mpay_order' => "CREATE TABLE `mpay_order` (
            id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER NOT NULL DEFAULT 0, order_id TEXT NOT NULL DEFAULT '', type TEXT NOT NULL DEFAULT '', out_trade_no TEXT NOT NULL DEFAULT '', notify_url TEXT NOT NULL DEFAULT '', return_url TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', really_price REAL NOT NULL DEFAULT 0.00, money REAL NOT NULL DEFAULT 0.00, clientip TEXT NOT NULL DEFAULT '', device TEXT NOT NULL DEFAULT '', param TEXT NOT NULL DEFAULT '', state INTEGER NOT NULL DEFAULT 0, patt INTEGER NOT NULL DEFAULT 0, create_time DATETIME DEFAULT (datetime('now','localtime')), close_time DATETIME DEFAULT NULL, pay_time DATETIME DEFAULT NULL, platform TEXT NOT NULL DEFAULT '', platform_order TEXT NOT NULL DEFAULT '', aid INTEGER NOT NULL DEFAULT 0, cid INTEGER NOT NULL DEFAULT 0, delete_time DATETIME DEFAULT NULL)",
        'mpay_pay_account' => "CREATE TABLE `mpay_pay_account` (
            id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER NOT NULL DEFAULT 0, platform TEXT NOT NULL DEFAULT '', account TEXT NOT NULL DEFAULT '', password TEXT NOT NULL DEFAULT '', state INTEGER NOT NULL DEFAULT 1, pattern INTEGER NOT NULL DEFAULT 1, params TEXT NOT NULL, delete_time DATETIME DEFAULT NULL)",
        'mpay_pay_channel' => "CREATE TABLE `mpay_pay_channel` (
            id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL DEFAULT 0, channel TEXT NOT NULL DEFAULT '', type INTEGER NOT NULL DEFAULT 0, qrcode TEXT NOT NULL DEFAULT '', last_time DATETIME DEFAULT (datetime('now','localtime')), state INTEGER NOT NULL DEFAULT 1, delete_time DATETIME DEFAULT NULL)",
        'mpay_user' => "CREATE TABLE `mpay_user` (
            id INTEGER PRIMARY KEY AUTOINCREMENT, pid INTEGER NOT NULL DEFAULT 0, secret_key TEXT NOT NULL DEFAULT '', nickname TEXT NOT NULL DEFAULT '', username TEXT NOT NULL DEFAULT '', password TEXT NOT NULL DEFAULT '', state INTEGER NOT NULL DEFAULT 1, role INTEGER NOT NULL DEFAULT 0, create_time DATETIME DEFAULT (datetime('now','localtime')), delete_time DATETIME DEFAULT NULL)",
    ];

    foreach ($tables as $name => $sql) {
        $db->exec($sql);
        echo "[OK] 表 $name 创建成功\n";
    }

    // 生成 9 位随机密码 (去除易混淆字符)
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 9; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }

    $secretKey = md5(1000 . time() . mt_rand());
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO mpay_user (id, pid, secret_key, nickname, username, password, state, role, create_time) VALUES (1, 1000, :secret_key, :nickname, :username, :password, 1, 1, datetime('now','localtime'))");
    $stmt->execute([
        ':secret_key' => $secretKey,
        ':nickname' => 'Mpay',
        ':username' => 'admin',
        ':password' => $hashedPassword,
    ]);

    echo "[OK] 默认管理员 admin 已创建\n";

    @chmod($dbPath, 0666);

    $lockPath = $rootPath . '/runtime/install.lock';
    $runtimeDir = $rootPath . '/runtime';
    if (!is_dir($runtimeDir)) {
        mkdir($runtimeDir, 0777, true);
    }
    file_put_contents($lockPath, (string)time());
    @chmod($lockPath, 0666);
    echo "[OK] 安装锁已生成\n";

    echo "INIT_PASSWORD={$password}\n";
    echo "INIT_DONE\n";

} catch (Exception $e) {
    echo "[ERROR] 数据库初始化失败: " . $e->getMessage() . "\n";
    echo "INIT_ERROR\n";
    exit(1);
}
