#!/bin/bash

# リモートサーバーから画像ディレクトリを同期するスクリプト
# 本番環境のpublic/oc-img* ディレクトリをSCPでダウンロード

set -e  # エラーが発生したら即座に終了

# スクリプトのディレクトリを取得（batch/sh）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# プロジェクトルートを取得（batch/sh の2階層上）
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# 共通設定を読み込む
source "${SCRIPT_DIR}/lib/remote-config.sh"

# 必須変数のチェック
validate_required_vars "REMOTE_SERVER" "REMOTE_USER" "REMOTE_PORT" "REMOTE_KEY"

echo "========================================"
echo "リモートサーバーから画像を同期"
echo "========================================"
echo ""

# ========================================
# 画像ディレクトリの同期
# ========================================

echo "----------------------------------------"
echo "画像ディレクトリを同期中..."
echo "----------------------------------------"
echo ""

# リモートの画像ディレクトリパス
REMOTE_IMG_BASE="${CONFIG_VARS[REMOTE_IMG_BASE]}"

# ローカルの画像ディレクトリパス
LOCAL_IMG_BASE="${PROJECT_ROOT}/public"

# 同期する画像ディレクトリのリスト
IMG_DIRS=(
  "oc-img"
  "oc-img-th"
  "oc-img-tw"
)

for DIR_NAME in "${IMG_DIRS[@]}"; do
  echo "同期中: ${DIR_NAME}"

  # SCPで直接ディレクトリを同期
  scp -r -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" \
    "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:${REMOTE_IMG_BASE}/${DIR_NAME}" \
    "${LOCAL_IMG_BASE}/"

  if [ $? -ne 0 ]; then
    echo "Warning: ${DIR_NAME} の同期に失敗しました。" >&2
  else
    echo "✓ ${DIR_NAME} を同期しました。"
  fi

  echo ""
done

# ========================================
# 完了
# ========================================

echo "========================================"
echo "✓ 全ての画像同期が完了しました！"
echo "========================================"
echo ""
echo "同期されたディレクトリ:"
for DIR_NAME in "${IMG_DIRS[@]}"; do
  if [ -d "${LOCAL_IMG_BASE}/${DIR_NAME}" ]; then
    FILE_COUNT=$(find "${LOCAL_IMG_BASE}/${DIR_NAME}" -type f | wc -l)
    echo "  - ${DIR_NAME}: ${FILE_COUNT} ファイル"
  fi
done

exit 0
