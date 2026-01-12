#!/bin/bash

# SSL証明書ディレクトリ
SSL_DIR="./docker/app/ssl-new"
CERT_FILE="${SSL_DIR}/ocgraph.test+4.pem"

# 証明書が既に存在する場合はスキップ
if [ -f "$CERT_FILE" ]; then
    echo "SSL証明書は既に存在します: $CERT_FILE"
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
    echo "SSL証明書の生成が完了しました"
else
    echo "エラー: SSL証明書の生成に失敗しました"
    exit 1
fi
