<?php
/**
 * SQLite 自动初始化脚本
 * 在 Docker 容器启动时由 entrypoint.sh 调用
 * 自动创建表、插入默认管理员、生成 install.lock
 */

header('Content-Type: text/plain; charset=utf-8');

$dbPath = __DIR__ . '/../database/mpay.db';

if (file_exists($dbPath)) {
    echo "[INFO] SQLite 数据库已存在，跳过自动初始化\n";
    exit(0);
}

echo "[INFO] 正在初始化 SQLite 数据库...\n";

// 确保 database 目录存在
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 创建表
    $tables = [
        'mpay_order' => "CREATE TABLE `mpay_order` (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pid INTEGER NOT NULL DEFAULT 0,
            order_id TEXT NOT NULL DEFAULT '',
            type TEXT NOT NULL DEFAULT '',
            out_trade_no TEXT NOT NULL DEFAULT '',
            notify_url TEXT NOT NULL DEFAULT '',
            return_url TEXT NOT NULL DEFAULT '',
            name TEXT NOT NULL DEFAULT '',
            really_price REAL NOT NULL DEFAULT 0.00,
            money REAL NOT NULL DEFAULT 0.00,
            clientip TEXT NOT NULL DEFAULT '',
            device TEXT NOT NULL DEFAULT '',
            param TEXT NOT NULL DEFAULT '',
            state INTEGER NOT NULL DEFAULT 0,
            patt INTEGER NOT NULL DEFAULT 0,
            create_time DATETIME DEFAULT (datetime('now','localtime')),
            close_time DATETIME DEFAULT NULL,
            pay_time DATETIME DEFAULT NULL,
            platform TEXT NOT NULL DEFAULT '',
            platform_order TEXT NOT NULL DEFAULT '',
            aid INTEGER NOT NULL DEFAULT 0,
            cid INTEGER NOT NULL DEFAULT 0,
            delete_time DATETIME DEFAULT NULL
        )",
        'mpay_pay_account' => "CREATE TABLE `mpay_pay_account` (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pid INTEGER NOT NULL DEFAULT 0,
            platform TEXT NOT NULL DEFAULT '',
            account TEXT NOT NULL DEFAULT '',
            password TEXT NOT NULL DEFAULT '',
            state INTEGER NOT NULL DEFAULT 1,
            pattern INTEGER NOT NULL DEFAULT 1,
            params TEXT NOT NULL,
            delete_time DATETIME DEFAULT NULL
        )",
        'mpay_pay_channel' => "CREATE TABLE `mpay_pay_channel` (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL DEFAULT 0,
            channel TEXT NOT NULL DEFAULT '',
            type INTEGER NOT NULL DEFAULT 0,
            qrcode TEXT NOT NULL DEFAULT '',
            last_time DATETIME DEFAULT (datetime('now','localtime')),
            state INTEGER NOT NULL DEFAULT 1,
            delete_time DATETIME DEFAULT NULL
        )",
        'mpay_user' => "CREATE TABLE `mpay_user` (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pid INTEGER NOT NULL DEFAULT 0,
            secret_key TEXT NOT NULL DEFAULT '',
            nickname TEXT NOT NULL DEFAULT '',
            username TEXT NOT NULL DEFAULT '',
            password TEXT NOT NULL DEFAULT '',
            state INTEGER NOT NULL DEFAULT 1,
            role INTEGER NOT NULL DEFAULT 0,
            create_time DATETIME DEFAULT (datetime('now','localtime')),
            delete_time DATETIME DEFAULT NULL
        )",
    ];

    foreach ($tables as $name => $sql) {
        $db->exec($sql);
        echo "[OK] 表 $name 创建成功\n";
    }

    // 生成 9 位随机密码 (6随机+1数字+1大写+1小写, 打乱)
    $rand = str_split(bin2hex(random_bytes(3)));
    $num = (string)rand(0, 9);
    $upper = chr(rand(65, 90));
    $lower = chr(rand(97, 122));
    $password = str_shuffle($rand[0] . $rand[1] . $rand[2] . $num . $upper . $lower . $rand[3] . $rand[4] . $rand[5]);

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

    // 设置权限
    @chmod($dbPath, 0666);

    // 生成 install.lock
    $lockPath = __DIR__ . '/../runtime/install.lock';
    $runtimeDir = dirname($lockPath);
    if (!is_dir($runtimeDir)) {
        mkdir($runtimeDir, 0777, true);
    }
    file_put_contents($lockPath, (string)time());
    @chmod($lockPath, 0666);
    echo "[OK] 安装锁已生成\n";

    // 打印登录信息
    $publicIp = getenv('PUBLIC_IP') ?: trim(@shell_exec('curl -fsSL --max-time 3 https://ifconfig.me 2>/dev/null') ?: @shell_exec('curl -fsSL --max-time 3 https://ip.sb 2>/dev/null') ?: '服务器IP');
    $mpayPort = getenv('MPAY_PORT') ?: '8088';

    echo "\n";
    echo "===============================================\n";
    echo "   [OK] mpay 自动初始化完成\n";
    echo "===============================================\n";
    echo "   访问地址：http://{$publicIp}:{$mpayPort}\n";
    echo "   用户名：admin\n";
    echo "   密码：{$password}\n";
    echo "   [WARN] 首次登录后请修改默认密码！\n";
    echo "===============================================\n";

} catch (Exception $e) {
    echo "[ERROR] 数据库初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}
