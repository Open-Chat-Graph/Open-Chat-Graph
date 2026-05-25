#!/usr/bin/env bash

# 本番同期 - 初回セットアップ
#
# 実行条件:
#   - .env の DATA_PROTECTION=false (これから true に切り替えるため)
#   - secrets/ に prod-sync.env, prod-ssh.key, local-secrets.tmpl.php が配置済み
#   - docker-compose で mysql, app コンテナが起動済み (make init-y 後)
#
# 実行内容:
#   1. local-secrets.php をテンプレから生成
#   2. ローカル DB を作成 (存在しなければ)
#   3. MySQL: フルダンプ → scp ベースで rsync 初期取得 → DROP+CREATE 再インポート
#   4. SQLite: WAL チェックポイント → rsync (ocgraph_sqlapi 含む)
#   5. comment-img / comment-img-hidden を rsync
#   6. storage 派生キャッシュを rsync
#   7. comment_image テーブル存在保証
#   8. .env の DATA_PROTECTION=true に切替

set -euo pipefail

PROD_SYNC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${PROD_SYNC_DIR}/../../.." && pwd)"
export PROD_SYNC_DIR PROJECT_ROOT

# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/config.sh"
# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/mysql.sh"
# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/sqlite.sh"
# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/images.sh"
# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/static.sh"

# ============================================
# 前提チェック
# ============================================
log_step "前提チェック"

if [ ! -f "${PROJECT_ROOT}/.env" ]; then
    echo "Error: .env が存在しません。先に make init-y を実行してください。" >&2
    exit 1
fi
if grep -q "^DATA_PROTECTION=true$" "${PROJECT_ROOT}/.env"; then
    echo "Error: DATA_PROTECTION=true です。setup は初回のみ実行できます。" >&2
    echo "       既にセットアップ済みの可能性があります。更新したい場合は make sync-update を使ってください。" >&2
    exit 1
fi
log_ok ".env DATA_PROTECTION≠true 確認 (setup 実行可)"

CURRENT_STEP=""
# 失敗時、重い手順(MySQL/SQLite)をやり直さず途中から再開できるよう、失敗ステップを案内する。
trap '[ -n "$CURRENT_STEP" ] && { echo "" >&2; echo "✗ FAILED at step: $CURRENT_STEP" >&2; echo "  それ以前の手順(MySQL/SQLite 等)をスキップして再開するには:" >&2; echo "      make sync-setup FROM=$CURRENT_STEP" >&2; }' ERR

# 途中再開ポイント。FROM=<step> でそれ以前のステップをスキップして再開する。
# 有効値: secrets dbcheck mysql sqlite images static flip
SYNC_STEPS=(secrets dbcheck mysql sqlite images static flip)
SYNC_FROM="${FROM:-}"
if [ -n "$SYNC_FROM" ] && ! printf '%s\n' "${SYNC_STEPS[@]}" | grep -qx "$SYNC_FROM"; then
    echo "Error: FROM=$SYNC_FROM は不正です。有効値: ${SYNC_STEPS[*]}" >&2
    exit 1
fi
_resume_reached=true
if [ -n "$SYNC_FROM" ]; then
    _resume_reached=false
    echo "  ▶ FROM=$SYNC_FROM: それ以前のステップをスキップして再開します"
fi
# step_active <name>: 実行すべきなら 0、(再開ポイント未到達で)スキップなら 1。
step_active() {
    [ "$_resume_reached" = true ] && return 0
    [ "$1" = "$SYNC_FROM" ] && { _resume_reached=true; return 0; }
    return 1
}

# envsubst の存在チェック
if ! command -v envsubst >/dev/null 2>&1; then
    echo "Error: envsubst (gettext-base) が必要です。" >&2
    exit 1
fi

# ============================================
# 1. local-secrets.php を生成
# ============================================
if step_active secrets; then
CURRENT_STEP="secrets"
log_step "1/8: local-secrets.php 生成"
if [ -f "${PROJECT_ROOT}/local-secrets.php" ]; then
    log_info "既存 local-secrets.php を退避: local-secrets.php.bak"
    mv "${PROJECT_ROOT}/local-secrets.php" "${PROJECT_ROOT}/local-secrets.php.bak"
fi
# MYSQL_HOST は env 由来。LOCAL_MYSQL_HOST を尊重し、未設定なら "mysql"。
MYSQL_HOST="${LOCAL_MYSQL_HOST:-mysql}" \
MYSQL_USER="$LOCAL_MYSQL_USER" \
MYSQL_PASS="$LOCAL_MYSQL_PASS" \
envsubst '${MYSQL_HOST} ${MYSQL_USER} ${MYSQL_PASS}' \
    < "$LOCAL_SECRETS_TMPL" \
    > "${PROJECT_ROOT}/local-secrets.php"
log_ok "local-secrets.php 生成完了"
fi

# ============================================
# 2. リモート/ローカル DB チェック
# ============================================
if step_active dbcheck; then
CURRENT_STEP="dbcheck"
mysql_check_remote_dbs
mysql_check_local_dbs
fi

# ============================================
# 3. MySQL: ダンプ → 転送 → インポート
# ============================================
if step_active mysql; then
CURRENT_STEP="mysql"
mysql_dump_remote
mysql_rsync_dumps
mysql_import_local
mysql_ensure_comment_image_table
else
log_step "3/8: MySQL (skip — FROM=$SYNC_FROM)"
fi

# ============================================
# 4. SQLite: チェックポイント → rsync (sqlapi 含む)
# ============================================
if step_active sqlite; then
CURRENT_STEP="sqlite"
sqlite_checkpoint_remote true
sqlite_rsync_dbs true
else
log_step "4/8: SQLite (skip — FROM=$SYNC_FROM)"
fi

# ============================================
# 5. 画像同期
# ============================================
if step_active images; then
CURRENT_STEP="images"
images_rsync_comment_img
images_rsync_comment_img_hidden
fi

# ============================================
# 6. storage 派生キャッシュ
# ============================================
if step_active static; then
CURRENT_STEP="static"
static_rsync_lang_dirs
fi

# ============================================
# 7. DATA_PROTECTION=true へ切替
# ============================================
if step_active flip; then
CURRENT_STEP="flip"
log_step "7/8: DATA_PROTECTION=true に切替"
# 3 ケースを冪等に処理: 既に true なら何もしない / 行があれば値を置換 / 行が無ければ追記。
# (従来の sed は DATA_PROTECTION=false 行が無いと無音で何もせず、未設定の .env を放置していた)
if grep -q "^DATA_PROTECTION=true$" "${PROJECT_ROOT}/.env"; then
    log_ok "既に DATA_PROTECTION=true"
elif grep -q "^DATA_PROTECTION=" "${PROJECT_ROOT}/.env"; then
    sed -i 's/^DATA_PROTECTION=.*/DATA_PROTECTION=true/' "${PROJECT_ROOT}/.env"
    log_ok ".env を DATA_PROTECTION=true に変更"
else
    printf '\nDATA_PROTECTION=true\n' >> "${PROJECT_ROOT}/.env"
    log_ok ".env に DATA_PROTECTION=true を追記 (行が無かったため)"
fi
fi
CURRENT_STEP=""

# ============================================
# 8. 完了
# ============================================
log_step "8/8: 完了"
echo ""
echo "========================================"
echo " ✓ 本番ミラー初回セットアップ完了"
echo "========================================"
echo ""
echo "今後の更新は:  make sync-update"
echo ""
