#!/bin/bash
# CI用テストスクリプト（高速・効率的）
# ホストOS側から実行して、固定データモックAPIを使用したカテゴリ別クローリングをテスト
# - 少量データ（80件/カテゴリ）、遅延なし、高速実行
# - 日常的なテスト・CI環境での使用を想定
#
# このスクリプトは docker/line-mock-api/.env.mock の設定を使用します:
# - TEST_JA_HOURS, TEST_TW_HOURS, TEST_TH_HOURS: 各言語の実行回数
# - 自動設定: MOCK_API_TYPE=fixed, MOCK_DELAY_ENABLED=0
#
# ## テスト仕様
#
# ### データ件数
# - 各カテゴリごとに急上昇:80件、ランキング:80件を返す（固定）
# - 日本語: 25時間分のテストデータ（hourIndex: 0〜24、23:30開始、翌23:30まで、JA_HOURS設定による）
# - 日本語以外（繁体字/タイ語）: 1時間分のテストデータ（hourIndex: 0のみ）
#
# ### カテゴリ0（すべて/全部/ทั้งหมด）
# - 急上昇のみルーム16件（hourIndex=0のみランキングに出現）
#   - インデックス0（1位）: hourIndex>=1で詳細API/招待ページで404（削除された挙動）
#   - インデックス1-15（2-16位）: hourIndex>=1でも詳細API/招待ページで取得可能（削除されていない）
#
# ### カテゴリ1以降
# - 人数固定ルーム16件（hourIndex=0のみランキングに出現）
#   - 5人×8（1-8位）: hourIndex>=1でも詳細API/招待ページで取得可能（削除されていない）
#   - 10人×4（9-12位）:
#     - 9位（インデックス8）: hourIndex>=1で詳細API/招待ページで404（削除された挙動）
#     - 10-12位（インデックス9-11）: hourIndex>=1でも詳細API/招待ページで取得可能（削除されていない）
#   - 20人×4（13-16位）: hourIndex>=1でも詳細API/招待ページで取得可能（削除されていない）
#
# ## オプション
# - `-y`: 既存のhour_index.txtがある場合、自動的に次の時間から開始
# - `-n`: 既存のhour_index.txtがある場合、自動的に次の23:30から開始

set -e

# 設定
APP_CONTAINER="oc-review-mock-app-1"
MOCK_CONTAINER="oc-review-mock-line-mock-api-1"
MYSQL_CONTAINER="oc-review-mock-mysql-1"
LOG_DIR="./test-logs"
# CI環境判定
if [ -n "$CI" ]; then
    COMPOSE_CMD="docker compose -f docker-compose.yml -f docker-compose.ci.yml"
else
    COMPOSE_CMD="docker compose -f docker-compose.yml -f docker-compose.mock.yml"
fi

# オプションの処理
AUTO_CONTINUE=false
AUTO_NEXT_2330=false
if [[ "$1" == "-y" ]]; then
    AUTO_CONTINUE=true
elif [[ "$1" == "-n" ]]; then
    AUTO_NEXT_2330=true
fi

# コマンドラインから渡された環境変数を保存（.env.mockより優先）
CMDLINE_TEST_JA_HOURS=${TEST_JA_HOURS:-}
CMDLINE_TEST_TW_HOURS=${TEST_TW_HOURS:-}
CMDLINE_TEST_TH_HOURS=${TEST_TH_HOURS:-}

# docker/line-mock-api/.env.mockから設定を読み込む
if [ -f docker/line-mock-api/.env.mock ]; then
    source docker/line-mock-api/.env.mock
fi

# コマンドラインから渡された環境変数を優先（空でなければ上書き）
if [ -n "$CMDLINE_TEST_JA_HOURS" ]; then
    TEST_JA_HOURS=$CMDLINE_TEST_JA_HOURS
fi
if [ -n "$CMDLINE_TEST_TW_HOURS" ]; then
    TEST_TW_HOURS=$CMDLINE_TEST_TW_HOURS
fi
if [ -n "$CMDLINE_TEST_TH_HOURS" ]; then
    TEST_TH_HOURS=$CMDLINE_TEST_TH_HOURS
fi

# 言語ごとの実行回数設定（環境変数が設定されていればそれを使用、なければデフォルト値）
JA_HOURS=${TEST_JA_HOURS:-25}  # 日本語（hourIndex: 0〜24の25時間分）
TW_HOURS=${TEST_TW_HOURS:-25}   # 繁体字中国語
TH_HOURS=${TEST_TH_HOURS:-25}   # タイ語

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

