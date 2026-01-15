#!/bin/bash
# CI用テストスクリプト（高速・効率的）
# ホストOS側から実行して、固定データモックAPIを使用したカテゴリ別クローリングをテスト
# - 少量データ（80件/カテゴリ）、遅延なし、高速実行
# - 日常的なテスト・CI環境での使用を想定
#
# このスクリプトは .env.mock の設定を使用します:
# - TEST_JA_HOURS, TEST_TW_HOURS, TEST_TH_HOURS: 各言語の実行回数
# - 自動設定: MOCK_API_TYPE=fixed, MOCK_DELAY_ENABLED=0
#
# ## テスト仕様
#
# ### データ件数
# - 各カテゴリごとに急上昇:80件、ランキング:80件を返す（固定）
# - 日本語: 複数日分のテストデータ（JA_HOURS設定による）
# - 日本語以外（繁体字/タイ語）: 初回のみのテストデータ
#
# ### カテゴリ0（すべて/全部/ทั้งหมด）
# - 急上昇のみに登場するルーム16件あり（ランキングには出ない）
#
# ### カテゴリ1以降
# - 人数固定ルーム16件（5人×8、10人×4、20人×4）は最初の1回のみ出現

set -e

# 設定
APP_CONTAINER="oc-review-mock-app-1"
MOCK_CONTAINER="oc-review-mock-line-mock-api-1"
LOG_DIR="./test-logs"

# .env.mockから設定を読み込む
if [ -f .env.mock ]; then
    source .env.mock
fi

# 言語ごとの実行回数設定（環境変数が設定されていればそれを使用、なければデフォルト値）
JA_HOURS=${TEST_JA_HOURS:-24}  # 日本語
TW_HOURS=${TEST_TW_HOURS:-1}   # 繁体字中国語
TH_HOURS=${TEST_TH_HOURS:-1}   # タイ語

# 色付き出力
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# ログディレクトリ作成
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/test-category-$(date +%Y%m%d_%H%M%S).log"

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
    echo -e "${CYAN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS:${NC} $1" | tee -a "$LOG_FILE"
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

# cronジョブを実行
run_cron_job() {
    local hour=$1
    local lang_name=$2
    local lang_arg=$3
    local timestamp=$4
    local readable_date=$(date -d "@$timestamp" '+%Y-%m-%d %H:%M:%S')

    log_info "${lang_name} クローリング実行（時刻: ${readable_date}、${hour}時間目）"

    local start_time=$(date +%s)
    # ログファイル名用にスラッシュを削除
    local lang_code="${lang_arg#/}"  # 先頭のスラッシュを削除
    local lang_code="${lang_code:-ja}"  # 空の場合はjaを使用
    local job_log="$LOG_DIR/hour${hour}_${lang_code}.log"

    # faketimeを使用してPHPスクリプトを実行
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
        log_success "${lang_name} クローリング完了（${minutes}分${seconds}秒）- ログ: ${job_log}"
    else
        log_error "${lang_name} クローリング失敗（終了コード: ${exit_code}）- 詳細: ${job_log}"
        # エラーでも継続
    fi
}

