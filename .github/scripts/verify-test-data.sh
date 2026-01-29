#!/bin/bash
# テスト環境のデータを検証するスクリプト
# MySQLテーブルのレコード数の存在を確認

set -e

# CI環境判定とCompose設定
if [ -n "$CI" ]; then
    COMPOSE_FILES="-f docker-compose.yml -f docker-compose.ci.yml"
else
    COMPOSE_FILES="-f docker-compose.yml -f docker-compose.mock.yml"
fi

COMPOSE_CMD="docker compose ${COMPOSE_FILES}"

# コンテナ名を動的に取得
APP_CONTAINER=$(${COMPOSE_CMD} ps -q app 2>/dev/null | xargs -r docker inspect --format='{{.Name}}' | sed 's/^.\{1\}//')
MYSQL_CONTAINER=$(${COMPOSE_CMD} ps -q mysql 2>/dev/null | xargs -r docker inspect --format='{{.Name}}' | sed 's/^.\{1\}//')

# 色付き出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# カウンター
PASSED=0
FAILED=0
TOTAL=0

# ログ関数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# テストの成否を記録
test_result() {
    local name=$1
    local expected=$2
    local actual=$3
    local comparison=$4  # "ge" (>=), "eq" (=), "le" (<=), "gt" (>), "lt" (<)

    TOTAL=$((TOTAL + 1))

    local passed=false
    case $comparison in
        "ge")
            if [ "$actual" -ge "$expected" ]; then
                passed=true
            fi
            ;;
        "eq")
            if [ "$actual" -eq "$expected" ]; then
                passed=true
            fi
            ;;
        "le")
            if [ "$actual" -le "$expected" ]; then
                passed=true
            fi
            ;;
        "gt")
            if [ "$actual" -gt "$expected" ]; then
                passed=true
            fi
            ;;
        "lt")
            if [ "$actual" -lt "$expected" ]; then
                passed=true
            fi
            ;;
    esac

    if [ "$passed" = true ]; then
        PASSED=$((PASSED + 1))
        case $comparison in
            "ge") log_success "${name}: ${actual}件 (>= ${expected}件)" ;;
            "eq") log_success "${name}: ${actual}件 (= ${expected}件)" ;;
            "le") log_success "${name}: ${actual}件 (<= ${expected}件)" ;;
            "gt") log_success "${name}: ${actual}件 (> ${expected}件)" ;;
            "lt") log_success "${name}: ${actual}件 (< ${expected}件)" ;;
        esac
    else
        FAILED=$((FAILED + 1))
        case $comparison in
            "ge") log_error "${name}: ${actual}件 (期待: >= ${expected}件)" ;;
            "eq") log_error "${name}: ${actual}件 (期待: = ${expected}件)" ;;
            "le") log_error "${name}: ${actual}件 (期待: <= ${expected}件)" ;;
            "gt") log_error "${name}: ${actual}件 (期待: > ${expected}件)" ;;
            "lt") log_error "${name}: ${actual}件 (期待: < ${expected}件)" ;;
        esac
    fi
}

# MySQLのレコード数を取得
get_mysql_count() {
    local database=$1
    local table=$2

    docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN \
        -e "SELECT COUNT(*) FROM ${database}.${table}" 2>/dev/null || echo "0"
}

# MySQLのレコード数を条件付きで取得
get_mysql_count_with_where() {
    local database=$1
    local table=$2
    local where_clause=$3

    docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN \
        -e "SELECT COUNT(*) FROM ${database}.${table} WHERE ${where_clause}" 2>/dev/null || echo "0"
}

# MySQLで条件に一致しないレコード数を取得（検証用）
get_mysql_invalid_count() {
    local database=$1
    local table=$2
    local where_clause=$3

    docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN \
        -e "SELECT COUNT(*) FROM ${database}.${table} WHERE NOT (${where_clause})" 2>/dev/null || echo "0"
}

