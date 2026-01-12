#!/bin/bash

# データベース初期構築スクリプト
# 使い方: ./setup/init-database.sh

set -e

MYSQL_CONTAINER="oc-review-mock-mysql-1"
SCHEMA_DIR="$(cd "$(dirname "$0")/schema/mysql" && pwd)"
MYSQL_USER="root"
MYSQL_PASSWORD="test_root_pass"

echo "================================"
echo "データベース初期構築"
echo "================================"
echo "Container: $MYSQL_CONTAINER"
echo "User: $MYSQL_USER"
echo ""

# コンテナが起動しているか確認
if ! docker ps --format '{{.Names}}' | grep -q "^${MYSQL_CONTAINER}$"; then
    echo "エラー: コンテナ ${MYSQL_CONTAINER} が起動していません"
    echo "先に環境を起動してください:"
    echo "  make up"
    exit 1
fi

# MySQLコマンド構築（Docker exec経由）
MYSQL_CMD="docker exec -i $MYSQL_CONTAINER mysql -u$MYSQL_USER -p$MYSQL_PASSWORD"

# スキーマファイルの順序（依存関係を考慮）
SCHEMA_FILES=(
    "ocgraph_ocreview_schema.sql"
    "ocgraph_ocreviewtw_schema.sql"
    "ocgraph_ocreviewth_schema.sql"
    "ocgraph_ranking_schema.sql"
    "ocgraph_rankingtw_schema.sql"
    "ocgraph_rankingth_schema.sql"
    "ocgraph_comment_schema.sql"
    "ocgraph_commenttw_schema.sql"
    "ocgraph_commentth_schema.sql"
    "ocgraph_userlog_schema.sql"
)

echo "スキーマファイルを適用中..."
echo ""

for schema_file in "${SCHEMA_FILES[@]}"; do
    schema_path="$SCHEMA_DIR/$schema_file"

    if [ ! -f "$schema_path" ]; then
        echo "⚠ スキップ: $schema_file (ファイルが見つかりません)"
        continue
    fi

    db_name=$(echo "$schema_file" | sed 's/_schema\.sql$//')
    echo "→ $db_name を作成中..."

    $MYSQL_CMD < "$schema_path"

    if [ $? -eq 0 ]; then
        echo "  ✓ 完了"
    else
        echo "  ✗ エラー"
        exit 1
    fi
done

echo ""
echo "================================"
echo "初期構築完了"
echo "================================"
echo ""
echo "作成されたデータベース:"
$MYSQL_CMD -e "SHOW DATABASES LIKE 'ocgraph_%';"
