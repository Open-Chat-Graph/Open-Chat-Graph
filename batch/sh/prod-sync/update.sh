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
trap '[ -n "$CURRENT_STEP" ] && echo "FAILED at step: $CURRENT_STEP — 再実行で続行可能 (各ステップは冪等)" >&2' ERR

# ============================================
# 1. MySQL
# ============================================
CURRENT_STEP="mysql"
mysql_check_remote_dbs
mysql_check_local_dbs
mysql_dump_remote
mysql_rsync_dumps
mysql_import_local
mysql_ensure_comment_image_table

# ============================================
# 2. SQLite (sqlapi 除外)
# ============================================
CURRENT_STEP="sqlite"
sqlite_checkpoint_remote false
sqlite_rsync_dbs false

# ============================================
# 3. 画像
# ============================================
CURRENT_STEP="images"
images_rsync_comment_img
images_rsync_comment_img_hidden

# ============================================
# 4. storage 派生キャッシュ
# ============================================
CURRENT_STEP="static"
static_rsync_lang_dirs

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
CURRENT_STEP="derived"
derived_run_importer

CURRENT_STEP=""

# ============================================
# 完了
# ============================================
echo ""
echo "========================================"
echo " ✓ 本番ミラー差分更新 全完了"
echo "========================================"
