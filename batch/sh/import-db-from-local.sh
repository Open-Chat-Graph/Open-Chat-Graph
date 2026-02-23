#!/bin/bash

# ローカル開発環境用MySQLインポートスクリプト
# batch/sh/sqldump/ 以下のSQLファイルをMySQLにインポート
#
# 使用方法:
#   docker compose exec app bash batch/sh/import-db-from-local.sh
#
# 環境変数でオーバーライド:
#   MYSQL_HOST=localhost MYSQL_USER=user MYSQL_PASS=pass bash batch/sh/import-db-from-local.sh
#
# 引数で指定:
#   bash batch/sh/import-db-from-local.sh [HOST] [USER] [PASS]

set -e  # エラーが発生したら即座に終了

# カラー出力用
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# ========================================
# 設定（引数 → 環境変数 → デフォルト値の優先順位）
# ========================================
MYSQL_HOST="${1:-${MYSQL_HOST:-mysql}}"  # 引数1 or 環境変数 or デフォルト
MYSQL_USER="${2:-${MYSQL_USER:-root}}"   # 引数2 or 環境変数 or デフォルト
MYSQL_PASS="${3:-${MYSQL_PASS:-test_root_pass}}"  # 引数3 or 環境変数 or デフォルト
DUMP_DIR="batch/sh/sqldump"
CHARSET="utf8mb4"
COLLATION="utf8mb4_unicode_ci"

# インポート対象データベースリスト
declare -a DATABASES=(
  "ocgraph_ocreview"
  "ocgraph_ocreviewtw"
  "ocgraph_ocreviewth"
  "ocgraph_ranking"
  "ocgraph_rankingtw"
  "ocgraph_rankingth"
  "ocgraph_userlog"
  "ocgraph_comment"
  "ocgraph_commenttw"
  "ocgraph_commentth"
)

# スクリプトのディレクトリを取得（batch/sh）
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# プロジェクトルートを取得（batch/sh の2階層上）
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}MySQL Database Import Script${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# 設定値の表示
echo -e "${YELLOW}Configuration:${NC}"
echo -e "  Host: ${MYSQL_HOST}"
echo -e "  User: ${MYSQL_USER}"
echo -e "  Dump Directory: ${PROJECT_ROOT}/${DUMP_DIR}"
echo ""

# MySQLサーバーへの接続確認
echo -e "${YELLOW}Checking MySQL connection...${NC}"
if ! mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" -e "SELECT 1;" > /dev/null 2>&1; then
  echo -e "${RED}Error: Cannot connect to MySQL server at '$MYSQL_HOST'.${NC}"
  echo -e "${YELLOW}Please check if the database container is running.${NC}"
  exit 1
fi
echo -e "${GREEN}✓ MySQL connection successful${NC}"
echo ""

# ダンプディレクトリの確認
if [ ! -d "$PROJECT_ROOT/$DUMP_DIR" ]; then
  echo -e "${RED}Error: Dump directory '$PROJECT_ROOT/$DUMP_DIR' not found.${NC}"
  exit 1
fi

# 各データベースをインポート
TOTAL=${#DATABASES[@]}
CURRENT=0

for DB_NAME in "${DATABASES[@]}"; do
  CURRENT=$((CURRENT + 1))
  SQL_FILE="$PROJECT_ROOT/$DUMP_DIR/${DB_NAME}.sql"

  echo -e "${YELLOW}[$CURRENT/$TOTAL] Processing: ${DB_NAME}${NC}"

  # SQLファイルの存在確認
  if [ ! -f "$SQL_FILE" ]; then
    echo -e "${RED}  ✗ SQL file not found: $SQL_FILE${NC}"
    continue
  fi

  # データベース作成
  echo -e "  Creating database..."
  mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET ${CHARSET} COLLATE ${COLLATION};" 2>/dev/null

  if [ $? -ne 0 ]; then
    echo -e "${RED}  ✗ Failed to create database${NC}"
    exit 1
  fi

  # データインポート
  echo -e "  Importing data..."
  mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$SQL_FILE" 2>/dev/null

  if [ $? -ne 0 ]; then
    echo -e "${RED}  ✗ Failed to import data${NC}"
    exit 1
  fi

  echo -e "${GREEN}  ✓ Successfully imported${NC}"
  echo ""
done

# コメントDBに comment_image テーブルが無ければ作成
echo -e "${YELLOW}Ensuring comment_image table exists...${NC}"
COMMENT_IMAGE_SQL="CREATE TABLE IF NOT EXISTS comment_image (
  id int NOT NULL AUTO_INCREMENT,
  comment_id int NOT NULL,
  filename varchar(255) NOT NULL,
  sort_order tinyint NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_comment_id (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

for COMMENT_DB in ocgraph_comment ocgraph_commenttw ocgraph_commentth; do
  mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" "$COMMENT_DB" -e "$COMMENT_IMAGE_SQL" 2>/dev/null
  echo -e "${GREEN}  ✓ ${COMMENT_DB}.comment_image${NC}"
done
echo ""

# 完了メッセージ
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}All databases imported successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# インポートされたデータベース一覧を表示
echo -e "${YELLOW}Imported databases:${NC}"
mysql -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" \
  -e "SHOW DATABASES;" 2>/dev/null | grep "ocgraph_" | sed 's/^/  - /'

exit 0
