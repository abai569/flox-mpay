#!/bin/bash
set -e

if [ ! -f /var/www/html/.env ]; then
    cat > /var/www/html/.env << EOF
APP_DEBUG = ${APP_DEBUG:-false}

DB_TYPE = ${DB_TYPE:-mysql}
DB_HOST = ${DB_HOST:-127.0.0.1}
DB_NAME = ${DB_NAME:-mpay}
DB_USER = ${DB_USER:-root}
DB_PASS = ${DB_PASS:-root}
DB_PORT = ${DB_PORT:-3306}
DB_PREFIX = ${DB_PREFIX:-mpay_}

DEFAULT_LANG = ${DEFAULT_LANG:-zh-cn}
EOF
    chown www-data:www-data /var/www/html/.env
fi

exec "$@"
