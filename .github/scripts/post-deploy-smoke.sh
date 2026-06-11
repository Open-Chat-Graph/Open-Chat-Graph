#!/bin/bash
# デプロイ直後の本番スモークテスト（deploy.yml の Post-deploy smoke test から実行）
# 各言語の top / ランキングAPI / ルームページ / グラフAPI / レコメンド (+jaのみコメントAPI) を確認する。
# 約16リクエスト・数秒で完了。失敗時は exit 1（deploy.yml 側で Discord のデプロイ失敗通知が発火する）。
#
# 環境変数:
#   SITE_URL  チェック対象のベースURL（デフォルト: https://openchat-review.me）
#   IS_STG    "true" なら ja のみチェック（stg には tw/th のDBが無い）
#
# 手動実行例:
#   SITE_URL=https://openchat-review-dev.me IS_STG=true ./.github/scripts/post-deploy-smoke.sh

set -u

SITE_URL="${SITE_URL:-https://openchat-review.me}"
IS_STG="${IS_STG:-false}"
UA="Mozilla/5.0 (compatible; ocgraph-deploy-smoke)"
# JSON API はサイト内JSからのfetchを示す独自ヘッダーが無いと404を返す（直叩き収集対策）。ページにも付与して問題ない
API_CLIENT_HEADER="X-Ocg-Client: 1"
QUERY="page=0&limit=1&category=0&sub_category=&keyword=&list=all&sort=member&order=desc"
FAIL=0

check() { # 説明 URL [本文に必要なパターン]
    local out status
    out=$(mktemp)
    status=$(curl -sL -A "$UA" -H "$API_CLIENT_HEADER" -o "$out" -w "%{http_code}" --max-time 15 "$2" || echo 000)
    if [ "$status" = "200" ] && { [ -z "${3:-}" ] || grep -q "$3" "$out"; }; then
        echo "OK  $1 ($status)"
    else
        echo "::error::スモーク失敗: $1 (status=$status) $2 body: $(head -c 120 "$out")"
        FAIL=1
    fi
    rm -f "$out"
}

for LOC in "" /tw /th; do
    # stgにはja以外のDBが無いためjaのみチェック
    if [ "$IS_STG" = "true" ] && [ -n "$LOC" ]; then
        continue
    fi

    check "トップ${LOC}" "${SITE_URL}${LOC}"

    # ランキングAPIから実在ルームIDを取得（このAPI自体のスモークも兼ねる）
    OC_ID=$(curl -sL -A "$UA" -H "$API_CLIENT_HEADER" --max-time 15 "${SITE_URL}${LOC}/oclist?${QUERY}" | grep -oE '"id":[0-9]+' | head -1 | grep -oE '[0-9]+' || true)
    if [ -z "$OC_ID" ]; then
        echo "::error::スモーク失敗: ランキングAPI${LOC} からルームIDを取得できない"
        FAIL=1
        continue
    fi
    echo "OK  ランキングAPI${LOC} (id=${OC_ID})"

    check "ルームページ${LOC}" "${SITE_URL}${LOC}/oc/${OC_ID}"
    check "グラフAPI${LOC}" "${SITE_URL}${LOC}/oc/${OC_ID}/stats?category=0" '"date":\['

    case "$LOC" in
        "")    RECO="%E3%83%9D%E3%82%B1%E3%83%83%E3%83%88%E3%83%A2%E3%83%B3%E3%82%B9%E3%82%BF%E3%83%BC%EF%BC%88%E3%83%9D%E3%82%B1%E3%83%A2%E3%83%B3%EF%BC%89" ;;
        "/tw") RECO="%E7%BE%BD%E7%90%83" ;;
        "/th") RECO="Roblox" ;;
    esac
    check "レコメンド${LOC}" "${SITE_URL}${LOC}/recommend/${RECO}"

    # コメント機能はja運用のみ
    if [ -z "$LOC" ]; then
        check "コメントAPI" "${SITE_URL}/comment/${OC_ID}?page=0&limit=10" '^\['
    fi
done

exit "$FAIL"
