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

# Cron設定スクリプトを実行（CRON=1の場合は有効化、それ以外はクリーンアップ）
# rootユーザーとして実行（sudoが不要な場合は直接実行）
if [ "$(id -u)" != "0" ]; then
    sudo -E /usr/local/bin/setup-cron.sh
else
    /usr/local/bin/setup-cron.sh
fi

# CRON機能が有効な場合
if [ "${CRON}" = "1" ]; then
    # Apacheをバックグラウンドで起動し、cronログをフォロー
    apache2-foreground &
    APACHE_PID=$!

    echo "Apache started (PID: $APACHE_PID), following cron log..."
    tail -f /var/log/cron.log &

    # Apacheプロセスを待機
    wait $APACHE_PID
else
    # Apacheを起動
    exec apache2-foreground
fi
