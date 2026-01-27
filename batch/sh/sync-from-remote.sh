#!/bin/bash

# リモートサーバーからデータベースとstorageファイルを同期するスクリプト
# 本番環境のMySQLデータベースをダンプ → ローカルにインポート
# 本番環境のstorageファイルをSCPでダウンロード

set -e  # エラーが発生したら即座に終了

# スクリプトのディレクトリを取得（batch/sh）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# プロジェクトルートを取得（batch/sh の2階層上）
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# 共通設定を読み込む
source "${SCRIPT_DIR}/lib/remote-config.sh"

# 必須変数のチェック
validate_required_vars "REMOTE_SERVER" "REMOTE_USER" "REMOTE_PORT" "REMOTE_KEY" \
  "REMOTE_MYSQL_USER" "REMOTE_MYSQL_PASS" "REMOTE_DUMP_DIR" "REMOTE_STORAGE_DIR" \
  "LOCAL_MYSQL_USER" "LOCAL_MYSQL_PASS" "LOCAL_MYSQL_HOST" \
  "LOCAL_IMPORT_DIR" "LOCAL_STORAGE_DIR"

echo "========================================"
echo "リモートサーバーからデータを同期"
echo "========================================"
echo ""

# ========================================
# 0. データベース存在確認
# ========================================

echo "----------------------------------------"
echo "0/3: データベース存在確認中..."
echo "----------------------------------------"
echo ""

