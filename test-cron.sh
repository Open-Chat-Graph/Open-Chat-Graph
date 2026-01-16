#!/bin/bash
# デバッグ用テストスクリプト（本番環境に近い設定）
# ホストOS側から実行して、コンテナの時刻を1時間ずつ進めながらcronジョブを実行
# - 大量データ（10万件）、遅延あり、48時間テストに対応
# - テストケースが多く、本番環境の挙動を再現
#
# このスクリプトは docker/line-mock-api/.env.mock の設定を使用します:
# - TEST_JA_HOURS, TEST_TW_HOURS, TEST_TH_HOURS: 各言語の実行回数
# - 自動設定: MOCK_RANKING_COUNT=10000, MOCK_RISING_COUNT=1000, MOCK_DELAY_ENABLED=0, MOCK_API_TYPE=dynamic

set -e

# 設定
COMPOSE_FILE="docker-compose.yml"
MOCK_COMPOSE_FILE="docker-compose.mock.yml"
APP_CONTAINER="oc-review-mock-app-1"
MOCK_CONTAINER="oc-review-mock-line-mock-api-1"
LOG_DIR="./test-logs"
TOTAL_HOURS=24

# docker/line-mock-api/.env.mockから設定を読み込む
if [ -f docker/line-mock-api/.env.mock ]; then
    source docker/line-mock-api/.env.mock
fi

# 言語ごとの実行回数設定（環境変数が設定されていればそれを使用、なければデフォルト値）
JA_HOURS=${TEST_JA_HOURS:-25}   # 日本語（hourIndex: 0〜24の25時間分）
TW_HOURS=${TEST_TW_HOURS:-1}    # 繁体字中国語
TH_HOURS=${TEST_TH_HOURS:-1}    # タイ語

# 色付き出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ログディレクトリ作成
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/test-48h-$(date +%Y%m%d_%H%M%S).log"

# ログ関数
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARN:${NC} $1" | tee -a "$LOG_FILE"
}

log_info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] INFO:${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS:${NC} $1" | tee -a "$LOG_FILE"
}

# コンテナが起動しているか確認
check_containers() {
    log "コンテナの状態を確認中..."

    if ! docker ps --format '{{.Names}}' | grep -q "^${APP_CONTAINER}$"; then
        log_error "APPコンテナ (${APP_CONTAINER}) が起動していません"
        log_error "まず 'make up-mock' を実行してください"
        exit 1
    fi

    if ! docker ps --format '{{.Names}}' | grep -q "^${MOCK_CONTAINER}$"; then
        log_error "モックAPIコンテナ (${MOCK_CONTAINER}) が起動していません"
        log_error "まず 'make up-mock' を実行してください"
        exit 1
    fi

    log "コンテナの起動を確認しました"
}

# システム時刻を取得
get_container_time() {
    local container=$1
    docker exec "$container" date +%s
}

# システム時刻を設定
set_container_time() {
    local container=$1
    local timestamp=$2
    local readable_date=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')

    log_info "コンテナ ${container} の時刻を ${readable_date} に設定中..."

    # システム時刻を変更（privileged: true と -u root が必要）
    if docker exec -u root "$container" date -s "@$timestamp" > /dev/null 2>&1; then
        log_info "時刻設定成功: ${readable_date}"
        return 0
    else
        log_error "時刻設定失敗（権限が不足している可能性があります）"
        log_error "docker-compose.mock.yml で privileged: true と cap_add: [SYS_TIME] が設定されているか確認してください"
        return 1
    fi
}

# cronジョブを実行
run_cron_job() {
    local hour=$1
    local lang_name=$2
    local lang_arg=$3
    local timestamp=$4
    local readable_date=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')

    log_info "${lang_name} クローリング実行（時刻: ${readable_date}）"

    local start_time=$(date +%s)
    # ログファイル名用にスラッシュを削除
    local lang_code="${lang_arg#/}"  # 先頭のスラッシュを削除
    local lang_code="${lang_code:-ja}"  # 空の場合はjaを使用
    local job_log="$LOG_DIR/hour${hour}_${lang_code}.log"

    # faketimeを使用してPHPスクリプトを実行
    # LD_PRELOAD + FAKETIME環境変数を使用（faketimeコマンドではなく）
    # これにより、PHPのtouch()関数がファイルタイムスタンプを偽装できる
    # @を付けることで、指定時刻から実際に時間が進むようになる（時刻固定ではない）
    local readable_date=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')

    if [ -z "$lang_arg" ]; then
        # 日本語（引数なし）
        docker exec "$APP_CONTAINER" bash -c \
            "FAKETIME='@${readable_date}' LD_PRELOAD=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1 /usr/local/bin/php batch/cron/cron_crawling.php" \
            2>&1 | tee "$job_log"
    else
        # 繁体字/タイ語（引数あり）
        docker exec "$APP_CONTAINER" bash -c \
            "FAKETIME='@${readable_date}' LD_PRELOAD=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1 /usr/local/bin/php batch/cron/cron_crawling.php ${lang_arg}" \
            2>&1 | tee "$job_log"
    fi

    local exit_code=$?
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    local minutes=$((duration / 60))
    local seconds=$((duration % 60))

    if [ $exit_code -eq 0 ]; then
        log "${lang_name} クローリング完了（${minutes}分${seconds}秒）- ログ: ${job_log}"
    else
        log_error "${lang_name} クローリング失敗（終了コード: ${exit_code}）- 詳細: ${job_log}"
        # エラーでも継続
    fi
}

