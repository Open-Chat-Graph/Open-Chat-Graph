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

# ───────────────────────────────────────────────────────────────
# www-data の UID を bind mount 実体の所有者に合わせる（ローカル/CI 共通）
#
# ./:/var/www/html を bind mount しているため、storage 配下のキャッシュ・ログは
# ホスト(ローカル uid 1000)や GitHub Actions runner の UID 所有になる。
# 一方 Apache・cron・CI テストはすべて www-data で動くので、UID がズレると PHP から
# storage/$lang/{logs,static_data_top,ranking_position,...} を書けず Permission denied
# になる（例: /admin/genetop の addCronLog 失敗）。
# www-data の UID をマウント実体に合わせれば、777 等の緩い権限を使わず通常 perms の
# まま双方向で読み書きできる。
#
# usermod -u は home(/var/www → bind mount 配下)を再帰 chown して 49GB の SQLite を
# 走査するため使わない。/etc/passwd を直接書き換えて UID だけ変更する。
# ───────────────────────────────────────────────────────────────
MOUNT_OWNER_UID="$(stat -c '%u' /var/www/html 2>/dev/null || echo 33)"
CURRENT_WWW_UID="$(id -u www-data 2>/dev/null || echo 33)"
WWW_GID="$(id -g www-data 2>/dev/null || echo 33)"
if [ "$MOUNT_OWNER_UID" != "0" ] && [ "$MOUNT_OWNER_UID" != "$CURRENT_WWW_UID" ]; then
    echo "Aligning www-data UID: ${CURRENT_WWW_UID} -> ${MOUNT_OWNER_UID} (matches bind-mounted code owner)"
    run_as_root sed -i "s#^www-data:x:[0-9]*:[0-9]*:#www-data:x:${MOUNT_OWNER_UID}:${WWW_GID}:#" /etc/passwd
    # 旧 UID(33) 所有の Apache ランタイム/ログ/キャッシュを新 UID へ追従させる
    run_as_root chown -R "${MOUNT_OWNER_UID}:${WWW_GID}" \
        /var/log/apache2 /var/cache/apache2 /run/apache2 /run/lock/apache2 2>/dev/null || true
    # 旧 UID(www-data) が作成済みの bind mount 上のファイルを新 UID へ移譲する。
    # 特に SQLite の .db-wal/.db-shm は旧 UID 所有・グループ書き込み不可で残るため、
    # これをやらないと remap 後に www-data が既存 DB を開けず
    # PDOException: SQLSTATE[HY000]: General error: 14 unable to open database file になる。
    # 移譲対象は www-data が書き込む storage/ と public/（アップロード画像）配下のみ。
    # 既に新 UID 所有のファイル（同期キャッシュ等）は対象外なので、初回以降は実質 no-op。
    run_as_root find /var/www/html/storage /var/www/html/public \
        -uid "${CURRENT_WWW_UID}" -exec chown "${MOUNT_OWNER_UID}:${WWW_GID}" {} + 2>/dev/null || true
else
    echo "www-data UID alignment: skip (mount owner=${MOUNT_OWNER_UID}, www-data=${CURRENT_WWW_UID})"
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
# 注意: 000-default.conf は単一ファイルとして bind mount されているため、sed -i の
# rename(temp→target) が "Device or resource busy" で失敗する。set -e 下でここが
# 中断するとコンテナごと起動失敗するため、失敗しても続行させる（置換は http→https
# リダイレクトのポート表記だけに影響し、HTTPS 配信自体には影響しない）。
HTTPS_PORT_VALUE=${HTTPS_PORT:-8443}
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    run_as_root sed -i "s/{{HTTPS_PORT}}/${HTTPS_PORT_VALUE}/g" /etc/apache2/sites-enabled/000-default.conf \
        && echo "Apache HTTP config updated: HTTPS_PORT=${HTTPS_PORT_VALUE}" \
        || echo "Apache HTTP config: HTTPS_PORT 置換をスキップ (bind mount のため sed -i 不可)"
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
