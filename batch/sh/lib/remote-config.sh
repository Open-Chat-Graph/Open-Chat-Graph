#!/bin/bash

# リモートサーバー接続設定を読み込む共通ライブラリ
# 使用方法: source "${SCRIPT_DIR}/lib/remote-config.sh"

# 変数をダミーで連想配列に初期化（後で import-mysql-from-server.env で上書きされる）
declare -g -A CONFIG_VARS=(
  [REMOTE_SERVER]=""
  [REMOTE_USER]=""
  [REMOTE_PORT]=""
  [REMOTE_KEY]=""
  [REMOTE_MYSQL_USER]=""
  [REMOTE_MYSQL_PASS]=""
  [REMOTE_DUMP_DIR]=""
  [REMOTE_STORAGE_DIR]=""
  [LOCAL_MYSQL_USER]=""
  [LOCAL_MYSQL_PASS]=""
  [LOCAL_MYSQL_HOST]=""
  [LOCAL_IMPORT_DIR]=""
  [LOCAL_STORAGE_DIR]=""
)

# データベーステーブルのマッピングをダミーで初期化（後で import-mysql-from-server.env で上書きされる）
declare -g -A TABLE_MAP=()

# スクリプトのディレクトリを取得（batch/sh）
if [ -z "${SCRIPT_DIR}" ]; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fi

# プロジェクトルートを取得（batch/sh の2階層上）
if [ -z "${PROJECT_ROOT}" ]; then
  PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
fi

# 外部ファイルを読み込む
if [ -f "${SCRIPT_DIR}/import-mysql-from-server.env" ]; then
  source "${SCRIPT_DIR}/import-mysql-from-server.env"
else
  echo "Error: import-mysql-from-server.env ファイルが見つかりません。" >&2
  exit 1
fi

# 必須変数がすべて設定されているかチェック
validate_required_vars() {
  local required_vars=("$@")
  for VAR in "${required_vars[@]}"; do
    if [ -z "${CONFIG_VARS[$VAR]}" ]; then
      echo "Error: 必須変数 $VAR が設定されていません。" >&2
      exit 1
    fi
  done
}

# TABLE_MAPが設定されているかチェック
if [ ${#TABLE_MAP[@]} -eq 0 ]; then
  echo "Error: TABLE_MAP が設定されていません。" >&2
  exit 1
fi

# 言語コード
declare -g -a LANG_CODES=(
  "ja"
  "tw"
  "th"
)
