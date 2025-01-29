#!/bin/bash

# 変数をダミーで連想配列に初期化（後で import-mysql-from-server.env で上書きされる）
declare -A CONFIG_VARS=(
  [REMOTE_SERVER]=""
  [REMOTE_USER]=""
  [REMOTE_PORT]=""
  [REMOTE_KEY]=""
  [REMOTE_MYSQL_USER]=""
  [REMOTE_MYSQL_PASS]=""
  [REMOTE_DUMP_DIR]=""
  [LOCAL_MYSQL_USER]=""
  [LOCAL_MYSQL_PASS]=""
  [LOCAL_MYSQL_HOST]=""
  [LOCAL_IMPORT_DIR]=""
)

# 外部ファイルを読み込む
if [ -f /var/www/html/batch/sh/import-mysql-from-server.env ]; then
  source /var/www/html/batch/sh/import-mysql-from-server.env
else
  echo "Error: import-mysql-from-server.env ファイルが見つかりません。" >&2
  exit 1
fi

# 必須変数がすべて設定されているかチェック
for VAR in "${!CONFIG_VARS[@]}"; do
  if [ -z "${CONFIG_VARS[$VAR]}" ]; then
    echo "Error: 必須変数 $VAR が設定されていません。" >&2
    exit 1
  fi
done

# ダンプ元とインポート先テーブルの対応を配列で定義
declare -A TABLE_MAP=(
  [ocgraph_ocreview]="ocgraph_ocreview"
  [ocgraph_ocreviewtw]="ocgraph_ocreviewtw"
  [ocgraph_ocreviewth]="ocgraph_ocreviewth"
  [ocgraph_ranking]="ocgraph_ranking"
  [ocgraph_rankingtw]="ocgraph_rankingtw"
  [ocgraph_rankingth]="ocgraph_rankingth"
  [ocgraph_userlog]="ocgraph_userlog"
  [ocgraph_comment]="ocgraph_comment"
  [ocgraph_commenttw]="ocgraph_commenttw"
  [ocgraph_commentth]="ocgraph_commentth"
)

# 配列をリモートサーバーに渡すために、キーと値をそれぞれエクスポート
TABLE_KEYS=$(echo "${!TABLE_MAP[@]}" | tr ' ' '\n')
TABLE_VALUES=$(echo "${TABLE_MAP[@]}" | tr ' ' '\n')

# SSHでリモートサーバーに接続してダンプを実行
echo "リモートサーバーに接続してダンプを実行中..."
ssh -i "${CONFIG_VARS[REMOTE_KEY]}" -p "${CONFIG_VARS[REMOTE_PORT]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}" <<EOF
  # ダンプディレクトリの準備
  mkdir -p ${CONFIG_VARS[REMOTE_DUMP_DIR]}
  rm -r ${CONFIG_VARS[REMOTE_DUMP_DIR]}/*

  # テーブル名をループで処理
  TABLE_KEYS=($TABLE_KEYS)
  TABLE_VALUES=($TABLE_VALUES)
  for i in \${!TABLE_KEYS[@]}; do
    SOURCE_TABLE=\${TABLE_KEYS[\$i]}
    FILE_NAME=\${TABLE_VALUES[\$i]}.sql
    mysqldump -u "${CONFIG_VARS[REMOTE_MYSQL_USER]}" -p"${CONFIG_VARS[REMOTE_MYSQL_PASS]}" --add-drop-table \$SOURCE_TABLE > "${CONFIG_VARS[REMOTE_DUMP_DIR]}/\$FILE_NAME"
  done
EOF

# SSHコマンドが失敗した場合にエラーハンドリング
if [ $? -ne 0 ]; then
  echo "Error: SSHでリモートサーバーへの接続またはダンプ実行に失敗しました。" >&2
  exit 1
fi

# ローカルで既存のインポートディレクトリを初期化
echo "ローカルインポートディレクトリを初期化中..."
rm -r "${CONFIG_VARS[LOCAL_IMPORT_DIR]}"/*
mkdir -p "${CONFIG_VARS[LOCAL_IMPORT_DIR]}"

# SCPでダウンロードとローカルインポート
for SOURCE_TABLE in "${!TABLE_MAP[@]}"; do
  LOCAL_TABLE="${TABLE_MAP[$SOURCE_TABLE]}"
  FILE_NAME="$LOCAL_TABLE.sql"

  # ファイルをSCPで取得
  echo "SCPでファイルを取得中: ${FILE_NAME}"
  scp -P "${CONFIG_VARS[REMOTE_PORT]}" -i "${CONFIG_VARS[REMOTE_KEY]}" "${CONFIG_VARS[REMOTE_USER]}@${CONFIG_VARS[REMOTE_SERVER]}:${CONFIG_VARS[REMOTE_DUMP_DIR]}/$FILE_NAME" "${CONFIG_VARS[LOCAL_IMPORT_DIR]}/"

  # SCPコマンドが失敗した場合にエラーハンドリング
  if [ $? -ne 0 ]; then
    echo "Error: SCPでファイルの取得に失敗しました。" >&2
    exit 1
  fi

  # ローカルMySQLにインポート
  echo "ローカルMySQLにインポート中: ${LOCAL_TABLE}"

  # ローカルMySQLにデータベースが存在しない場合、作成
  mysql -h"${CONFIG_VARS[LOCAL_MYSQL_HOST]}" -u"${CONFIG_VARS[LOCAL_MYSQL_USER]}" -p"${CONFIG_VARS[LOCAL_MYSQL_PASS]}" -e "CREATE DATABASE IF NOT EXISTS \`${LOCAL_TABLE}\`;"

  # データベースにインポート
  mysql -h"${CONFIG_VARS[LOCAL_MYSQL_HOST]}" -u"${CONFIG_VARS[LOCAL_MYSQL_USER]}" -p"${CONFIG_VARS[LOCAL_MYSQL_PASS]}" "$LOCAL_TABLE" <"${CONFIG_VARS[LOCAL_IMPORT_DIR]}/$FILE_NAME"

  # MySQLコマンドが失敗した場合にエラーハンドリング
  if [ $? -ne 0 ]; then
    echo "Error: MySQLへのインポートに失敗しました。" >&2
    exit 1
  fi
done

# 終了メッセージ
echo "全てのテーブルのダンプとインポートが完了しました。"
