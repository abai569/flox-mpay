#!/bin/bash
set -e

mkdir -p /var/www/html/database

cat > /var/www/html/.env << EOF
APP_DEBUG = ${APP_DEBUG:-false}

DB_TYPE = sqlite
DB_NAME = database/mpay.db
DB_PREFIX = ${DB_PREFIX:-mpay_}

DEFAULT_LANG = ${DEFAULT_LANG:-zh-cn}
EOF

chown -R www-data:www-data /var/www/html/database /var/www/html/.env

exec "$@"
