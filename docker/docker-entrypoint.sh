#!/bin/bash
set -e

if [ ! -f /var/www/html/.env ]; then
    cat > /var/www/html/.env << EOF
APP_DEBUG = ${APP_DEBUG:-false}

DB_TYPE = ${DB_TYPE:-sqlite}
DB_NAME = ${DB_NAME:-database/mpay.db}
DB_PREFIX = ${DB_PREFIX:-mpay_}

DEFAULT_LANG = ${DEFAULT_LANG:-zh-cn}
EOF
    chmod 644 /var/www/html/.env
    chown www-data:www-data /var/www/html/.env
fi

# 规范化 DB_TYPE（去掉首尾空格）
_DB_TYPE=$(echo "${DB_TYPE:-sqlite}" | tr -d '[:space:]')

# Auto-init SQLite database if not yet initialized
if [ "$_DB_TYPE" = "sqlite" ] && [ ! -f /var/www/html/database/mpay.db ]; then
    echo ""
    echo "[INFO] 检测到 SQLite 模式且数据库不存在，执行自动初始化..."
    php /var/www/html/docker/init_db.php 2>&1 || true
    echo ""
    
    # 设置数据库文件和锁文件权限
    if [ -f /var/www/html/database/mpay.db ]; then
        chmod 666 /var/www/html/database/mpay.db
        chown www-data:www-data /var/www/html/database/mpay.db
    fi
    if [ -f /var/www/html/runtime/install.lock ]; then
        chmod 666 /var/www/html/runtime/install.lock
        chown www-data:www-data /var/www/html/runtime/install.lock
    fi
fi

# 确保 runtime 子目录存在（mkdir 必须在 chown 之前，否则新建目录仍是 root 拥有）
mkdir -p /var/www/html/runtime/session
mkdir -p /var/www/html/runtime/log
mkdir -p /var/www/html/runtime/cache

# 递归设置 www-data 权限（PHP-FPM 需要写入 runtime 目录）
chown -R www-data:www-data /var/www/html/runtime
chown -R www-data:www-data /var/www/html/database

php /var/www/html/think clear 2>/dev/null || true

exec "$@"
