#!/usr/bin/env bash

# 本番同期 - 差分更新
#
# 実行条件:
#   - .env の DATA_PROTECTION=true (setup 済みであることの代理判定)
#   - secrets/ 3 ファイルが揃っている
#   - docker-compose で mysql, app コンテナが起動済み
#
# 実行内容:
#   1. MySQL: 安定フラグ付ダンプ → rsync 差分転送 → DROP+CREATE 再インポート
#   2. SQLite: WAL チェックポイント → rsync 差分 (ocgraph_sqlapi は転送せず)
#   3. comment-img / comment-img-hidden: rsync 差分 + --delete
#   4. storage 派生キャッシュ: rsync 差分 + --delete
#   5. comment_image テーブル存在保証 (スキーマ追加対応)
#   6. ocgraph_sqlapi: PHP インポータでローカル再構築 (差分追記)
#
# 失敗時は set -e で停止。各ステップは冪等なので make sync-update を再実行で続行可。
# rsync は default mode (temp+rename) のためローカルアプリ稼働中でも SQLite 読み込みを壊さない。

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
# shellcheck disable=SC1091
source "${PROD_SYNC_DIR}/lib/derived.sh"

# ============================================
# 前提チェック
# ============================================
log_step "前提チェック"

if [ ! -f "${PROJECT_ROOT}/.env" ]; then
    echo "Error: .env が存在しません。" >&2
    exit 1
fi
if ! grep -q "^DATA_PROTECTION=true$" "${PROJECT_ROOT}/.env"; then
    echo "Error: DATA_PROTECTION=true ではありません。先に make sync-setup を実行してください。" >&2
    exit 1
fi
log_ok ".env DATA_PROTECTION=true 確認"

CURRENT_STEP=""
# 失敗時、重い手順(MySQL/SQLite)をやり直さず途中から再開できるよう、失敗ステップを案内する。
trap '[ -n "$CURRENT_STEP" ] && { echo "" >&2; echo "✗ FAILED at step: $CURRENT_STEP" >&2; echo "  それ以前の手順(MySQL/SQLite 等)をスキップして再開するには:" >&2; echo "      make sync-update FROM=$CURRENT_STEP" >&2; }' ERR

# 途中再開ポイント。FROM=<step> でそれ以前のステップをスキップして再開する。
# 有効値: mysql sqlite images static derived
SYNC_STEPS=(mysql sqlite images static derived)
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

# ============================================
# 1. MySQL
# ============================================
if step_active mysql; then
CURRENT_STEP="mysql"
mysql_check_remote_dbs
mysql_check_local_dbs
mysql_dump_remote
mysql_rsync_dumps
mysql_import_local
mysql_ensure_comment_image_table
else
log_step "1: MySQL (skip — FROM=$SYNC_FROM)"
fi

# ============================================
# 2. SQLite (sqlapi 除外)
# ============================================
if step_active sqlite; then
CURRENT_STEP="sqlite"
sqlite_checkpoint_remote false
sqlite_rsync_dbs false
else
log_step "2: SQLite (skip — FROM=$SYNC_FROM)"
fi

# ============================================
# 3. 画像
# ============================================
if step_active images; then
CURRENT_STEP="images"
images_rsync_comment_img
images_rsync_comment_img_hidden
fi

# ============================================
# 4. storage 派生キャッシュ
# ============================================
if step_active static; then
CURRENT_STEP="static"
static_rsync_lang_dirs
fi

# ============================================
# リモート → ローカル取得 ここまでで完了
# ============================================
CURRENT_STEP=""
echo ""
echo "========================================"
echo " ✓ 本番からの取得・反映 完了"
echo "========================================"
echo ""
echo "  これ以降はローカルでの派生DB再構築 (ocgraph_sqlapi)。"
echo "  リモート通信なし。インポータ自体が冪等なので Ctrl-C で中断しても次回 sync-update で続行可能。"
echo ""

# ============================================
# 5. 派生 DB (ocgraph_sqlapi) をローカルで再構築
# ============================================
if step_active derived; then
CURRENT_STEP="derived"
derived_run_importer
fi

CURRENT_STEP=""

# ============================================
# 完了
# ============================================
echo ""
echo "========================================"
echo " ✓ 本番ミラー差分更新 全完了"
echo "========================================"