# リモートサーバーでのDB存在確認
echo "リモートサーバーのデータベース存在確認中..."
REMOTE_DB_CHECK=$(ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" bash -s "${CONFIG_VARS[REMOTE_MYSQL_USER]}" "${CONFIG_VARS[REMOTE_MYSQL_PASS]}" "${!TABLE_MAP[@]}" <<'EOFREMOTE'
  set -eo pipefail  # エラーが発生したら即座に終了

  MYSQL_USER=$1
  MYSQL_PASS=$2
  shift 2
  for DB_NAME in "$@"; do
    DB_EXISTS=$(MYSQL_PWD="$MYSQL_PASS" mysql -u "$MYSQL_USER" \
      -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$DB_NAME';" -sN)
    if [ -z "$DB_EXISTS" ]; then
      echo "MISSING:$DB_NAME"
    fi
  done
EOFREMOTE
)

if [ -n "$REMOTE_DB_CHECK" ]; then
  echo "Error: リモートサーバーに以下のデータベースが存在しません:" >&2
  echo "$REMOTE_DB_CHECK" | sed 's/^MISSING:/  - /' >&2
  exit 1
fi

echo "✓ リモートサーバーのデータベースが全て存在します。"
echo ""

# ローカルサーバーでのDB存在確認
echo "ローカルサーバーのデータベース存在確認中..."
for LOCAL_DB in "${TABLE_MAP[@]}"; do
  DB_EXISTS=$(mysql -h"${CONFIG_VARS[LOCAL_MYSQL_HOST]}" -u"${CONFIG_VARS[LOCAL_MYSQL_USER]}" -p"${CONFIG_VARS[LOCAL_MYSQL_PASS]}" \
    -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${LOCAL_DB}';" -sN)

  if [ -z "$DB_EXISTS" ]; then
    echo "Error: ローカルに以下のデータベースが存在しません: ${LOCAL_DB}" >&2
    echo "       CREATE DATABASE権限がない環境では、事前に空のデータベースを作成してください。" >&2
    exit 1
  fi
done

echo "✓ ローカルサーバーのデータベースが全て存在します。"
echo ""
echo "  注意: ローカルDBの既存テーブルは全て削除されます（DROP TABLE）"
echo ""

# ========================================
# 1. MySQLデータベースの同期
# ========================================

echo "----------------------------------------"
echo "1/3: MySQLデータベースを同期中..."
echo "----------------------------------------"
echo ""

# ダンプディレクトリの初期化（リモート）
echo "リモートサーバーのダンプディレクトリを初期化中..."
ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" \
  "mkdir -p '${CONFIG_VARS[REMOTE_DUMP_DIR]}' && rm -rf '${CONFIG_VARS[REMOTE_DUMP_DIR]}'/*"

if [ $? -ne 0 ]; then
  echo "Error: リモートサーバーのダンプディレクトリ初期化に失敗しました。" >&2
  exit 1
fi

# 各データベースを個別にダンプ
for SOURCE_DB in "${!TABLE_MAP[@]}"; do
  LOCAL_DB="${TABLE_MAP[$SOURCE_DB]}"
  FILE_NAME="$LOCAL_DB.sql"

  echo "  ダンプ中: $SOURCE_DB → $FILE_NAME"

  ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" \
    bash -s "${CONFIG_VARS[REMOTE_MYSQL_USER]}" "${CONFIG_VARS[REMOTE_MYSQL_PASS]}" "${SOURCE_DB}" "${CONFIG_VARS[REMOTE_DUMP_DIR]}" "${FILE_NAME}" <<'EOFREMOTE'
set -eo pipefail
MYSQL_USER=$1
MYSQL_PASS=$2
SOURCE_DB=$3
DUMP_DIR=$4
FILE_NAME=$5
MYSQL_PWD="$MYSQL_PASS" mysqldump -u "$MYSQL_USER" --add-drop-table --databases "$SOURCE_DB" > "$DUMP_DIR/$FILE_NAME"
EOFREMOTE

  if [ $? -ne 0 ]; then
    echo "Error: ${SOURCE_DB} のダンプに失敗しました。" >&2
    exit 1
  fi
done

echo "✓ リモートサーバーでのダンプが完了しました。"
echo ""

# ローカルで既存のインポートディレクトリを初期化
echo "ローカルインポートディレクトリを初期化中..."
rm -rf "${CONFIG_VARS[LOCAL_IMPORT_DIR]}"/*
mkdir -p "${CONFIG_VARS[LOCAL_IMPORT_DIR]}"

# SCPでダウンロードとローカルインポート
for SOURCE_TABLE in "${!TABLE_MAP[@]}"; do
  LOCAL_TABLE="${TABLE_MAP[$SOURCE_TABLE]}"
  FILE_NAME="$LOCAL_TABLE.sql"

  # ファイルをSCPで取得
  echo "SCPでファイルを取得中: ${FILE_NAME}"
  scp -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" \
    "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:${CONFIG_VARS[REMOTE_DUMP_DIR]}/$FILE_NAME" \
    "${CONFIG_VARS[LOCAL_IMPORT_DIR]}/"

  if [ $? -ne 0 ]; then
    echo "Error: SCPでファイルの取得に失敗しました。" >&2
    exit 1
  fi

  # ローカルMySQLにインポート（既存テーブルはDROP TABLEされる）
  echo "ローカルMySQLにインポート中: ${LOCAL_TABLE}"

  # データベースにインポート
  mysql -h"${CONFIG_VARS[LOCAL_MYSQL_HOST]}" -u"${CONFIG_VARS[LOCAL_MYSQL_USER]}" -p"${CONFIG_VARS[LOCAL_MYSQL_PASS]}" \
    "$LOCAL_TABLE" < "${CONFIG_VARS[LOCAL_IMPORT_DIR]}/$FILE_NAME"

  if [ $? -ne 0 ]; then
    echo "Error: MySQLへのインポートに失敗しました。" >&2
    exit 1
  fi
done

echo "✓ 全てのデータベースのインポートが完了しました。"
echo ""

# ========================================
# 2. storageファイルの同期
# ========================================

echo "----------------------------------------"
echo "2/3: storageファイルを同期中..."
echo "----------------------------------------"
echo ""

for code in "${LANG_CODES[@]}"; do
  FILE_NAME="${code}/*"
  echo "SCPでstorageファイルを取得中: ${FILE_NAME}"

  # ローカルディレクトリを作成
  mkdir -p "${CONFIG_VARS[LOCAL_STORAGE_DIR]}/${code}"

  scp -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" -r \
    "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:${CONFIG_VARS[REMOTE_STORAGE_DIR]}/$FILE_NAME" \
    "${CONFIG_VARS[LOCAL_STORAGE_DIR]}/${code}/"

  if [ $? -ne 0 ]; then
    echo "Warning: ${code} のstorageファイルの取得に失敗しました。" >&2
  else
    echo "✓ ${code} のstorageファイルを取得しました。"
  fi
done

echo ""

# ========================================
# 完了
# ========================================

echo "========================================"
echo "✓ 全ての同期が完了しました！"
echo "========================================"
echo ""
echo "同期されたデータベース:"
mysql -h"${CONFIG_VARS[LOCAL_MYSQL_HOST]}" -u"${CONFIG_VARS[LOCAL_MYSQL_USER]}" -p"${CONFIG_VARS[LOCAL_MYSQL_PASS]}" \
  -e "SHOW DATABASES;" 2>/dev/null | grep "ocgraph_" | sed 's/^/  - /'

exit 0
