#!/bin/bash

# ローカル開発環境用MySQLインポートスクリプト
# batch/sh/sqldump/ 以下のSQLファイルをDockerコンテナのMySQLにインポート

set -e  # エラーが発生したら即座に終了

# カラー出力用
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 設定
MYSQL_CONTAINER="oc-review-dev-mysql-1"
MYSQL_USER="root"
MYSQL_PASS="test_root_pass"
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

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}MySQL Database Import Script${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Dockerコンテナが起動しているか確認
echo -e "${YELLOW}Checking Docker container...${NC}"
if ! docker ps | grep -q "$MYSQL_CONTAINER"; then
  echo -e "${RED}Error: MySQL container '$MYSQL_CONTAINER' is not running.${NC}"
  echo -e "${YELLOW}Please start the container with: docker-compose up -d${NC}"
  exit 1
fi
echo -e "${GREEN}✓ Docker container is running${NC}"
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
  docker exec "$MYSQL_CONTAINER" mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET ${CHARSET} COLLATE ${COLLATION};" 2>/dev/null

  if [ $? -ne 0 ]; then
    echo -e "${RED}  ✗ Failed to create database${NC}"
    exit 1
  fi

  # データインポート
  echo -e "  Importing data..."
  docker exec -i "$MYSQL_CONTAINER" mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" "$DB_NAME" < "$SQL_FILE" 2>/dev/null

  if [ $? -ne 0 ]; then
    echo -e "${RED}  ✗ Failed to import data${NC}"
    exit 1
  fi

  echo -e "${GREEN}  ✓ Successfully imported${NC}"
  echo ""
done

# 完了メッセージ
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}All databases imported successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# インポートされたデータベース一覧を表示
echo -e "${YELLOW}Imported databases:${NC}"
docker exec "$MYSQL_CONTAINER" mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" \
  -e "SHOW DATABASES;" 2>/dev/null | grep "ocgraph_" | sed 's/^/  - /'

exit 0
