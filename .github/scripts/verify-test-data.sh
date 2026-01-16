#!/bin/bash
# テスト環境のデータを検証するスクリプト
# MySQLテーブルのレコード数と画像ファイルの存在を確認

set -e

# 設定
APP_CONTAINER="oc-review-mock-app-1"
MYSQL_CONTAINER="oc-review-mock-mysql-1"

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

# ディレクトリ内のwebp画像数を取得
get_webp_count() {
    local dir=$1

    docker exec "$APP_CONTAINER" bash -c \
        "find ${dir} -maxdepth 1 -name '*.webp' -type f 2>/dev/null | wc -l" || echo "0"
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

    # ocgraph_ocreview.open_chat (2000件以上)
    # コメントテーブルはクローリングでは更新されないため、メインのopen_chatテーブルを確認
    count=$(get_mysql_count "ocgraph_ocreview" "open_chat")
    test_result "ocgraph_ocreview.open_chat" 2000 "$count" "ge"

    # ocgraph_ocreviewth.open_chat (1000件以上)
    count=$(get_mysql_count "ocgraph_ocreviewth" "open_chat")
    test_result "ocgraph_ocreviewth.open_chat" 1000 "$count" "ge"

    # ocgraph_ocreviewtw.open_chat (1000件以上)
    count=$(get_mysql_count "ocgraph_ocreviewtw" "open_chat")
    test_result "ocgraph_ocreviewtw.open_chat" 1000 "$count" "ge"

    # ocgraph_ocreview.statistics_ranking_hour (10件以上)
    count=$(get_mysql_count "ocgraph_ocreview" "statistics_ranking_hour")
    test_result "ocgraph_ocreview.statistics_ranking_hour" 10 "$count" "ge"

    # ocgraph_ocreview.statistics_ranking_hour24 (24時間ランキングはテスト時間では生成されないためスキップ)
    # count=$(get_mysql_count "ocgraph_ocreview" "statistics_ranking_hour24")
    # test_result "ocgraph_ocreview.statistics_ranking_hour24" 10 "$count" "ge"

    # ocgraph_ocreview.user_log (0件)
    count=$(get_mysql_count "ocgraph_ocreview" "user_log")
    test_result "ocgraph_ocreview.user_log" 0 "$count" "eq"

    # user_logに記録がある場合、内容を表示
    if [ "$count" -gt 0 ]; then
        echo ""
        log_warn "user_logに${count}件の記録があります。内容を確認してください："
        docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -e "SELECT * FROM ocgraph_ocreview.user_log ORDER BY id DESC LIMIT 10" 2>/dev/null || true
        echo ""
    fi

    # ocgraph_ranking.ranking (500件以上)
    count=$(get_mysql_count "ocgraph_ranking" "ranking")
    test_result "ocgraph_ranking.ranking" 500 "$count" "ge"

    echo ""
    log_info "画像ファイルの存在を確認中..."
    echo ""

    # public/oc-img/0 の .webp画像 (10件以上)
    count=$(get_webp_count "/var/www/html/public/oc-img/0")
    test_result "public/oc-img/0 の .webp画像" 10 "$count" "ge"

    # public/oc-img/preview/0 の .webp画像 (10件以上)
    count=$(get_webp_count "/var/www/html/public/oc-img/preview/0")
    test_result "public/oc-img/preview/0 の .webp画像" 10 "$count" "ge"

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
