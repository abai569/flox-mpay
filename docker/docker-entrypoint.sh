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
    chown www-data:www-data /var/www/html/.env
fi

# Auto-init SQLite database if not yet initialized
if [ "$DB_TYPE" = "sqlite" ] && [ ! -f /var/www/html/database/mpay.db ]; then
    echo "[INFO] 检测到 SQLite 模式且数据库不存在，执行自动初始化..."
    php /var/www/html/docker/init_db.php
    chown www-data:www-data /var/www/html/database/mpay.db
    if [ -f /var/www/html/runtime/install.lock ]; then
        chown www-data:www-data /var/www/html/runtime/install.lock
    fi
fi

exec "$@"
