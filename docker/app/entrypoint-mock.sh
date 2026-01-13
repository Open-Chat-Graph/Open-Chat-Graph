#!/bin/bash
set -e

echo "Starting entrypoint-mock.sh..."

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

# PHPの設定を更新してmkcertのCA証明書を使用
if [ -f /usr/local/share/ca-certificates/mkcert-rootCA.crt ]; then
    echo "Found mkcert root CA certificate"
    echo "Configuring PHP to trust mkcert CA..."

    # 既存のCA証明書とmkcert CAを結合したファイルを作成
    cat /etc/ssl/certs/ca-certificates.crt /usr/local/share/ca-certificates/mkcert-rootCA.crt > /tmp/combined-ca.crt

    # PHPの設定を更新
    echo "openssl.cafile=/tmp/combined-ca.crt" > /usr/local/etc/php/conf.d/openssl.ini
    echo "PHP configured to trust mkcert CA"
fi


# storageとpublicディレクトリの所有者をwww-dataに変更（Cron実行に必要）
echo "Setting permissions for storage and public directories..."
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/html/public 2>/dev/null || true
echo "Permissions set (some files may be owned by host user)"
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
