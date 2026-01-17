#!/bin/bash
set -e

echo "Starting entrypoint.sh..."

# rootまたはsudoでコマンドを実行する関数
run_as_root() {
    if [ "$(id -u)" != "0" ]; then
        sudo "$@"
    else
        "$@"
    fi
}

# 環境変数を保持してrootまたはsudoでコマンドを実行する関数
run_as_root_with_env() {
    if [ "$(id -u)" != "0" ]; then
        sudo -E "$@"
    else
        "$@"
    fi
}

# Mock環境用：storageディレクトリをコピー（匿名ボリューム使用時）
if [ ! -d "/var/www/html/storage/ja" ]; then
    echo "Copying storage directory from host..."
    # /var/www/html/storage-host から /var/www/html/storage にコピー
    if [ -d "/var/www/html/storage-host" ]; then
        run_as_root cp -a /var/www/html/storage-host/. /var/www/html/storage/
        run_as_root chown -R www-data:www-data /var/www/html/storage
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

    # システムのCA証明書ストアを更新（Docker Layer Cachingで古い証明書が残っている場合に対応）
    # update-ca-certificatesを使用して証明書を正しく更新
    run_as_root update-ca-certificates --fresh >/dev/null 2>&1 || true

    # CA証明書を直接ca-certificates.crtに追加（update-ca-certificatesが/usr/local/share/を処理しない問題を回避）
    if ! grep -Fxq "$(cat /usr/local/share/ca-certificates/mkcert-rootCA.crt)" /etc/ssl/certs/ca-certificates.crt 2>/dev/null; then
        run_as_root sh -c 'cat /usr/local/share/ca-certificates/mkcert-rootCA.crt >> /etc/ssl/certs/ca-certificates.crt'
        echo "mkcert CA added to certificate store"
    else
        echo "mkcert CA already in certificate store"
    fi

    # PHPの設定も更新
    echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt" > /usr/local/etc/php/conf.d/openssl.ini
    echo "System CA store and PHP configured to trust mkcert CA"
fi

# Apache設定ファイルのHTTPSポート番号を環境変数で置換
HTTPS_PORT_VALUE=${HTTPS_PORT:-8443}
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    run_as_root sed -i "s/{{HTTPS_PORT}}/${HTTPS_PORT_VALUE}/g" /etc/apache2/sites-enabled/000-default.conf
    echo "Apache HTTP config updated: HTTPS_PORT=${HTTPS_PORT_VALUE}"
fi

echo "Starting Apache..."

# Cron設定スクリプトを実行（CRON=1の場合は有効化、それ以外はクリーンアップ）
run_as_root_with_env /usr/local/bin/setup-cron.sh

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
