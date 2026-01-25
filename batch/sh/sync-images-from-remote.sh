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

# 一時ディレクトリ
TEMP_DIR="${PROJECT_ROOT}/batch/sh/tmp-images"
mkdir -p "${TEMP_DIR}"

# 同期する画像ディレクトリのリスト
IMG_DIRS=(
  "oc-img"
  "oc-img-th"
  "oc-img-tw"
)

echo "リモートサーバーで画像をzip圧縮中..."
ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" <<EOF
  cd ${REMOTE_IMG_BASE}
  for DIR_NAME in ${IMG_DIRS[@]}; do
    echo "  圧縮中: \$DIR_NAME"
    zip -r -q "/tmp/\${DIR_NAME}.zip" "\$DIR_NAME" -x "*/preview/default/*" "*/default/*"
  done
EOF

if [ $? -ne 0 ]; then
  echo "Error: リモートサーバーでのzip圧縮に失敗しました。" >&2
  exit 1
fi

echo "✓ リモートサーバーでの圧縮が完了しました。"
echo ""

for DIR_NAME in "${IMG_DIRS[@]}"; do
  echo "SCPでzipファイルを取得中: ${DIR_NAME}.zip"

  # zipファイルをSCPで取得
  scp -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" \
    "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:/tmp/${DIR_NAME}.zip" \
    "${TEMP_DIR}/"

  if [ $? -ne 0 ]; then
    echo "Warning: ${DIR_NAME}.zip の取得に失敗しました。" >&2
    continue
  fi

  echo "✓ ${DIR_NAME}.zip を取得しました。"

  # 解凍
  echo "解凍中: ${DIR_NAME}"
  unzip -q -o "${TEMP_DIR}/${DIR_NAME}.zip" -d "${LOCAL_IMG_BASE}/"

  if [ $? -ne 0 ]; then
    echo "Warning: ${DIR_NAME}.zip の解凍に失敗しました。" >&2
  else
    echo "✓ ${DIR_NAME} を解凍しました。"
  fi

  # ローカルの一時ファイルを削除
  rm -f "${TEMP_DIR}/${DIR_NAME}.zip"

  echo ""
done

# リモートの一時ファイルを削除
echo "リモートサーバーの一時ファイルを削除中..."
ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" <<EOF
  for DIR_NAME in ${IMG_DIRS[@]}; do
    rm -f "/tmp/\${DIR_NAME}.zip"
  done
EOF

# ローカルの一時ディレクトリを削除
rm -rf "${TEMP_DIR}"

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
