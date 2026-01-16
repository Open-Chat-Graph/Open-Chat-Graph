#!/bin/bash

# SSL証明書ディレクトリ
SSL_DIR="./docker/app/ssl-new"
CERT_FILE="${SSL_DIR}/ocgraph.test+4.pem"
ROOT_CA_FILE="${SSL_DIR}/rootCA.pem"

# mkcertのルートCA証明書をコピーする関数
copy_root_ca() {
    local MKCERT_ROOT=$(mkcert -CAROOT 2>/dev/null)
    if [ -n "$MKCERT_ROOT" ] && [ -f "${MKCERT_ROOT}/rootCA.pem" ]; then
        # ディレクトリとして誤って作成されている場合は削除
        if [ -d "$ROOT_CA_FILE" ]; then
            rm -rf "$ROOT_CA_FILE"
        fi
        # ファイルが存在しないか、内容が異なる場合はコピー
        if [ ! -f "$ROOT_CA_FILE" ] || ! cmp -s "${MKCERT_ROOT}/rootCA.pem" "$ROOT_CA_FILE"; then
            cp "${MKCERT_ROOT}/rootCA.pem" "$ROOT_CA_FILE"
            echo "ルートCA証明書をコピーしました: $ROOT_CA_FILE"
        fi
    else
        echo "警告: mkcertのルートCA証明書が見つかりません"
    fi
}

# 証明書が既に存在する場合
if [ -f "$CERT_FILE" ]; then
    echo "SSL証明書は既に存在します: $CERT_FILE"
    # ルートCA証明書は常に確認・コピー
    copy_root_ca
    exit 0
fi

# mkcertがインストールされているか確認
if ! command -v mkcert &> /dev/null; then
    echo "エラー: mkcertがインストールされていません"
    echo "インストール方法: https://github.com/FiloSottile/mkcert#installation"
    exit 1
fi

# SSLディレクトリを作成
mkdir -p "$SSL_DIR"

# SSL証明書を生成
echo "SSL証明書を生成しています..."
cd "$SSL_DIR" && mkcert ocgraph.test ocgraph-mock.test localhost 127.0.0.1 ::1

if [ $? -eq 0 ]; then
    # Dockerコンテナ内のApacheが読み取れるよう秘密鍵の権限を変更
    chmod 644 ocgraph.test+4-key.pem
    echo "SSL証明書の生成が完了しました"

    # ルートCA証明書をコピー
    copy_root_ca
else
    echo "エラー: SSL証明書の生成に失敗しました"
    exit 1
fi
