#!/bin/bash
# CI用URLテストスクリプト
# 各種エンドポイントにアクセスして動作確認を行う

set -e

# 色付き出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# テスト用URL
BASE_URL="https://localhost:8443"

# ログディレクトリ
LOG_DIR="./test-logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/url-test-$(date +%Y%m%d_%H%M%S).log"

# カウンター
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# ログ関数
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${CYAN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS:${NC} $1" | tee -a "$LOG_FILE"
}

# URLテスト関数
test_url() {
    local url="$1"
    local description="$2"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Testing: ${url} ... " | tee -a "$LOG_FILE"

    # curlでアクセス（SSL証明書の検証を無効化、リダイレクトを追跡）
    if curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 "$url" | grep -qE "^(200|301|302)$"; then
        echo -e "${GREEN}OK${NC}" | tee -a "$LOG_FILE"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        local status_code=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 "$url")
        echo -e "${RED}FAILED (Status: ${status_code})${NC}" | tee -a "$LOG_FILE"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# POSTリクエストテスト関数
test_post() {
    local url="$1"
    local data="$2"
    local description="$3"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Testing POST: ${url} ... " | tee -a "$LOG_FILE"

    # curlでPOSTリクエスト
    if curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 \
        -X POST \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "$data" \
        "$url" | grep -qE "^(200|301|302|400|422)$"; then
        echo -e "${GREEN}OK${NC}" | tee -a "$LOG_FILE"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        local status_code=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 -X POST -d "$data" "$url")
        echo -e "${RED}FAILED (Status: ${status_code})${NC}" | tee -a "$LOG_FILE"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# JSON POSTリクエストテスト関数
test_post_json() {
    local url="$1"
    local json_data="$2"
    local description="$3"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -n "Testing POST (JSON): ${url} ... " | tee -a "$LOG_FILE"

    # curlでJSON POSTリクエスト（401, 403なども成功とみなす - エンドポイントが存在すればOK）
    if curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 \
        -X POST \
        -H "Content-Type: application/json" \
        -d "$json_data" \
        "$url" | grep -qE "^(200|301|302|400|401|403|422)$"; then
        echo -e "${GREEN}OK${NC}" | tee -a "$LOG_FILE"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        local status_code=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 -X POST -H "Content-Type: application/json" -d "$json_data" "$url")
        echo -e "${RED}FAILED (Status: ${status_code})${NC}" | tee -a "$LOG_FILE"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

