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
log_ok ".env DATA_PROTECTION=false 確認"

CURRENT_STEP=""
trap '[ -n "$CURRENT_STEP" ] && echo "FAILED at step: $CURRENT_STEP — 再実行で続行可能 (各ステップは冪等)" >&2' ERR

# envsubst の存在チェック
if ! command -v envsubst >/dev/null 2>&1; then
    echo "Error: envsubst (gettext-base) が必要です。" >&2
    exit 1
fi

# ============================================
# 1. local-secrets.php を生成
# ============================================
CURRENT_STEP="local-secrets.php生成"
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

# ============================================
# 2. リモート/ローカル DB チェック
# ============================================
CURRENT_STEP="mysql-check"
mysql_check_remote_dbs
mysql_check_local_dbs

# ============================================
# 3. MySQL: ダンプ → 転送 → インポート
# ============================================
CURRENT_STEP="mysql"
mysql_dump_remote
mysql_rsync_dumps
mysql_import_local
mysql_ensure_comment_image_table

# ============================================
# 4. SQLite: チェックポイント → rsync (sqlapi 含む)
# ============================================
CURRENT_STEP="sqlite"
sqlite_checkpoint_remote true
sqlite_rsync_dbs true

# ============================================
# 5. 画像同期
# ============================================
CURRENT_STEP="images"
images_rsync_comment_img
images_rsync_comment_img_hidden

# ============================================
# 6. storage 派生キャッシュ
# ============================================
CURRENT_STEP="static"
static_rsync_lang_dirs

# ============================================
# 7. DATA_PROTECTION=true へ切替
# ============================================
CURRENT_STEP="data-protection-flip"
log_step "7/8: DATA_PROTECTION=true に切替"
sed -i 's/^DATA_PROTECTION=false$/DATA_PROTECTION=true/' "${PROJECT_ROOT}/.env"
log_ok ".env を DATA_PROTECTION=true に変更"
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