# メイン処理
main() {
    log "========================================="
    log "カテゴリ別クローリングテストを開始"
    log "ログファイル: ${LOG_FILE}"
    log "========================================="
    log_info "実行回数設定: 日本語=${JA_HOURS}回, 繁体字=${TW_HOURS}回, タイ語=${TH_HOURS}回"
    echo ""

    # .env.mockを自動設定（固定データモード用）
    log_info ".env.mockを固定データモード用に設定中..."

    # .env.mock.exampleから元のファイルをコピー
    if [ ! -f .env.mock.example ]; then
        log_error ".env.mock.exampleが見つかりません"
        exit 1
    fi

    cp .env.mock.example .env.mock

    # 必要な設定を上書き（sedを使用してコメントを保持）
    sed -i 's/^MOCK_DELAY_ENABLED=.*/MOCK_DELAY_ENABLED=0/' .env.mock
    sed -i 's/^MOCK_API_TYPE=.*/MOCK_API_TYPE=fixed/' .env.mock

    # hourIndexを初期化
    docker exec "$MOCK_CONTAINER" mkdir -p /app/data
    echo "0" | docker exec -i "$MOCK_CONTAINER" tee /app/data/hour_index.txt > /dev/null

    log_success ".env.mockを設定しました（固定データモード、遅延なし）"

    # コンテナチェック
    check_containers

    # faketimeが利用可能か確認
    if ! docker exec "$APP_CONTAINER" which faketime > /dev/null 2>&1; then
        log_error "faketimeがインストールされていません"
        log_error "コンテナを再ビルドしてください: make rebuild-mock"
        exit 1
    fi

    log_info "faketimeを使用してテストを実行します"

    # 開始時刻を設定（現在時刻の翌日00:00:00 - 30分 = 当日23:30から開始）
    # SSL証明書の有効期間を考慮して翌日以降に設定
    # 23:30から開始することで、1時間目に日次処理が実行される
    local tomorrow_date=$(date -d "+1 day" '+%Y-%m-%d')
    CURRENT_TIME=$(date -d "${tomorrow_date} 23:30:00" +%s)

    log "テスト開始時刻: $(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S')"
    log_info "この時刻を基準に各言語の設定時間分進めます"
    log_info "1時間目（00:00）に日次処理が実行されます"

    # 最大時間数を計算
    local max_hours=$JA_HOURS
    if [ $TW_HOURS -gt $max_hours ]; then
        max_hours=$TW_HOURS
    fi
    if [ $TH_HOURS -gt $max_hours ]; then
        max_hours=$TH_HOURS
    fi

    # 各時間ループ
    for hour in $(seq 1 $max_hours); do
        # 時刻を1時間進める
        if [ $hour -gt 1 ]; then
            CURRENT_TIME=$((CURRENT_TIME + 3600))
        fi

        

        log "========================================="
        log "第${hour}時間目 ($(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S'))"
        log "========================================="

        # hourIndexを更新（hour=1 → hourIndex=0）
        local hour_index=$((hour - 1))
        echo "$hour_index" | docker exec -i "$MOCK_CONTAINER" tee /app/data/hour_index.txt > /dev/null

        local jobs_executed=0
        local pids=()

        # 3つのcronジョブを並列で実行（各言語の実行回数を制限）
        # 毎時30分: 日本語（引数なし）
        if [ $hour -le $JA_HOURS ]; then
            run_cron_job "$hour" "日本語" "" "$CURRENT_TIME" &
            pids+=($!)
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "日本語はスキップ（設定: ${JA_HOURS}回まで）"
        fi

        # 毎時35分: 繁体字中国語（時刻を5分進める）
        if [ $hour -le $TW_HOURS ]; then
            run_cron_job "$hour" "繁体字中国語" "/tw" "$((CURRENT_TIME + 300))" &
            pids+=($!)
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "繁体字中国語はスキップ（設定: ${TW_HOURS}回まで）"
        fi

        # 毎時40分: タイ語（時刻を10分進める）
        if [ $hour -le $TH_HOURS ]; then
            run_cron_job "$hour" "タイ語" "/th" "$((CURRENT_TIME + 600))" &
            pids+=($!)
            jobs_executed=$((jobs_executed + 1))
        else
            log_info "タイ語はスキップ（設定: ${TH_HOURS}回まで）"
        fi

        # すべてのジョブが完了するまで待つ
        if [ ${#pids[@]} -gt 0 ]; then
            wait "${pids[@]}"
        fi

        # 何もジョブが実行されなかった場合
        if [ $jobs_executed -eq 0 ]; then
            log_warn "この時間はすべての言語がスキップされました"
        fi

        # 進捗表示
        local progress=$((hour * 100 / max_hours))
        log "進捗: ${hour}/${max_hours}時間 (${progress}%)"
        echo "" | tee -a "$LOG_FILE"
    done

    log "========================================="
    log "カテゴリ別クローリングテスト完了"
    log "ログファイル: ${LOG_FILE}"
    log "個別ログ: ${LOG_DIR}/"
    log "========================================="
}

# Ctrl+Cでの中断をハンドル
trap 'log_error "テストが中断されました"; exit 130' INT TERM

# メイン処理実行
main
