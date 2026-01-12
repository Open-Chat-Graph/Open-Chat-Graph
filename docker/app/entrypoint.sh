#!/bin/bash
set -e

echo "Starting entrypoint.sh..."

# Xdebug設定（環境変数ENABLE_XDEBUG=1で有効化）
if [ "${ENABLE_XDEBUG}" = "1" ]; then
    echo "Enabling Xdebug..."
    cat > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini <<EOF
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.discover_client_host=true
EOF
    echo "Xdebug enabled"
else
    echo "Xdebug is disabled (set ENABLE_XDEBUG=1 to enable)"
    # Xdebugを無効化（エラーは無視）
    rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini 2>/dev/null || true
fi

echo "Starting Apache..."

# Apacheを起動
exec apache2-foreground