# MySQLのレコード数を取得
get_mysql_count() {
    local database=$1
    local table=$2

    docker exec "$MYSQL_CONTAINER" mysql -uroot -ptest_root_pass -sN \
        -e "SELECT COUNT(*) FROM ${database}.${table}" 2>/dev/null || echo "0"
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

    # docker/line-mock-api/.env.mockを自動設定（固定データモード用）
    log_info "docker/line-mock-api/.env.mockを固定データモード用に設定中..."

    # CI環境では.env.mockは不要（docker-compose.ci.ymlで環境変数を直接設定）
    if [ -z "$CI" ]; then
        # ローカル環境の場合
        if [ ! -f docker/line-mock-api/.env.mock ]; then
            if [ ! -f docker/line-mock-api/.env.mock.example ]; then
                log_error "docker/line-mock-api/.env.mock.exampleが見つかりません"
                exit 1
            fi
            cp docker/line-mock-api/.env.mock.example docker/line-mock-api/.env.mock
            log_info "docker/line-mock-api/.env.mock.exampleからdocker/line-mock-api/.env.mockを作成しました"
        else
            log_info "既存のdocker/line-mock-api/.env.mockを使用します"
        fi

        # 必要な設定を上書き（sedを使用してコメントを保持）
        sed -i 's/^MOCK_DELAY_ENABLED=.*/MOCK_DELAY_ENABLED=0/' docker/line-mock-api/.env.mock
        sed -i 's/^MOCK_API_TYPE=.*/MOCK_API_TYPE=fixed/' docker/line-mock-api/.env.mock

        log_success "docker/line-mock-api/.env.mockを設定しました（固定データモード、遅延なし）"
    else
        log_info "CI環境: docker-compose.ci.ymlの設定を使用（固定データモード、遅延なし）"
    fi

    # hourIndexの処理（既存の値があれば継続するか確認）
    docker exec "$MOCK_CONTAINER" mkdir -p /app/data
    local existing_hour_index=0
    if docker exec "$MOCK_CONTAINER" test -f /app/data/hour_index.txt 2>/dev/null; then
        existing_hour_index=$(docker exec "$MOCK_CONTAINER" cat /app/data/hour_index.txt 2>/dev/null || echo "0")
        existing_hour_index=$((existing_hour_index))

        if [ $existing_hour_index -gt 0 ]; then
            log_warn "既存のhour_index.txtが見つかりました（現在の値: ${existing_hour_index}）"

            local start_from_next=false
            local start_from_next_2330=false

            if [ "$AUTO_CONTINUE" = true ]; then
                # -yオプション: 自動的に次の時間から開始
                log_info "-yオプションが指定されたため、次の時間（hourIndex: $((existing_hour_index + 1))）から自動的に開始します"
                start_from_next=true
            elif [ "$AUTO_NEXT_2330" = true ]; then
                # -nオプション: 自動的に次の23:30から開始
                log_info "-nオプションが指定されたため、次の23:30から自動的に開始します"
                start_from_next_2330=true
            else
                # オプションなし: ユーザーに選択を求める
                local tomorrow_date=$(date -d "+1 day" '+%Y-%m-%d')
                local base_time=$(date -d "${tomorrow_date} 23:30:00" +%s)
                local last_time=$((base_time + existing_hour_index * 3600))
                local next_2330_hours=$(( 24 - (existing_hour_index % 24) ))
                local next_2330_index=$((existing_hour_index + next_2330_hours))

                echo ""
                echo -e "${YELLOW}既存の進行状況が見つかりました:${NC}"
                echo "  現在のhour_index: ${existing_hour_index}"
                echo "  最後に実行した時刻: $(date -d "@$last_time" '+%Y-%m-%d %H:%M:%S')"
                echo ""
                echo "どのように開始しますか？"
                echo "  1) 次の時間（hourIndex: $((existing_hour_index + 1))、時刻: $(date -d "@$((last_time + 3600))" '+%Y-%m-%d %H:%M:%S')）から継続"
                echo "  2) 次の23:30（hourIndex: ${next_2330_index}、時刻: $(date -d "@$((base_time + next_2330_index * 3600))" '+%Y-%m-%d %H:%M:%S')）から継続"
                echo "  3) 最初（hourIndex: 0、時刻: $(date -d "@$base_time" '+%Y-%m-%d %H:%M:%S')）からやり直し"
                echo ""
                read -p "選択してください [1-3]: " choice

                case $choice in
                    1)
                        log_info "次の時間から継続します"
                        start_from_next=true
                        ;;
                    2)
                        log_info "次の23:30から継続します"
                        start_from_next_2330=true
                        ;;
                    3)
                        log_info "最初からやり直します"
                        existing_hour_index=0
                        ;;
                    *)
                        log_error "無効な選択です"
                        exit 1
                        ;;
                esac
            fi

            if [ "$start_from_next" = true ]; then
                # 次の時間から開始（hourIndexに1を加える）
                existing_hour_index=$((existing_hour_index + 1))
                log_info "hourIndexを${existing_hour_index}から開始します"
            elif [ "$start_from_next_2330" = true ]; then
                # 次の23:30から開始
                # 既存のhour_indexが示す時刻から、次の23:30までの時間を計算
                # existing_hour_indexは23:30を基準に何時間経過したかを示す
                # 次の23:30は: 現在の経過時間に対して次の24の倍数
                local hours_until_next_2330=$(( 24 - (existing_hour_index % 24) ))
                existing_hour_index=$((existing_hour_index + hours_until_next_2330))
                log_info "開始hourIndex: ${existing_hour_index}（${hours_until_next_2330}時間後の23:30）"
            fi
        else
            log_info "hourIndexを初期化します（0から開始）"
            existing_hour_index=0
        fi
    else
        log_info "hourIndexを初期化します（0から開始）"
        existing_hour_index=0
    fi

    # hourIndexを設定
    echo "$existing_hour_index" | docker exec -i "$MOCK_CONTAINER" tee /app/data/hour_index.txt > /dev/null

    # 開始hourIndexを保存（後で使用）
    START_HOUR_INDEX=$existing_hour_index

    # コンテナチェック
    check_containers

    # faketimeが利用可能か確認
    if ! docker exec "$APP_CONTAINER" which faketime > /dev/null 2>&1; then
        log_error "faketimeがインストールされていません"
        log_error "コンテナを再ビルドしてください: make rebuild-mock"
        exit 1
    fi

    log_info "faketimeを使用してテストを実行します"

    # 開始時刻を設定
    # SSL証明書の有効期間を考慮して翌日以降に設定
    # 23:30から開始することで、1時間目に日次処理が実行される
    local tomorrow_date=$(date -d "+1 day" '+%Y-%m-%d')
    local base_time=$(date -d "${tomorrow_date} 23:30:00" +%s)

    # START_HOUR_INDEXに基づいて開始時刻を調整
    CURRENT_TIME=$((base_time + START_HOUR_INDEX * 3600))

    log "テスト開始時刻: $(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S')（hourIndex: ${START_HOUR_INDEX}）"
    log_info "この時刻を基準に各言語の設定時間分進めます"
    if [ $START_HOUR_INDEX -eq 0 ]; then
        log_info "1時間目（23:30）に日次処理が実行されます"
    fi

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
        # 実際のhourIndex（START_HOUR_INDEXからの相対位置）
        local actual_hour_index=$((START_HOUR_INDEX + hour - 1))

        # 時刻を1時間進める
        if [ $hour -gt 1 ]; then
            CURRENT_TIME=$((CURRENT_TIME + 3600))
        fi



        log "========================================="
        log "第${hour}時間目 (hourIndex: ${actual_hour_index}, $(date -d "@$CURRENT_TIME" '+%Y-%m-%d %H:%M:%S'))"
        log "========================================="

        # hourIndexを更新
        local hour_index=$actual_hour_index
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

        # バックグラウンドで起動したPHPプロセス（persist_ranking_position_background.phpなど）の終了を待機
        log_info "バックグラウンドプロセスの終了を待機中..."
        local wait_count=0
        local max_wait=120  # 最大2分待機
        while true; do
            # コンテナ内のPHPバックグラウンドプロセスを確認（cron_crawling.php以外）
            local php_processes=$(docker exec "$APP_CONTAINER" pgrep -f "php batch/" 2>/dev/null | wc -l)
            if [ "$php_processes" -eq 0 ]; then
                log_info "✓ すべてのバックグラウンドプロセスが完了しました"
                break
            fi

            wait_count=$((wait_count + 1))
            if [ $wait_count -ge $max_wait ]; then
                log_warn "バックグラウンドプロセスの待機がタイムアウトしました（${php_processes}個のプロセスがまだ実行中）"
                break
            fi

            sleep 1
        done

        # 進捗表示
        local progress=$((hour * 100 / max_hours))
        log "進捗: ${hour}/${max_hours}時間 (${progress}%)"

        # 現在のデータベースレコード数を表示
        local ja_count=$(get_mysql_count "ocgraph_ocreview" "open_chat")
        local tw_count=$(get_mysql_count "ocgraph_ocreviewtw" "open_chat")
        local th_count=$(get_mysql_count "ocgraph_ocreviewth" "open_chat")
        log_info "DB状況 - 日本語: ${ja_count}件, 繁体字: ${tw_count}件, タイ語: ${th_count}件"

        echo "" | tee -a "$LOG_FILE"
    done

    log "========================================="
    log "カテゴリ別クローリングテスト完了"
    log "ログファイル: ${LOG_FILE}"
    log "個別ログ: ${LOG_DIR}/"
    log "========================================="

    # データ検証
    log ""
    log "データ検証を開始..."
    if bash "$(dirname "$0")/verify-test-data.sh"; then
        log_success "データ検証に成功しました"
    else
        log_error "データ検証に失敗しました"
        exit 1
    fi
}

# Ctrl+Cでの中断をハンドル
trap 'log_error "テストが中断されました"; exit 130' INT TERM

# メイン処理実行
main
