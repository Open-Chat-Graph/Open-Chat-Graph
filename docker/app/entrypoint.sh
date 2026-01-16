#!/bin/bash
set -e

echo "Starting entrypoint.sh..."

# Mock環境用：storageディレクトリをコピー（匿名ボリューム使用時）
if [ ! -d "/var/www/html/storage/ja" ]; then
    echo "Copying storage directory from host..."
    # /var/www/html/storage-host から /var/www/html/storage にコピー
    if [ -d "/var/www/html/storage-host" ]; then
        if [ "$(id -u)" != "0" ]; then
            # www-dataユーザーの場合はsudoで実行
            sudo cp -a /var/www/html/storage-host/. /var/www/html/storage/
            sudo chown -R www-data:www-data /var/www/html/storage
        else
            # rootユーザーの場合はそのまま実行
            cp -a /var/www/html/storage-host/. /var/www/html/storage/
            chown -R www-data:www-data /var/www/html/storage
        fi
        echo "Storage directory copied successfully"
    else
        echo "Warning: /var/www/html/storage-host not found, storage directory not initialized"
    fi
fi

# ランキングファイルを削除（タイムスタンプ偽装のため、新規作成させる）
# 匿名ボリュームに古いデータが残っている場合に備えて常に実行
if [ -d "/var/www/html/storage-host" ]; then
    echo "Removing old ranking files for timestamp manipulation..."
    rm -f /var/www/html/storage/*/ranking_position/*.dat 2>/dev/null || true
    rm -f /var/www/html/storage/*/rising_position/*.dat 2>/dev/null || true
fi

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

# PHPの設定を更新してmkcertのCA証明書を使用（Mock環境用）
if [ -f /usr/local/share/ca-certificates/mkcert-rootCA.crt ]; then
    echo "Found mkcert root CA certificate"
    echo "Configuring system and PHP to trust mkcert CA..."

    # CA証明書を直接ca-certificates.crtに追加（update-ca-certificatesが/usr/local/share/を処理しない問題を回避）
    if [ "$(id -u)" != "0" ]; then
        if ! grep -q "mkcert" /etc/ssl/certs/ca-certificates.crt 2>/dev/null; then
            sudo sh -c 'cat /usr/local/share/ca-certificates/mkcert-rootCA.crt >> /etc/ssl/certs/ca-certificates.crt'
            echo "mkcert CA added to certificate store"
        fi
    else
        if ! grep -q "mkcert" /etc/ssl/certs/ca-certificates.crt 2>/dev/null; then
            cat /usr/local/share/ca-certificates/mkcert-rootCA.crt >> /etc/ssl/certs/ca-certificates.crt
            echo "mkcert CA added to certificate store"
        fi
    fi

    # PHPの設定も更新
    echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt" > /usr/local/etc/php/conf.d/openssl.ini
    echo "System CA store and PHP configured to trust mkcert CA"
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
