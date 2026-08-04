<?php

$dbPath = '/var/www/html/database/mpay.db';

if (!file_exists($dbPath)) {
    exit(0);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $columns = $db->query('PRAGMA table_info(mpay_order)')->fetchAll(PDO::FETCH_ASSOC);
    $hasPaySource = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'pay_source') {
            $hasPaySource = true;
            break;
        }
    }

    if (!$hasPaySource) {
        $db->exec("ALTER TABLE mpay_order ADD COLUMN pay_source TEXT NOT NULL DEFAULT ''");
        echo "[OK] 数据库迁移完成：mpay_order.pay_source\n";
    }
} catch (Exception $e) {
    echo "[WARN] 数据库迁移失败: " . $e->getMessage() . "\n";
}