# メイン処理
main() {
    log "========================================="
    log "URLテストを開始"
    log "ログファイル: ${LOG_FILE}"
    log "========================================="
    echo ""

    # 基本ページテスト
    log "基本ページのテスト"
    test_url "${BASE_URL}" "トップページ"
    test_url "${BASE_URL}/tw" "トップページ（繁体字）"
    test_url "${BASE_URL}/th" "トップページ（タイ語）"
    echo ""

    # オープンチャット詳細ページ（DBから実際のIDを取得）
    log "オープンチャット詳細ページのテスト"
    # MySQLから最初のopen_chat IDを取得
    local MYSQL_CONTAINER="oc-review-mock-mysql-1"
    local JA_OC_ID=$(docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN -e "SELECT id FROM ocgraph_ocreview.open_chat ORDER BY id LIMIT 1" 2>/dev/null || echo "")
    local TH_OC_ID=$(docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN -e "SELECT id FROM ocgraph_ocreviewth.open_chat ORDER BY id LIMIT 1" 2>/dev/null || echo "")
    local TW_OC_ID=$(docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN -e "SELECT id FROM ocgraph_ocreviewtw.open_chat ORDER BY id LIMIT 1" 2>/dev/null || echo "")

    if [ -n "$JA_OC_ID" ]; then
        test_url "${BASE_URL}/oc/${JA_OC_ID}" "OC詳細ページ (ID=${JA_OC_ID})"
    else
        log_error "日本語のopen_chatテーブルにデータがありません"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
    fi

    if [ -n "$TH_OC_ID" ]; then
        test_url "${BASE_URL}/th/oc/${TH_OC_ID}" "OC詳細ページ（タイ語, ID=${TH_OC_ID}）"
    else
        log_error "タイ語のopen_chatテーブルにデータがありません"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
    fi

    if [ -n "$TW_OC_ID" ]; then
        test_url "${BASE_URL}/tw/oc/${TW_OC_ID}" "OC詳細ページ（繁体字, ID=${TW_OC_ID}）"
    else
        log_error "繁体字のopen_chatテーブルにデータがありません"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
    fi
    echo ""

    # 各種ページ
    log "各種ページのテスト"
    test_url "${BASE_URL}/oc" "OC登録ページ"
    test_url "${BASE_URL}/policy" "ポリシーページ"
    test_url "${BASE_URL}/policy/term" "利用規約ページ"
    test_url "${BASE_URL}/labs/live" "Labs Live"
    test_url "${BASE_URL}/policy/privacy" "プライバシーポリシー"
    test_url "${BASE_URL}/labs/publication-analytics" "公開分析"
    test_url "${BASE_URL}/recently-registered" "最近登録"
    test_url "${BASE_URL}/recently-registered/1" "最近登録（ページ1）"
    test_url "${BASE_URL}/ranking" "ランキング"
    echo ""

    # OCリストページ（各種パラメータ）
    log "OCリストページのテスト"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=increase&order=desc" "OCリスト（hourly/increase/desc）"
    test_url "${BASE_URL}/tw/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=increase&order=desc" "OCリスト（繁体字/hourly）"
    test_url "${BASE_URL}/th/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=increase&order=desc" "OCリスト（タイ語/hourly）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=daily&sort=increase&order=desc" "OCリスト（daily）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=weekly&sort=increase&order=desc" "OCリスト（weekly）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=all&sort=member&order=desc" "OCリスト（all/member）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=increase&order=asc" "OCリスト（hourly/asc）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=rate&order=desc" "OCリスト（hourly/rate/desc）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=&list=hourly&sort=rate&order=asc" "OCリスト（hourly/rate/asc）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=17&sub_category=&keyword=&list=hourly&sort=rate&order=asc" "OCリスト（category=17）"
    echo ""

    # キーワード検索
    log "キーワード検索のテスト"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=%E3%83%9D%E3%82%B1%E3%83%A2%E3%83%B3&list=all&sort=member&order=desc" "検索（ポケモン）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=17&sub_category=&keyword=%E3%83%9D%E3%82%B1%E3%83%A2%E3%83%B3&list=all&sort=member&order=desc" "検索（ポケモン/category=17）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=badge%3A%E3%82%B9%E3%83%9A%E3%82%B7%E3%83%A3%E3%83%AB%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88&list=all&sort=member&order=desc" "検索（badge:スペシャルオープンチャット）"
    test_url "${BASE_URL}/oclist?page=0&limit=20&category=0&sub_category=&keyword=badge%3A%E5%85%AC%E5%BC%8F%E8%AA%8D%E8%A8%BC%E3%83%90%E3%83%83%E3%82%B8&list=all&sort=member&order=desc" "検索（badge:公式認証バッジ）"
    echo ""

    # 不正なパラメータのテスト（400エラーは期待される結果）
    log "不正なパラメータのテスト"
    # 不正なパラメータに対しては400エラーが返されるのが正常
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -n "Testing: ${BASE_URL}/oclist?page=0&limit=... (invalid params) ... " | tee -a "$LOG_FILE"
    status_code=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 30 "${BASE_URL}/oclist?page=0&limit=\a\/%22%22%2&category=\a\/%22%22%2&\a\/%22%22%2=&keyword=33&list=hourly&sort=\//\\\\a\/%22%22%27|a&order=asc")
    if [ "$status_code" = "400" ] || [ "$status_code" = "200" ]; then
        echo -e "${GREEN}OK (Status: ${status_code})${NC}" | tee -a "$LOG_FILE"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}FAILED (Status: ${status_code}, expected 400 or 200)${NC}" | tee -a "$LOG_FILE"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    echo ""

    # レコメンドページ
    log "レコメンドページのテスト"
    test_url "${BASE_URL}/recommend/%E3%83%9D%E3%82%B1%E3%83%83%E3%83%88%E3%83%A2%E3%83%B3%E3%82%B9%E3%82%BF%E3%83%BC%EF%BC%88%E3%83%9D%E3%82%B1%E3%83%A2%E3%83%B3%EF%BC%89" "レコメンド（ポケモン）"
    echo ""

    # コメントページ
    log "コメントページのテスト"
    test_url "${BASE_URL}/comment/0?page=0&limit=10" "コメント（ID=0）"
    test_url "${BASE_URL}/comment/1?page=10&limit=10" "コメント（ID=1、page=10）"
    test_url "${BASE_URL}/comments-timeline" "コメントタイムライン"
    echo ""

    # POSTリクエストのテスト
    log "POSTリクエストのテスト"
    test_post "${BASE_URL}/oc" "url=aa&submit=" "OC登録（POST）"
    test_post_json "${BASE_URL}/comment_report/1" '{"token":"test-token"}' "コメント通報（POST JSON）"
    test_post_json "${BASE_URL}/comment_reaction/3777" '{"type":"empathy"}' "コメントリアクション（POST JSON）"
    echo ""

    # 結果サマリー
    log "========================================="
    log "テスト結果"
    log "========================================="
    log "総テスト数: ${TOTAL_TESTS}"
    log "成功: ${PASSED_TESTS}"
    log "失敗: ${FAILED_TESTS}"
    log "========================================="

    if [ $FAILED_TESTS -gt 0 ]; then
        log_error "${FAILED_TESTS}件のテストが失敗しました"
        exit 1
    else
        log_success "すべてのテストが成功しました"
    fi
}

# Ctrl+Cでの中断をハンドル
trap 'log_error "テストが中断されました"; exit 130' INT TERM

# メイン処理実行
main
