#!/bin/bash

# リモート環境用データベース初期構築スクリプト
# レンタルサーバー（SSH）、ステージング、開発環境などDocker以外の環境で使用
#
# 使い方:
#   ./setup/init-remote.sh <mysql_user> <mysql_password> <mysql_host> [db_prefix] [lang_filter]
#
# 引数:
#   mysql_user     : MySQLユーザー名
#   mysql_password : MySQLパスワード
#   mysql_host     : MySQLホスト（例: localhost）
#   db_prefix      : データベース名の接頭辞（省略時: ocgraph）
#   lang_filter    : インポートする言語を限定（ja/tw/th、省略時: 全て）
#
# 例:
#   ./setup/init-remote.sh root password localhost
#   ./setup/init-remote.sh myuser mypass localhost myprefix
#   ./setup/init-remote.sh myuser mypass localhost myprefix ja

set -e

# 引数チェック
if [ $# -lt 3 ]; then
    echo "エラー: 引数が不足しています"
    echo ""
    echo "使い方: $0 <mysql_user> <mysql_password> <mysql_host> [db_prefix] [lang_filter]"
    echo ""
    echo "引数:"
    echo "  mysql_user     : MySQLユーザー名"
    echo "  mysql_password : MySQLパスワード"
    echo "  mysql_host     : MySQLホスト（例: localhost）"
    echo "  db_prefix      : データベース名の接頭辞（省略時: ocgraph）"
    echo "  lang_filter    : インポートする言語を限定（ja/tw/th、省略時: 全て）"
    echo ""
    echo "例:"
    echo "  $0 root password localhost"
    echo "  $0 myuser mypass localhost myprefix"
    echo "  $0 myuser mypass localhost myprefix ja"
    exit 1
fi

MYSQL_USER="$1"
MYSQL_PASSWORD="$2"
MYSQL_HOST="$3"
DB_PREFIX="${4:-ocgraph}"  # デフォルトは ocgraph
LANG_FILTER="${5:-}"       # 言語フィルタ（ja/tw/th、省略時は空文字列 = 全て）

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SCHEMA_DIR="$SCRIPT_DIR/schema/mysql"
SQLITE_SCHEMA_DIR="$SCRIPT_DIR/schema/sqlite"
TEMPLATE_DIR="$SCRIPT_DIR/template"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
STORAGE_DIR="$PROJECT_ROOT/storage"

echo "================================"
echo "リモート環境データベース初期構築"
echo "================================"
echo "MySQL Host: $MYSQL_HOST"
echo "MySQL User: $MYSQL_USER"
echo "DB Prefix: $DB_PREFIX"
if [ -n "$LANG_FILTER" ]; then
    echo "言語フィルタ: $LANG_FILTER"
fi
echo ""
echo "⚠️  警告: このスクリプトは以下のデータを完全に初期化します"
echo ""
echo "  【MySQLデータベース】"
if [ -z "$LANG_FILTER" ] || [ "$LANG_FILTER" == "ja" ]; then
    echo "    - ${DB_PREFIX}_ocreview (ja)"
    echo "    - ${DB_PREFIX}_ranking (ja)"
    echo "    - ${DB_PREFIX}_comment (ja)"
fi
if [ -z "$LANG_FILTER" ] || [ "$LANG_FILTER" == "tw" ]; then
    echo "    - ${DB_PREFIX}_ocreviewtw (tw)"
    echo "    - ${DB_PREFIX}_rankingtw (tw)"
    echo "    - ${DB_PREFIX}_commenttw (tw)"
fi
if [ -z "$LANG_FILTER" ] || [ "$LANG_FILTER" == "th" ]; then
    echo "    - ${DB_PREFIX}_ocreviewth (th)"
    echo "    - ${DB_PREFIX}_rankingth (th)"
    echo "    - ${DB_PREFIX}_commentth (th)"
fi
echo "    - ${DB_PREFIX}_userlog"
echo ""
echo "  【SQLiteデータベース】"
echo "    - statistics.db (ja/tw/th)"
echo "    - ranking_position.db (ja/tw/th)"
echo "    - sqlapi.db (ja)"
echo ""
echo "  【その他のデータ】"
echo "    - 既存の.datファイル、ログファイル、サイトマップ"
echo ""
echo "  ※ 既存のテーブルとデータは全て削除されます"
echo ""
read -p "続行しますか? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "キャンセルしました"
    exit 0
fi
echo ""

# 既存データのクリーンアップ
echo "既存データをクリーンアップしています..."
rm -f "$STORAGE_DIR"/*/open_chat_sub_categories/subcategories.json
rm -f "$STORAGE_DIR"/*/static_data_top/*.dat
rm -f "$STORAGE_DIR"/*/static_data_recommend/*/*.dat
rm -f "$STORAGE_DIR"/*/ranking_position/*/*.dat
rm -f "$STORAGE_DIR"/*/ranking_position/*.dat
rm -f "$STORAGE_DIR"/*/SQLite/statistics/*.db*
rm -f "$STORAGE_DIR"/*/SQLite/ranking_position/*.db*
rm -f "$STORAGE_DIR"/*/SQLite/ocgraph_sqlapi/*.db*
rm -f "$STORAGE_DIR"/*.log
rm -f "$STORAGE_DIR"/*/*/*.log
rm -f "$PROJECT_ROOT"/public/sitemaps/*.xml
rm -f "$PROJECT_ROOT"/public/sitemap.xml
echo "  ✓ 完了"
echo ""

# MySQLコマンド構築
MYSQL_CMD="mysql -u${MYSQL_USER} -p${MYSQL_PASSWORD} -h${MYSQL_HOST}"

# スキーマファイルと対応するデータベース名の配列
# データベース名 => スキーマファイル名
declare -A DB_SCHEMA_MAP=(
    ["${DB_PREFIX}_ocreview"]="ocgraph_ocreview_schema.sql"
    ["${DB_PREFIX}_ocreviewtw"]="ocgraph_ocreviewtw_schema.sql"
    ["${DB_PREFIX}_ocreviewth"]="ocgraph_ocreviewth_schema.sql"
    ["${DB_PREFIX}_ranking"]="ocgraph_ranking_schema.sql"
    ["${DB_PREFIX}_rankingtw"]="ocgraph_rankingtw_schema.sql"
    ["${DB_PREFIX}_rankingth"]="ocgraph_rankingth_schema.sql"
    ["${DB_PREFIX}_comment"]="ocgraph_comment_schema.sql"
    ["${DB_PREFIX}_commenttw"]="ocgraph_commenttw_schema.sql"
    ["${DB_PREFIX}_commentth"]="ocgraph_commentth_schema.sql"
    ["${DB_PREFIX}_userlog"]="ocgraph_userlog_schema.sql"
)

# データベースの存在確認関数
check_database_exists() {
    local db_name="$1"
    $MYSQL_CMD -e "USE \`$db_name\`" 2>/dev/null
    return $?
}

echo "データベースの存在確認中..."
echo ""

# 言語フィルタが指定されている場合は対象データベースのみ処理
should_process_db() {
    local db_name="$1"

    # userlog は常に処理
    if [[ "$db_name" == *"userlog"* ]]; then
        return 0
    fi

    # 言語フィルタが指定されていない場合は全て処理
    if [ -z "$LANG_FILTER" ]; then
        return 0
    fi

    # 言語フィルタに応じて判定
    case "$LANG_FILTER" in
        ja)
            # jaの場合は、tw/th のサフィックスがないデータベースのみ
            if [[ ! "$db_name" =~ tw && ! "$db_name" =~ th ]]; then
                return 0
            fi
            ;;
        tw)
            # twの場合は、tw サフィックスのデータベースのみ
            if [[ "$db_name" =~ tw ]]; then
                return 0
            fi
            ;;
        th)
            # thの場合は、th サフィックスのデータベースのみ
            if [[ "$db_name" =~ th ]]; then
                return 0
            fi
            ;;
    esac

    return 1
}

# 各データベースの処理
for db_name in "${!DB_SCHEMA_MAP[@]}"; do
    schema_file="${DB_SCHEMA_MAP[$db_name]}"

    # 言語フィルタでスキップ判定
    if ! should_process_db "$db_name"; then
        continue
    fi

    # スキーマファイルのパスを置換（ocgraph_ を DB_PREFIX に）
    original_schema_file="$schema_file"
    schema_path="$SCHEMA_DIR/$schema_file"

    if [ ! -f "$schema_path" ]; then
        echo "⚠ スキップ: $db_name (スキーマファイル $schema_file が見つかりません)"
        continue
    fi

    echo "→ $db_name を処理中..."

    if check_database_exists "$db_name"; then
        echo "  データベース '$db_name' が存在します"
        echo "  既存のテーブルを削除してスキーマを再作成します..."

        # スキーマファイルの内容を読み込み、DB名を置換してインポート
        if [ "$DB_PREFIX" != "ocgraph" ]; then
            # DB接頭辞が異なる場合は置換してインポート
            sed "s/ocgraph_/${DB_PREFIX}_/g" "$schema_path" | $MYSQL_CMD "$db_name"
        else
            # デフォルトの場合はそのままインポート
            $MYSQL_CMD "$db_name" < "$schema_path"
        fi

        if [ $? -eq 0 ]; then
            echo "  ✓ スキーマを適用しました"
        else
            echo "  ✗ スキーマ適用エラー"
            exit 1
        fi
    else
        echo "  データベース '$db_name' が存在しません"

        if [ "$DB_PREFIX" != "ocgraph" ]; then
            echo "  エラー: カスタム接頭辞が指定されていますが、データベースが存在しません"
            echo "  先にデータベース '$db_name' を作成してください"
            exit 1
        fi

        echo "  データベースを作成してスキーマを適用します..."

        # CREATE DATABASE権限がない場合を考慮してエラーハンドリング
        if ! $MYSQL_CMD < "$schema_path" 2>&1; then
            echo "  ✗ エラー: データベースの作成に失敗しました"
            echo "  CREATE DATABASE権限がない可能性があります"
            echo "  先に管理者にデータベース '$db_name' の作成を依頼してください"
            exit 1
        fi

        echo "  ✓ データベースを作成しました"
    fi

    echo ""
done

echo "================================"
echo "SQLiteデータベース初期化"
echo "================================"
echo ""

# テンプレートファイルをコピー（常に全言語分処理）
echo "テンプレートファイルをコピーしています..."
for lang in ja tw th; do
    echo "  → ${lang} のテンプレートをコピー中..."
    mkdir -p "$STORAGE_DIR/${lang}/static_data_top"
    cp -f "$TEMPLATE_DIR/static_data_top/"* "$STORAGE_DIR/${lang}/static_data_top/" 2>/dev/null || true
done
echo "  ✓ 完了"
echo ""

# SQLiteデータベースを生成（常に全言語分処理）
echo "スキーマファイルからSQLiteデータベースを生成しています..."

for lang in ja tw th; do
    echo "  → ${lang} のデータベースを生成中..."

    # ディレクトリ作成
    mkdir -p "$STORAGE_DIR/${lang}/SQLite/statistics"
    mkdir -p "$STORAGE_DIR/${lang}/SQLite/ranking_position"

    # statistics.db を生成
    if [ -f "$SQLITE_SCHEMA_DIR/statistics.sql" ]; then
        sqlite3 "$STORAGE_DIR/${lang}/SQLite/statistics/statistics.db" < "$SQLITE_SCHEMA_DIR/statistics.sql"
        echo "    ✓ statistics.db"
    fi

    # ranking_position.db を生成
    if [ -f "$SQLITE_SCHEMA_DIR/ranking_position.sql" ]; then
        sqlite3 "$STORAGE_DIR/${lang}/SQLite/ranking_position/ranking_position.db" < "$SQLITE_SCHEMA_DIR/ranking_position.sql"
        echo "    ✓ ranking_position.db"
    fi

    # sqlapi.db を生成（jaのみ）
    if [ "$lang" == "ja" ] && [ -f "$SQLITE_SCHEMA_DIR/sqlapi.sql" ]; then
        mkdir -p "$STORAGE_DIR/${lang}/SQLite/ocgraph_sqlapi"
        sqlite3 "$STORAGE_DIR/${lang}/SQLite/ocgraph_sqlapi/sqlapi.db" < "$SQLITE_SCHEMA_DIR/sqlapi.sql"
        echo "    ✓ sqlapi.db"
    fi
done

echo "  ✓ 完了"
echo ""

echo "================================"
echo "初期構築完了"
echo "================================"
echo ""
echo "作成されたMySQLデータベース:"
$MYSQL_CMD -e "SHOW DATABASES LIKE '${DB_PREFIX}_%';"
echo ""
echo "作成されたSQLiteデータベース:"
find "$STORAGE_DIR" -name "*.db" -type f
echo ""