# メイン処理
main() {
    log "========================================="
    log "24時間分のcronテストを開始（高速テスト用）"
    log "ログファイル: ${LOG_FILE}"
    log "========================================="
    log_info "実行回数設定: 日本語=${JA_HOURS}回, 繁体字=${TW_HOURS}回, タイ語=${TH_HOURS}回"
    echo ""

    # docker/line-mock-api/.env.mockを自動設定（高速テスト用）
    log_info "docker/line-mock-api/.env.mockを高速テスト用に設定中..."

    # docker/line-mock-api/.env.mock.exampleから元のファイルをコピー
    if [ ! -f docker/line-mock-api/.env.mock.example ]; then
        log_error "docker/line-mock-api/.env.mock.exampleが見つかりません"
        exit 1
    fi

    cp docker/line-mock-api/.env.mock.example docker/line-mock-api/.env.mock

    # 必要な設定を上書き（sedを使用してコメントを保持）
    sed -i 's/^MOCK_RANKING_COUNT=.*/MOCK_RANKING_COUNT=10000/' docker/line-mock-api/.env.mock
    sed -i 's/^MOCK_RISING_COUNT=.*/MOCK_RISING_COUNT=1000/' docker/line-mock-api/.env.mock
    sed -i 's/^MOCK_DELAY_ENABLED=.*/MOCK_DELAY_ENABLED=0/' docker/line-mock-api/.env.mock
    sed -i 's/^MOCK_API_TYPE=.*/MOCK_API_TYPE=dynamic/' docker/line-mock-api/.env.mock

    log_success "docker/line-mock-api/.env.mockを設定しました（1万件、遅延なし、動的データ）"

    # コンテナチェック
    check_containers

    # 開始時刻を設定（今日の1日後の23:30:00）
    # 日本語23:30、繁体字23:40、タイ語23:45でcronが実行される
    local tomorrow_date=$(date -d "+1 day" '+%Y-%m-%d')
    CURRENT_TIME=$(date -d "${tomorrow_date} 23:30:00" +%s)

    log "テスト開始時刻: $(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S')"

    # faketimeが利用可能か確認
    if ! docker exec "$APP_CONTAINER" which faketime > /dev/null 2>&1; then
        log_error "faketimeがインストールされていません"
        log_error "コンテナを再ビルドしてください: make rebuild-mock"
        exit 1
    fi

    log_info "faketimeを使用してテストを実行します"

    # 48時間ループ
    for hour in $(seq 1 $TOTAL_HOURS); do
        # 時刻を1時間進める
        CURRENT_TIME=$((CURRENT_TIME + 3600))

        log "========================================="
        log "第${hour}時間目 ($(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S'))"
        log "========================================="

        local jobs_executed=0

        # 3つのcronジョブを直列で実行（各言語の実行回数を制限）
        # 毎時30分: 日本語（引数なし）
        if [ $hour -le $JA_HOURS ]; then
            run_cron_job "$hour" "日本語" "" "$CURRENT_TIME"
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "日本語はスキップ（設定: ${JA_HOURS}回まで）"
        fi

        # 毎時40分: 繁体字中国語（時刻を10分進める）
        if [ $hour -le $TW_HOURS ]; then
            run_cron_job "$hour" "繁体字中国語" "/tw" "$((CURRENT_TIME + 600))"
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "繁体字中国語はスキップ（設定: ${TW_HOURS}回まで）"
        fi

        # 毎時45分: タイ語（時刻を15分進める）
        if [ $hour -le $TH_HOURS ]; then
            run_cron_job "$hour" "タイ語" "/th" "$((CURRENT_TIME + 900))"
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "タイ語はスキップ（設定: ${TH_HOURS}回まで）"
        fi

        # 何もジョブが実行されなかった場合
        if [ $jobs_executed -eq 0 ]; then
            log_warn "この時間はすべての言語がスキップされました"
        fi

        # 進捗表示
        local progress=$((hour * 100 / TOTAL_HOURS))
        log "進捗: ${hour}/${TOTAL_HOURS}時間 (${progress}%)"
        echo "" | tee -a "$LOG_FILE"
    done


    log "========================================="
    log "24時間分のcronテスト完了"
    log "ログファイル: ${LOG_FILE}"
    log "個別ログ: ${LOG_DIR}/"
    log "========================================="

    # データ検証
    log ""
    log "データ検証を開始..."
    if bash ./.github/scripts/verify-test-data.sh; then
        log "データ検証に成功しました"
    else
        log_error "データ検証に失敗しました"
        exit 1
    fi
}

# Ctrl+Cでの中断をハンドル
trap 'log_error "テストが中断されました"; exit 130' INT TERM

# メイン処理実行
main
