#!/bin/bash

# スクリプトの実行ディレクトリ（リポジトリルート）を保存
REPO_ROOT=$(pwd)
REPO_NAME=$(basename "$REPO_ROOT")

# SSL証明書ディレクトリ
SSL_DIR="./docker/app/ssl-new"

# 証明書ファイルを検索
find_cert_file() {
    if [ -f "${REPO_ROOT}/${SSL_DIR}/localhost.pem" ]; then
        echo "${REPO_ROOT}/${SSL_DIR}/localhost.pem"
    fi
}

# 強制再生成モード
FORCE_REGENERATE=false
if [ "$1" = "--force" ] || [ "$1" = "-f" ]; then
    FORCE_REGENERATE=true
fi

# 証明書が既に存在する場合
EXISTING_CERT=$(find_cert_file)
if [ -n "$EXISTING_CERT" ] && [ "$FORCE_REGENERATE" = false ]; then
    echo "SSL証明書は既に存在します: $(basename "$EXISTING_CERT")"
    exit 0
fi

# 強制再生成の場合は既存証明書を削除
if [ "$FORCE_REGENERATE" = true ]; then
    echo "既存の証明書を削除して再生成します..."
    rm -f "${REPO_ROOT}/${SSL_DIR}"/localhost.pem "${REPO_ROOT}/${SSL_DIR}"/localhost-key.pem
    rm -f "${REPO_ROOT}/${SSL_DIR}"/localhost+*.pem "${REPO_ROOT}/${SSL_DIR}"/localhost+*-key.pem
    # 旧形式の証明書とrootCAも削除（不要なファイル）
    rm -f "${REPO_ROOT}/${SSL_DIR}"/ocgraph.test+*.pem "${REPO_ROOT}/${SSL_DIR}"/ocgraph.test+*-key.pem
    rm -f "${REPO_ROOT}/${SSL_DIR}"/rootCA.pem
fi

# mkcertがインストールされているか確認
if ! command -v mkcert &> /dev/null; then
    echo "mkcertがインストールされていません。"
    read -p "インストールしますか？ (y/N): " INSTALL_MKCERT
    if [ "$INSTALL_MKCERT" = "y" ] || [ "$INSTALL_MKCERT" = "Y" ]; then
        sudo apt install -y mkcert
    else
        echo "mkcertがないためSSL証明書を生成できません"
        exit 1
    fi
fi

# libnss3-toolsがインストールされているか確認（Chrome/Firefoxの証明書ストアへの登録に必要）
if ! command -v certutil &> /dev/null; then
    echo "libnss3-toolsがインストールされていません。"
    echo "未インストールの場合、Chrome/Firefoxで「保護されていない通信」と表示されます。"
    read -p "インストールしますか？ (y/N): " INSTALL_NSS
    if [ "$INSTALL_NSS" = "y" ] || [ "$INSTALL_NSS" = "Y" ]; then
        sudo apt install -y libnss3-tools
    fi
fi

# mkcertのルートCAがシステムにインストールされているか確認
MKCERT_CAROOT=$(mkcert -CAROOT 2>/dev/null)
if [ -n "$MKCERT_CAROOT" ] && [ -f "${MKCERT_CAROOT}/rootCA.pem" ]; then
    # ルートCAがシステムの信頼済み証明書ストアに登録されているか確認
    ROOT_CA_HASH=$(openssl x509 -hash -noout -in "${MKCERT_CAROOT}/rootCA.pem" 2>/dev/null)
    if [ -n "$ROOT_CA_HASH" ] && ! ls /etc/ssl/certs/${ROOT_CA_HASH}.* &>/dev/null; then
        echo "mkcertのルートCAがシステムに登録されていません。インストールします..."
        mkcert -install
    fi
else
    echo "mkcertのルートCAを生成・インストールします..."
    mkcert -install
fi

# SSLディレクトリを作成
mkdir -p "${REPO_ROOT}/${SSL_DIR}"

# 追加のホスト（IPアドレスまたはホスト名）を取得
ADDITIONAL_HOSTS=""
if [ "$FORCE_REGENERATE" = true ]; then
    echo ""
    echo "LAN内のスマホ/タブレットからHTTPS接続するため、このPCのIPアドレスやホスト名を追加します。"
    echo "例: 192.168.1.100,mypc.local (複数の場合はカンマまたはスペース区切り)"
    echo ""
    read -p "追加ホスト: " INPUT_HOSTS

    if [ -z "$INPUT_HOSTS" ]; then
        echo "追加ホストが指定されませんでした。処理を中断します。"
        exit 0
    fi

    # カンマとスペースを正規化してスペース区切りに変換
    ADDITIONAL_HOSTS=$(echo "$INPUT_HOSTS" | tr ',;' ' ' | tr -s ' ')
    echo ""
fi

# SSL証明書を生成
echo "SSL証明書を生成しています..."
cd "${REPO_ROOT}/${SSL_DIR}" && mkcert localhost 127.0.0.1 ::1 $ADDITIONAL_HOSTS

if [ $? -eq 0 ]; then
    # 生成されたファイルを固定名にリネーム
    GENERATED_CERT=$(ls localhost+*.pem | grep -v key | head -n 1)
    GENERATED_KEY=$(ls localhost+*-key.pem | head -n 1)

    if [ -n "$GENERATED_CERT" ] && [ -n "$GENERATED_KEY" ]; then
        mv "$GENERATED_CERT" localhost.pem
        mv "$GENERATED_KEY" localhost-key.pem
        chmod 644 localhost-key.pem
        echo "SSL証明書の生成が完了しました"
    else
        echo "エラー: 生成された証明書が見つかりません"
        exit 1
    fi

    # 別端末用のルートCA証明書をコピー（make certの時のみ）
    if [ "$FORCE_REGENERATE" = true ]; then
        MKCERT_ROOT=$(mkcert -CAROOT 2>/dev/null)
        if [ -n "$MKCERT_ROOT" ] && [ -f "${MKCERT_ROOT}/rootCA.pem" ]; then
            cp "${MKCERT_ROOT}/rootCA.pem" "${REPO_ROOT}/${REPO_NAME}-rootCA.pem"
            echo "別端末用のルートCA証明書: ./${REPO_NAME}-rootCA.pem"
        fi
    fi
else
    echo "エラー: SSL証明書の生成に失敗しました"
    exit 1
fi