# SQLiteのレコード数を取得
get_sqlite_count() {
    local db_path=$1
    local table=$2

    docker exec "$APP_CONTAINER" bash -c \
        "sqlite3 ${db_path} 'SELECT COUNT(*) FROM ${table}' 2>/dev/null" || echo "0"
}

# メイン処理
main() {
    echo ""
    log_info "========================================="
    log_info "テストデータの検証を開始"
    log_info "========================================="
    echo ""

    # コンテナが起動しているか確認
    if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
        log_error "APPコンテナ (${APP_CONTAINER}) が起動していません"
        exit 1
    fi

    if ! docker ps --format '{{.Names}}' | grep -q "^${MYSQL_CONTAINER}$"; then
        log_error "MySQLコンテナ (${MYSQL_CONTAINER}) が起動していません"
        exit 1
    fi

    # MySQLテーブルのレコード数確認
    log_info "MySQLテーブルのレコード数を確認中..."
    echo ""

    # 多言語対応: JA, TW, TH
    declare -A lang_configs=(
        ["ja"]="ocgraph_ocreview:2000:ocgraph_ranking"
        ["tw"]="ocgraph_ocreviewtw:1000:ocgraph_rankingtw"
        ["th"]="ocgraph_ocreviewth:1000:ocgraph_rankingth"
    )

    # 各言語のテーブルを確認
    for lang in ja tw th; do
        IFS=':' read -r db_name min_count ranking_db <<< "${lang_configs[$lang]}"

        log_info "--- ${lang^^} 言語のデータを確認 ---"
        echo ""

        # open_chat (メインテーブル)
        count=$(get_mysql_count "$db_name" "open_chat")
        test_result "${db_name}.open_chat" "$min_count" "$count" "ge"

        # open_chat WHERE url IS NOT NULL (URLが存在するレコード)
        count=$(get_mysql_count_with_where "$db_name" "open_chat" "url IS NOT NULL")
        test_result "${db_name}.open_chat (url IS NOT NULL)" 100 "$count" "ge"

        # statistics_ranking_hour (統計ランキング)
        count=$(get_mysql_count "$db_name" "statistics_ranking_hour")
        test_result "${db_name}.statistics_ranking_hour" 1000 "$count" "ge"

        # statistics_ranking_hour24 (24時間統計ランキング)
        count=$(get_mysql_count "$db_name" "statistics_ranking_hour24")
        test_result "${db_name}.statistics_ranking_hour24" 1000 "$count" "ge"

        # sync_open_chat_state (bool カラムが全て 0)
        count=$(get_mysql_invalid_count "$db_name" "sync_open_chat_state" "bool = 0")
        test_result "${db_name}.sync_open_chat_state (bool = 0)" 0 "$count" "eq"

        # sync_open_chat_state (type が ocreviewApiDataImportBackground または rankingPersistenceBackground で extra が '[]' 以外のレコードが0件)
        count=$(get_mysql_count_with_where "$db_name" "sync_open_chat_state" "type IN ('ocreviewApiDataImportBackground', 'rankingPersistenceBackground') AND extra != '[]'")
        test_result "${db_name}.sync_open_chat_state (type指定で extra != '[]')" 0 "$count" "eq"

        # recommend (おすすめテーブル)
        count=$(get_mysql_count "$db_name" "recommend")
        test_result "${db_name}.recommend" 3 "$count" "ge"

        # oc_tag (タグテーブル)
        count=$(get_mysql_count "$db_name" "oc_tag")
        test_result "${db_name}.oc_tag" 3 "$count" "ge"

        # user_log (エラーログ: 0件期待)
        count=$(get_mysql_count "$db_name" "user_log")
        test_result "${db_name}.user_log" 0 "$count" "eq"

        # user_logに記録がある場合、内容を表示
        if [ "$count" -gt 0 ]; then
            echo ""
            log_warn "user_logに${count}件の記録があります。内容を確認してください："
            docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -e "SELECT * FROM ${db_name}.user_log ORDER BY id DESC LIMIT 10" 2>/dev/null || true
            echo ""
        fi

        # ranking テーブル (500件以上)
        count=$(get_mysql_count "$ranking_db" "ranking")
        test_result "${ranking_db}.ranking" 500 "$count" "ge"

        # SQLite: ranking_position.db
        sqlite_ranking_path="/var/www/html/storage/${lang}/SQLite/ranking_position/ranking_position.db"

        count=$(get_sqlite_count "$sqlite_ranking_path" "ranking")
        test_result "storage/${lang}/SQLite/ranking_position.db (ranking)" 1000 "$count" "ge"

        count=$(get_sqlite_count "$sqlite_ranking_path" "rising")
        test_result "storage/${lang}/SQLite/ranking_position.db (rising)" 1000 "$count" "ge"

        # SQLite: statistics.db
        sqlite_stats_path="/var/www/html/storage/${lang}/SQLite/statistics/statistics.db"

        count=$(get_sqlite_count "$sqlite_stats_path" "statistics")
        test_result "storage/${lang}/SQLite/statistics.db (statistics)" 2000 "$count" "ge"

        # SQLite: sqlapi.db (日本語のみ - 件数一致テスト)
        if [ "$lang" = "ja" ]; then
            sqlite_sqlapi_path="/var/www/html/storage/${lang}/SQLite/ocgraph_sqlapi/sqlapi.db"

            # daily_member_statistics → storage/ja/SQLite/statistics.db の statistics テーブル以上
            local statistics_count=$(get_sqlite_count "$sqlite_stats_path" "statistics")
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "daily_member_statistics")
            test_result "sqlapi.db (daily_member_statistics) >= statistics.db (statistics)" "$statistics_count" "$count" "ge"

            # growth_ranking_past_24_hours → ocgraph_ocreview.statistics_ranking_hour24 と件数が一致
            local hour24_count=$(get_mysql_count "$db_name" "statistics_ranking_hour24")
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "growth_ranking_past_24_hours")
            test_result "sqlapi.db (growth_ranking_past_24_hours) = ${db_name}.statistics_ranking_hour24" "$hour24_count" "$count" "eq"

            # growth_ranking_past_hour → ocgraph_ocreview.statistics_ranking_hour と件数が一致
            local hour_count=$(get_mysql_count "$db_name" "statistics_ranking_hour")
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "growth_ranking_past_hour")
            test_result "sqlapi.db (growth_ranking_past_hour) = ${db_name}.statistics_ranking_hour" "$hour_count" "$count" "eq"

            # line_official_ranking_total_count → storage/ja/SQLite/ranking_position.db の total_count テーブルと件数が一致
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "line_official_ranking_total_count")
            local total_count_count=$(get_sqlite_count "$sqlite_ranking_path" "total_count")
            test_result "sqlapi.db (line_official_ranking_total_count) = ranking_position.db (total_count)" "$total_count_count" "$count" "eq"

            # open_chat_deleted → ocgraph_ocreview.open_chat_deleted と件数が一致
            local deleted_count=$(get_mysql_count "$db_name" "open_chat_deleted")
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "open_chat_deleted")
            test_result "sqlapi.db (open_chat_deleted) = ${db_name}.open_chat_deleted" "$deleted_count" "$count" "eq"

            # openchat_master → ocgraph_ocreview.open_chat 以上
            local open_chat_count=$(get_mysql_count "$db_name" "open_chat")
            count=$(get_sqlite_count "$sqlite_sqlapi_path" "openchat_master")
            test_result "sqlapi.db (openchat_master) >= ${db_name}.open_chat" "$open_chat_count" "$count" "ge"
        fi

        echo ""
    done

    echo ""

    log_info "========================================="
    log_info "検証結果: ${PASSED}/${TOTAL} 成功, ${FAILED}/${TOTAL} 失敗"
    log_info "========================================="
    echo ""

    if [ $FAILED -eq 0 ]; then
        log_success "すべてのテストが成功しました！"
        return 0
    else
        log_error "一部のテストが失敗しました"
        return 1
    fi
}

# メイン処理実行
main
